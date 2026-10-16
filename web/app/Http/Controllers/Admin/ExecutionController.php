<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ProjectMaterial;
use App\Models\WorkOrder;
use App\Models\WorkOrderDispute;
use App\Models\WorkOrderMilestone;
use App\Models\WorkOrderUpdate;
use App\Support\Execution;
use App\Support\Finance;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Module 7 — the delivery desk (§10). One register of every work order an award has produced, and one sheet per
 * order where the office does the seven things that are not the worker's: fix the plan, issue materials, sign a
 * stage off, record a site visit, record what the hirer said when they inspected, run a dispute to its decision,
 * and hand the confirmed job to Module 11's payout register.
 */
class ExecutionController extends Controller
{
    /** The register's tabs. Each is a set of §10.4 states, not a new state of its own. */
    private const TABS = [
        'all' => ['label' => 'Every order', 'statuses' => null],
        'awaiting' => ['label' => 'Waiting to be accepted', 'statuses' => ['issued']],
        'site' => ['label' => 'On site', 'statuses' => ['accepted', 'in_progress']],
        'inspection' => ['label' => 'Waiting on the hirer', 'statuses' => ['completion_submitted']],
        'disputes' => ['label' => 'In dispute', 'statuses' => ['disputed']],
        'handover' => ['label' => 'Confirmed — to Finance', 'statuses' => ['confirmed']],
        'done' => ['label' => 'Closed or stopped', 'statuses' => ['closed', 'cancelled']],
    ];

    public function index(Request $request)
    {
        $tab = self::TABS[$request->query('tab', 'all')] ?? self::TABS['all'];
        $search = trim((string) $request->query('q'));

        $rows = WorkOrder::with(['project.hirer:id,organisation', 'fundi:id,full_name,ef_code,tier', 'milestones',
            'disputes' => fn ($q) => $q->whereNull('decided_on')])
            ->when($tab['statuses'], fn ($q) => $q->whereIn('status', $tab['statuses']))
            ->when($search !== '', fn ($q) => $q->where(fn ($w) => $w
                ->where('reference', 'like', "%{$search}%")
                ->orWhere('assignee_name', 'like', "%{$search}%")
                ->orWhereHas('project', fn ($p) => $p->where('title', 'like', "%{$search}%")
                    ->orWhere('reference', 'like', "%{$search}%"))))
            ->orderByRaw("field(status,'issued','completion_submitted','disputed','accepted','in_progress','confirmed','closed','cancelled')")
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString();

        $counts = collect(self::TABS)
            ->mapWithKeys(fn (array $t, string $key) => [$key => $t['statuses'] === null
                ? WorkOrder::count()
                : WorkOrder::whereIn('status', $t['statuses'])->count()]);

        return view('admin.execution.index', [
            'orders' => $rows,
            'tab' => $request->query('tab', 'all'),
            'tabs' => self::TABS,
            'counts' => $counts,
            'search' => $search,
            'queues' => Execution::queues(),
        ]);
    }

    public function show(WorkOrder $order)
    {
        $order->load([
            'project.hirer', 'project.providedMaterials.item', 'project.providedMaterials.provider',
            'fundi', 'milestones.signer', 'updates.recorder', 'updates.fundi',
            'disputes.raiser', 'disputes.mediator', 'disputes.decider', 'raiser',
        ]);

        return view('admin.execution.show', [
            'order' => $order,
            'dispute' => Execution::activeDispute($order),
            'payout' => Execution::payoutFor($order),
            'next' => Execution::nextState($order),
            'percent' => Execution::rollUp($order),
            'penalties' => Execution::penalties($order),
            'isExecutive' => Finance::isExecutive(),
            'outstanding' => $order->milestonesOutstanding(),
            'splitTotal' => array_sum($order->split ?: []),
        ]);
    }

