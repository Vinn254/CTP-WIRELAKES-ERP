<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BudgetLine;
use App\Models\Cohort;
use App\Models\FinanceEntry;
use App\Models\FundiRegistration;
use App\Models\Payout;
use App\Models\Project;
use App\Support\Finance;
use App\Support\Payouts;
use Illuminate\Http\Request;

/**
 * Module 11 — the payout register. Two paths on one screen, kept visibly apart: project income waiting on
 * the hirer's payment and approved by Finance, training income waiting on the end-of-cohort report and approved by
 * the Programme Manager before Finance releases it from the training budget. A payout that cannot move is held
 * with its reason, never deleted or duplicated.
 */
class PayoutController extends Controller
{
    public function index(Request $request)
    {
        $stream = in_array($request->query('f'), ['project', 'training'], true) ? $request->query('f') : 'all';
        $stage = $request->query('stage', 'all');
        $search = trim((string) $request->query('q'));

        $base = fn () => Payout::query();

        $rows = Payout::with(['fundi:id,full_name,ef_code,county', 'project:id,title', 'cohort:id,reference',
            'trainer.fundi:id,full_name', 'approver:id,name', 'releaser:id,name'])
            ->when($stream !== 'all', fn ($q) => $q->where('stream', $stream))
            ->when($stage !== 'all', fn ($q) => $q->where('stage', $stage))
            ->when($search !== '', fn ($q) => $q->where(fn ($w) => $w
                ->where('reference', 'like', "%{$search}%")
                ->orWhere('note', 'like', "%{$search}%")))
            ->orderByRaw("case stage when 'queued' then 0 when 'approved' then 1 when 'awaiting_hirer_payment' then 2
                when 'awaiting_report' then 2 when 'held' then 3 when 'released' then 4 else 5 end")
            ->orderByDesc('id')
            ->paginate(25)->withQueryString();

        return view('admin.finance.payouts', [
            'payouts' => $rows,
            'stream' => $stream,
            'stage' => $stage,
            'search' => $search,
            'stages' => Payout::STAGES,
            'categories' => BudgetLine::CATEGORIES,
            'fundis' => FundiRegistration::where('status', 'active')->orderBy('full_name')
                ->get(['id', 'full_name', 'ef_code', 'phone']),
            'projects' => Project::where('status', '!=', 'draft')->orderByDesc('id')->limit(200)
                ->get(['id', 'title', 'budget']),
            'cohorts' => Cohort::with('training:id,name')->orderByDesc('id')->limit(200)->get(['id', 'reference', 'training_id']),
            'totals' => [
                'queued' => (float) $base()->where('stage', 'queued')->sum('net_ksh'),
                'approved' => (float) $base()->where('stage', 'approved')->sum('net_ksh'),
                'released' => (float) $base()->where('stage', 'released')->sum('net_ksh'),
                'project' => (float) $base()->where('stream', 'project')->where('stage', 'released')->sum('net_ksh'),
                'training' => (float) $base()->where('stream', 'training')->where('stage', 'released')->sum('net_ksh'),
                'held' => $base()->where('stage', 'held')->count(),
                'lines' => $base()->count(),
            ],
        ]);
    }

    public function store(Request $request)
    {
        $payout = Payouts::raise(Payout::dataFrom($request));

        return back()->with('status', $payout->reference.' opened as '.$payout->streamLabel()
            .' for '.($payout->fundi->full_name ?? 'an E-Fundi').'. '.$payout->stageLabel().' — '
            .($payout->stream === 'training'
                ? 'the Programme Manager confirms the certified trainees once the end-of-cohort report is in.'
                : 'the payout is calculated once Finance confirms the hirer has paid.'));
    }

    public function show(Payout $payout)
    {
        $payout->load(['fundi:id,full_name,ef_code,county,phone', 'project:id,title,budget', 'cohort:id,reference',
            'trainer.fundi:id,full_name', 'raiser:id,name', 'approver:id,name', 'releaser:id,name']);

        return view('admin.finance.payout', [
            'payout' => $payout,
            'categories' => BudgetLine::CATEGORIES,
            'ledger' => FinanceEntry::where('source_type', Payout::class)->where('source_id', $payout->id)
                ->orWhere(fn ($q) => $q->where('fundi_id', $payout->fundi_id)
                    ->where('direction', 'expense')->whereIn('kind', ['fundi_payout', 'trainer_payout']))
                ->orderByDesc('happened_on')->limit(20)->get(['id', 'reference', 'kind', 'amount_ksh', 'happened_on', 'title']),
        ]);
    }

    public function confirmHirerPayment(Request $request, Payout $payout)
    {
        Payouts::confirmHirerPayment($payout, $request);

        return back()->with('status', $payout->reference.': the hirer\'s payment of '
            .Finance::ksh((float) $request->input('amount_received_ksh')).' is confirmed in the system ('
            .$request->input('hirer_payment_ref').'). The payout is calculated at '.$payout->refresh()->netLabel()
            .' and queued for approval.');
    }

    public function confirmReport(Request $request, Payout $payout)
    {
        Payouts::confirmReport($payout, $request);

        return back()->with('status', $payout->reference.': the end-of-cohort report is in and '
            .$payout->certified_count.' certified trainee(s) are confirmed. '.$payout->calculation
            .' — the Programme Manager approves, then Finance releases it from the training budget.');
    }

    public function approve(Payout $payout)
    {
        Payouts::approve($payout);

        return back()->with('status', $payout->reference.' was approved by '.$payout->approverLabel().' ('
            .($payout->approver->name ?? 'the office').') for '.Finance::ksh((float) $payout->net_ksh)
            .'. Finance now releases it — '.($payout->stream === 'training' ? 'out of the training budget.' : 'out of the project budget.').'');
    }

    public function release(Request $request, Payout $payout)
    {
        Payouts::release($payout, $request);

        return back()->with('status', $payout->reference.' released: '.Finance::ksh((float) $payout->net_ksh)
            .' to '.($payout->fundi->full_name ?? 'the fundi').' by '.$payout->methodLabel()
            .' (confirmation '.$payout->provider_ref.'). It is on the ledger against '.$payout->budgetLabel()
            .' and on the fundi\'s earnings history.');
    }

    public function hold(Request $request, Payout $payout)
    {
        Payouts::hold($payout, $request);

        return back()->with('status', $payout->reference.' is on hold: '.$payout->held_reason
            .'. The row stays on the register with its reason — it is not deleted and not raised twice.');
    }

    public function resume(Payout $payout)
    {
        Payouts::resume($payout);

        return back()->with('status', $payout->reference.' is back on its path at '.$payout->stageLabel().'.');
    }

    public function reject(Request $request, Payout $payout)
    {
        Payouts::reject($payout, $request);

        return back()->with('status', $payout->reference.' was not paid out: '.$payout->rejection_reason
            .'. The decision and its reason stay on the register.');
    }
}
