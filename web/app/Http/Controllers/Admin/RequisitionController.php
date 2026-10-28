<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BudgetLine;
use App\Models\FinanceEntry;
use App\Models\InventoryItem;
use App\Models\Project;
use App\Models\Requisition;
use App\Support\Departments;
use App\Support\Finance;
use Illuminate\Http\Request;

/**
 * The requisition register. Every desk inside the organisation raises here for what it needs to work; Procurement
 * checks and approves or rejects with a reason; Finance processes it and the row then reads Disbursed (money paid
 * out, with its line on the money register) or Distributed (goods handed over, with who received them). Nothing is
 * deleted — a rejected row stays with its reason and can be put back on the path.
 */
class RequisitionController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $status = $request->query('f', 'all');
        $desk = $request->query('desk', 'all');
        $search = trim((string) $request->query('q'));

        $visible = fn () => Requisition::query()->when($this->seesAll($user),
            fn ($q) => $q, fn ($q) => $q->whereIn('department', Departments::visibleSlugs($user)));

        $rows = Requisition::ordered()
            ->when(! $this->seesAll($user), fn ($q) => $q->whereIn('department', Departments::visibleSlugs($user)))
            ->when($status !== 'all', fn ($q) => $q->where('status', $status))
            ->when($desk !== 'all' && $this->seesAll($user), fn ($q) => $q->where('department', $desk))
            ->when($search !== '', fn ($q) => $q->where(fn ($w) => $w
                ->where('reference', 'like', "%{$search}%")
                ->orWhere('item_name', 'like', "%{$search}%")
                ->orWhere('purpose', 'like', "%{$search}%")))
            ->with(['raiser:id,name', 'project:id,title'])
            ->paginate(25)->withQueryString();

        return view('admin.requisitions.index', [
            'requisitions' => $rows,
            'status' => $status,
            'desk' => $desk,
            'search' => $search,
            'filters' => ['all' => 'Every requisition'] + Requisition::STATUSES,
            'counts' => ['all' => $visible()->count()] + collect(Requisition::STATUSES)
                ->mapWithKeys(fn ($label, $key) => [$key => $visible()->where('status', $key)->count()])->all(),
            'desks' => $this->seesAll($user) ? Departments::nameList() : [],
            'units' => InventoryItem::UNITS,
            'categories' => BudgetLine::categories(),
            'projects' => Project::orderBy('title')->pluck('title', 'id'),
            'myDepts' => Departments::nameList(Departments::visibleSlugs($user)),
            'seesAll' => $this->seesAll($user),
            'totals' => [
                'waiting' => $visible()->where('status', 'submitted')->count(),
                'withFinance' => $visible()->where('status', 'approved')->count(),
                'value' => (float) $visible()->whereIn('status', ['submitted', 'approved'])->sum('estimated_price_ksh'),
                'paid' => (float) Requisition::where('status', 'disbursed')->sum('paid_amount_ksh'),
            ],
        ]);
    }

    public function store(Request $request)
    {
        $data = Requisition::dataFrom($request);
        $data['reference'] = Requisition::nextReference();
        $data['status'] = 'submitted';
        $data['raised_by_user'] = $request->user()->id;

        $requisition = Requisition::create($data);

        return redirect()->route('admin.requisitions.show', $requisition)
            ->with('status', $requisition->reference.' is raised and with Procurement & Stores for a decision. '
                .$requisition->quantityLabel().' of '.$requisition->item_name.'.');
    }

    public function show(Request $request, Requisition $requisition)
    {
        $this->assertVisible($request->user(), $requisition);

        return view('admin.requisitions.show', [
            'rq' => $requisition,
            'lines' => FinanceEntry::where('source_type', Requisition::class)
                ->where('source_id', $requisition->id)->orderByDesc('id')->get(),
            'units' => InventoryItem::UNITS,
            'categories' => BudgetLine::categories(),
            'payments' => Requisition::PAYMENTS,
            'isExec' => Finance::isExecutive(),
            'canDecide' => $this->canDecide($request->user()) && $requisition->status === 'submitted',
            'canProcess' => $this->canProcess($request->user()) && $requisition->status === 'approved',
            'canReopen' => $this->canDecide($request->user()) && $requisition->status === 'rejected',
        ]);
    }

    /** Step 2 — Procurement checks the ask against stock and the budget and decides. */
    public function decide(Request $request, Requisition $requisition)
    {
        $user = $request->user();
        $this->assertVisible($user, $requisition);
        abort_unless($this->canDecide($user), 403, 'Approving a requisition is a Procurement & Stores action.');

        $data = $request->validate([
            'approve' => 'required|in:yes,no,back',
            'note' => 'required_if:approve,no|nullable|string|max:1000|min:10',
        ], [
            'note.required_if' => 'A requisition is rejected on a reason the desk that asked for it can read.',
            'note.min' => 'One line on why it is not being bought.',
        ], ['note' => 'Reason']);

        $requisition->mustBe($data['approve'] === 'back' ? 'rejected' : 'submitted');

        $requisition->update([
            'status' => match ($data['approve']) {
                'yes' => 'approved',
                'back' => 'submitted',
                default => 'rejected',
            },
            'decided_by_user' => $user->id,
            'decided_at' => now(),
            'decision_note' => $data['note'] ?? null,
        ]);

        return back()->with('status', $requisition->reference.' — '
            .match ($data['approve']) {
                'yes' => 'approved and forwarded to Finance to process.',
                'back' => 'put back on the path as raised, with the earlier rejection replaced.',
                default => 'rejected with the reason written on it. The desk that asked reads it here.',
            }.' '.$requisition->statusLabel());
    }

    /**
     * Step 3 — Finance processes the approved requisition. Paying money out is a release, so an executive account
     * signs it and one line goes on the money register; handing goods over is recorded here and moves no money.
     */
    public function process(Request $request, Requisition $requisition)
    {
        $user = $request->user();
        $this->assertVisible($user, $requisition);
        abort_unless($this->canProcess($user), 403, 'Processing a requisition is a Finance action.');

        $requisition->mustBe('approved');

        $data = $request->validate([
            'outcome' => 'required|in:disbursed,distributed',
            'paid_amount_ksh' => 'required_if:outcome,disbursed|nullable|numeric|min:0.01|max:1000000000',
            'paid_method' => 'required_if:outcome,disbursed|nullable|in:'.implode(',', array_keys(Requisition::PAYMENTS)),
            'given_to' => 'required_if:outcome,distributed|nullable|string|max:190',
            'finance_note' => 'nullable|string|max:1000',
        ], [
            'given_to.required_if' => 'Write who received the goods — that is the proof a distribution carries.',
            'paid_amount_ksh.required_if' => 'Write the amount that actually went out.',
        ], [
            'paid_amount_ksh' => 'Amount paid',
            'paid_method' => 'Paid by',
            'given_to' => 'Handed to',
        ]);

        if ($data['outcome'] === 'disbursed') {
            Finance::requireRelease('Paying out on a requisition');
        }

        $kind = $requisition->project_id ? 'project_misc' : 'operating_cost';

        $requisition->update([
            'status' => $data['outcome'],
            'processed_by_user' => $user->id,
            'processed_at' => now(),
            'paid_amount_ksh' => $data['outcome'] === 'disbursed' ? $data['paid_amount_ksh'] : null,
            'paid_method' => $data['outcome'] === 'disbursed' ? $data['paid_method'] : null,
            'given_to' => $data['outcome'] === 'distributed' ? $data['given_to'] : null,
            'finance_note' => $data['finance_note'] ?? null,
        ]);

        $line = $data['outcome'] === 'disbursed'
            ? FinanceEntry::post($kind, $requisition->reference.' — '.$requisition->item_name,
                (float) $data['paid_amount_ksh'], [
                    'method' => $data['paid_method'],
                    'budget_category' => $requisition->budget_category,
                    'project_id' => $requisition->project_id,
                    'note' => 'Disbursed on a requisition raised by '.$requisition->departmentLabel().'.'
                       .($data['finance_note'] ? ' '.$data['finance_note'] : ''),
                    'source_type' => Requisition::class,
                    'source_id' => $requisition->id,
                ])
            : null;

        return back()->with('status', $requisition->reference.' is '
            .($data['outcome'] === 'disbursed'
                ? 'disbursed: '.Finance::ksh((float) $data['paid_amount_ksh']).' out by '
                    .Requisition::PAYMENTS[$data['paid_method']].', and '.$line->reference.' is on the money register.'
                : 'distributed — '.$requisition->quantityLabel().' handed to '.$data['given_to'].'. '
                    .'No money line, because the goods came from the store.'));
    }

    /** A desk, or Procurement and Finance, read every row; everyone else reads only their own department's asks. */
    private function seesAll($user): bool
    {
        return $this->canDecide($user) || $this->canProcess($user);
    }

    private function canDecide($user): bool
    {
        return $user->isSuperAdmin() || in_array('procurement', Departments::visibleSlugs($user), true);
    }

    private function canProcess($user): bool
    {
        return $user->isSuperAdmin() || in_array('finance', Departments::visibleSlugs($user), true);
    }

    private function assertVisible($user, Requisition $requisition): void
    {
        abort_unless(
            $this->seesAll($user) || in_array($requisition->department, Departments::visibleSlugs($user), true),
            403, 'That requisition belongs to the '.$requisition->departmentLabel().' desk.'
        );
    }
}