    /** §10.2 — the office sets what the order actually says. A signed stage is never re-planned over (§10.5). */
    public function plan(Request $request, WorkOrder $order)
    {
        $order->mustBe('issued', 'accepted', 'in_progress');

        $data = $request->validate([
            'scope' => 'required|string|max:5000',
            'deliverables' => 'required|string|max:5000',
            'start_on' => 'required|date',
            'deadline' => 'nullable|date|after_or_equal:start_on',
            'schedule' => 'required|in:'.implode(',', array_keys(WorkOrder::SCHEDULES)),
            'agreed_amount_ksh' => 'required|numeric|min:0|max:1000000000',
            'materials_note' => 'nullable|string|max:1000',
        ], [
            'scope.required' => 'A work order has to state the work — the worker signs against what is written here (§10.2).',
            'deliverables.required' => 'Say what must be handed over at the end, so the hirer has something to inspect.',
        ], [
            'scope' => 'Scope of work',
            'deliverables' => 'Deliverables',
            'start_on' => 'Start date',
            'deadline' => 'Deadline',
            'schedule' => 'Payment schedule',
            'agreed_amount_ksh' => 'Agreed amount (KSh)',
            'materials_note' => 'Materials note',
        ]);

        $order->update($data);

        // A split that runs into a signature is refused, and the refusal has to leave the order exactly as it was —
        // the plan and the stages move together or not at all.
        $split = static::readSplit($request);

        if ($split) {
            DB::transaction(function () use ($order, $split) {
                $order->update(['split' => $split]);
                Execution::buildMilestones($order, $split);
            });
        }

        return back()->with('status', $order->reference.' updated — '.$order->scheduleLabel()
            .($split ? ', released on '.$order->fresh()->splitLabel() : '').'.');
    }

    /** A split is only read when the office chose one; a bad list is refused rather than quietly kept. */
    private static function readSplit(Request $request): ?array
    {
        $raw = collect($request->input('split', []))
            ->map(fn ($v) => (int) trim((string) $v))
            ->filter(fn (int $v) => $v > 0)
            ->values()
            ->all();

        if (! $raw) {
            return null;
        }

        abort_if(count($raw) > 6, 422, 'A split of more than six stages is not a payment schedule — the office signs each one off.');
        abort_if(array_sum($raw) !== 100, 422, 'The shares must come to 100%. '
            .implode(' / ', $raw).'% adds up to '.array_sum($raw). '%, so no money is released against it.');

        return $raw;
    }

    /** §10.4 step 2 — the storekeeper hands a line over against the order; a stock card is booked out with it. */
    public function issueMaterial(Request $request, WorkOrder $order, ProjectMaterial $material)
    {
        $order->mustBe('accepted', 'in_progress', 'issued', 'disputed');

        Execution::issueMaterial($order, $material, $request);

        return back()->with('status', $material->name.' issued for '.$order->reference.' — '
            .$material->totalLabel().' now sits in the payout deduction (§10.3).');
    }

    /** §10.4 step 4 — a supervisor signs a stage off. */
    public function signMilestone(Request $request, WorkOrder $order, WorkOrderMilestone $milestone)
    {
        $this->theirMilestone($order, $milestone);
        Execution::signMilestone($milestone, $request);

        return back()->with('status', $milestone->name.' signed off on '.$order->reference
            .' — '.Finance::ksh($milestone->amount()).' of the agreed amount is behind a signature.');
    }

    public function unsignMilestone(Request $request, WorkOrder $order, WorkOrderMilestone $milestone)
    {
        $this->theirMilestone($order, $milestone);
        Execution::unsignMilestone($milestone, $request);

        return back()->with('status', $milestone->name.' is open again on '.$order->reference.'. The trail carries both moves.');
    }

    /** §10.4 step 4 — the office records a site visit on the worker's log. */
    public function visit(Request $request, WorkOrder $order)
    {
        $order->mustBe('accepted', 'in_progress', 'completion_submitted', 'disputed');

        $data = WorkOrderUpdate::dataFrom($request, ['site_visit']);

        Execution::log($order, $data, $request, null);

        return back()->with('status', 'Site visit of '.Carbon::parse($data['entry_date'])->format('d M Y')
            .' recorded on '.$order->reference.'.');
    }

    /** §10.4 steps 6–7 — the hirer inspected and said yes. There is no hirer login, so the office types what was said. */
    public function confirm(Request $request, WorkOrder $order)
    {
        Execution::confirmCompletion($order, $request);

        return back()->with('status', $order->reference.' confirmed by '.trim((string) $order->confirmed_by_name)
            .' — the project is closed as delivered, and the job can be handed to Finance for the payout (§10.4 steps 8–10).');
    }

    /** §10.6 — the hirer would not confirm. The office records the issue and the 48 hours start. */
    public function dispute(Request $request, WorkOrder $order)
    {
        $dispute = Execution::raiseIssue($order, $request);

        return back()->with('status', 'Issue recorded on '.$order->reference.' — '
            .($order->assignee_name ?: 'the worker'). ' has 48 hours to answer, ending '
            .$dispute->dueOn()->format('d M Y H:i').'.');
    }

    public function mediate(Request $request, WorkOrder $order, WorkOrderDispute $dispute)
    {
        $this->theirDispute($order, $dispute);
        Execution::mediate($dispute, $request);

        return back()->with('status', 'Mediation recorded on the dispute against '.$order->reference
            .'. A programme executive decides the outcome (§10.6).');
    }

    /** §10.6 — a Director decides. Executive only, like every other decision that ends a record. */
    public function decide(Request $request, WorkOrder $order, WorkOrderDispute $dispute)
    {
        $this->theirDispute($order, $dispute);
        Execution::decide($dispute, $request);

        return back()->with('status', 'Dispute on '.$order->reference.' decided: '.$dispute->outcomeLabel()
            .((float) $dispute->penalty_ksh > 0 ? ' ('.$dispute->penaltyLabel().' onto the payout)' : '')
            .'. The order now reads '.str_replace('_', ' ', $order->fresh()->status).'.');
    }

    /** §10.4 steps 8–10 — the confirmed job becomes a payout row on Module 11's register. */
    public function handover(Request $request, WorkOrder $order)
    {
        $payout = Execution::handover($order, $request);

        return redirect()->route('admin.payouts.show', $payout)->with('status',
            $order->reference.' handed to Finance — '.$payout->reference.' is open on the payout register, waiting for '
            .'the hirer\'s money to be confirmed before anything is released (§14.6).');
    }

    public function close(Request $request, WorkOrder $order)
    {
        Execution::close($order, $request);

        return back()->with('status', $order->reference.' closed. The order and its log stay on the register.');
    }

    public function cancel(Request $request, WorkOrder $order)
    {
        Execution::cancel($order, $request);

        return back()->with('status', $order->reference.' stopped. The worker keeps the order on their record with the reason.');
    }

    /** Private-disk reads — an order's progress photo, the hirer's signed sheet, a dispute's evidence. */
    public function updatePhoto(WorkOrder $order, WorkOrderUpdate $update)
    {
        $this->theirUpdate($order, $update);

        return Execution::serve($update->photo_path, (string) $update->photo_name,
            $order->reference.'-'.str_replace('_', '-', $update->kind).'-'.$update->id);
    }

    public function confirmation(WorkOrder $order)
    {
        return Execution::serve($order->confirmation_path, (string) $order->confirmation_name, $order->reference.'-confirmation');
    }

    public function disputeEvidence(WorkOrder $order, WorkOrderDispute $dispute)
    {
        $this->theirDispute($order, $dispute);

        return Execution::serve($dispute->issue_path, (string) $dispute->issue_name, $order->reference.'-dispute-'.$dispute->id);
    }

    private function theirMilestone(WorkOrder $order, WorkOrderMilestone $milestone): void
    {
        abort_unless((int) $milestone->work_order_id === (int) $order->id, 403,
            'That stage is not on '.$order->reference.', so it cannot be signed from this sheet.');
    }

    private function theirDispute(WorkOrder $order, WorkOrderDispute $dispute): void
    {
        abort_unless((int) $dispute->work_order_id === (int) $order->id, 403,
            'That dispute is not on '.$order->reference.'.');
    }

    private function theirUpdate(WorkOrder $order, WorkOrderUpdate $update): void
    {
        abort_unless((int) $update->work_order_id === (int) $order->id, 403,
            'That entry is not on '.$order->reference.'.');
    }
}
