<?php

namespace App\Support;

use App\Models\Cohort;
use App\Models\FinanceEntry;
use App\Models\Payout;
use App\Models\Project;
use App\Models\Trainer;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * Module 11 — the two payout paths (§14.6) in one place. A project payout cannot be approved until the hirer's
 * payment is confirmed in the system; a training payout cannot be calculated until the end-of-cohort report is in
 * and the certified count is confirmed. Both are released by Finance, and releasing one posts its own line on the
 * ledger against the right budget — training money never pays for project work and the other way round.
 */
class Payouts
{
    /** Step 1 of either path — the office opens the payout row. It starts behind its own gate. */
    public static function raise(array $data): Payout
    {
        $isTraining = $data['stream'] === 'training';

        $cohort = $isTraining ? Cohort::findOrFail($data['cohort_id']) : null;
        $trainer = $cohort?->trainer;

        if ($isTraining && $trainer) {
            abort_unless($trainer->fundi_id === (int) $data['fundi_id'], 422,
                'A training payout is released to the fundi-trainer who ran the cohort — '.($trainer->fundi->full_name ?? 'the cohort trainer')
                .' taught '.$cohort->reference.', so confirm who the report was submitted by before opening this payout.');
        }

        $payout = Payout::create([
            ...$data,
            'reference' => Payout::nextReference(),
            'trainer_id' => $trainer?->id,
            'stage' => $isTraining ? 'awaiting_report' : 'awaiting_hirer_payment',
            'per_trainee_fee_ksh' => $data['per_trainee_fee_ksh'] ?? $trainer?->rate_ksh,
            // What the programme handed over for the job is a deduction — the project's own material lines decide it,
            // so the office never types a figure that disagrees with what the store gave out (§14.6 step 6).
            'materials_ksh' => $isTraining
                ? ($data['materials_ksh'] ?? 0)
                : (($data['materials_ksh'] ?? null) ?: self::materialsFor($data['project_id'] ?? null)),
            'raised_by_user' => auth()->id(),
        ]);

        static::storeFigures($payout);

        return $payout;
    }

    /** The value of a project's material lines that are recorded as handed over. */
    public static function materialsFor(?int $projectId): float
    {
        return $projectId ? Project::find($projectId)?->materialsSuppliedKsh() ?: 0 : 0;
    }

    /** Steps 3–5 of the project path: the hirer's money is confirmed in, then the payout is calculated and queued. */
    public static function confirmHirerPayment(Payout $payout, Request $request): Payout
    {
        abort_unless($payout->stream === 'project', 422,
            $payout->reference.' is a training payout — it waits for the end-of-cohort report, not a hirer payment (§14.6).');
        $payout->mustBe('awaiting_hirer_payment', 'held');

        $data = $request->validate([
            'hirer_paid_on' => 'required|date',
            'hirer_payment_ref' => 'required|string|max:80',
            'amount_received_ksh' => 'required|numeric|min:0|max:1000000000',
            'record_on_ledger' => 'nullable|boolean',
        ], [
            'hirer_paid_on.required' => 'A project payout is only released after the hirer has paid the organisation (§14.6).',
            'hirer_payment_ref.required' => 'Give the payment reference the Finance Officer found the receipt under.',
        ], [
            'hirer_paid_on' => 'Date the payment was received',
            'hirer_payment_ref' => 'Payment reference',
            'amount_received_ksh' => 'Amount the hirer paid',
        ]);

        $agreed = (float) $payout->agreed_amount_ksh;
        $received = (float) $data['amount_received_ksh'];

        abort_if($received + 0.01 < $agreed, 422,
            'The hirer has paid '.Finance::ksh($received).' against an agreed amount of '.Finance::ksh($agreed)
            .'. The fundi is paid out of what the organisation actually received — put the payout on hold with the balance, or record the true agreed amount.');

        $payout->update([
            'hirer_paid_on' => $data['hirer_paid_on'],
            'hirer_payment_ref' => $data['hirer_payment_ref'],
            'held_reason' => null,
        ]);

        if (! empty($data['record_on_ledger']) && ! FinanceEntry::existsFor('project', (int) $payout->project_id, 'project_payment')) {
            FinanceEntry::post('project_payment', 'Hirer payment for '.($payout->project->title ?? 'a project'), $received, [
                'method' => 'bank',
                'happened_on' => $data['hirer_paid_on'],
                'project_id' => $payout->project_id,
                'source_type' => Payout::class,
                'source_id' => $payout->id,
                'note' => 'Confirmed by Finance when '.$payout->reference.' was queued (reference '.$data['hirer_payment_ref'].').',
            ]);
        }

        $payout->update(['stage' => 'queued']);
        static::storeFigures($payout->refresh());

        return $payout;
    }

    /** Steps 1–2 of the training path: the report is in and the Programme Manager confirms the certified count. */
    public static function confirmReport(Payout $payout, Request $request): Payout
    {
        abort_unless($payout->stream === 'training', 422,
            $payout->reference.' is a project payout — it waits for the hirer\'s payment, not a cohort report (§14.6).');
        $payout->mustBe('awaiting_report', 'held');

        $data = $request->validate([
            'report_submitted_on' => 'required|date',
            'certified_count' => 'required|integer|min:0|max:10000',
            'per_trainee_fee_ksh' => 'required|numeric|min:0|max:100000000',
            'hosting_fee_ksh' => 'nullable|numeric|min:0|max:100000000',
            'report_note' => 'nullable|string|max:1000',
        ], [
            'certified_count.required' => 'The trainer is paid for the trainees who passed the assessment, so confirm that number (§14.6 step 2).',
        ], [
            'report_submitted_on' => 'Report submitted on',
            'certified_count' => 'Certified trainees',
            'per_trainee_fee_ksh' => 'Fee per trainee',
            'hosting_fee_ksh' => 'Workshop-hosting fee',
        ]);

        $payout->update([
            ...$data,
            'hosting_fee_ksh' => (float) ($data['hosting_fee_ksh'] ?? 0),
            'held_reason' => null,
            'stage' => 'queued',
        ]);

        static::storeFigures($payout->refresh());

        return $payout;
    }

    /** The approval step. A project payout is approved by Finance (an executive here); a training one by the Programme Manager. */
    public static function approve(Payout $payout): Payout
    {
        $payout->mustBe('queued');

        if ($payout->stream === 'project') {
            Finance::requireRelease('Approving the payout on '.$payout->reference);
        }

        $payout->update(['stage' => 'approved', 'approved_by_user' => auth()->id(), 'approved_on' => now()]);

        return $payout;
    }

    /** The release. Money leaves the programme, the ledger carries the line, the fundi's earnings carry the payout ID. */
    public static function release(Payout $payout, Request $request): Payout
    {
        $payout->mustBe('approved');
        Finance::requireRelease('Releasing '.$payout->reference);

        $data = $request->validate([
            'method' => 'required|in:mpesa,bank',
            'provider_ref' => 'required|string|max:80',
            'payee_phone' => 'nullable|required_if:method,mpesa|string|max:40',
            'payee_account' => 'nullable|required_if:method,bank|string|max:120',
            'released_on' => 'required|date',
            'note' => 'nullable|string|max:1000',
        ], [
            'provider_ref.required' => 'The M-Pesa or bank confirmation code is what makes this payout traceable (§14.6 step 7).',
            'payee_phone.required' => 'An M-Pesa payout needs the number it went to.',
            'payee_account.required' => 'A bank payout needs the account it went to.',
        ], [
            'provider_ref' => 'Confirmation code',
            'payee_phone' => 'Phone paid',
            'payee_account' => 'Account paid',
        ]);

        $kind = Payout::ledgerKind($payout->stream);

        FinanceEntry::post($kind, $payout->reference.' — '.($payout->fundi->full_name ?? 'E-Fundi')
            .' · '.$payout->sourceLabel(), (float) $payout->net_ksh, [
            'budget_category' => $payout->budget_category,
            'method' => $data['method'],
            'happened_on' => $data['released_on'],
            'project_id' => $payout->project_id,
            'cohort_id' => $payout->cohort_id,
            'fundi_id' => $payout->fundi_id,
            'source_type' => Payout::class,
            'source_id' => $payout->id,
            'note' => $payout->calculation.' · sent by '.Payout::METHODS[$data['method']]
                .', confirmation '.$data['provider_ref'].'.',
        ]);

        $payout->update([
            'stage' => 'released',
            'method' => $data['method'],
            'provider_ref' => $data['provider_ref'],
            'payee_phone' => $data['payee_phone'] ?? $payout->payee_phone,
            'payee_account' => $data['payee_account'] ?? $payout->payee_account,
            'released_by_user' => auth()->id(),
            'released_on' => Carbon::parse($data['released_on']),
            'note' => $data['note'] ?? $payout->note,
        ]);

        return $payout;
    }

    /** A payout that cannot move is put on hold with the reason — the row stays, it is never deleted. */
    public static function hold(Payout $payout, Request $request): Payout
    {
        $payout->mustBe('awaiting_hirer_payment', 'awaiting_report', 'queued', 'approved');

        $data = $request->validate([
            'held_reason' => 'required|string|max:1000|min:10',
        ], ['held_reason.min' => 'Say what is holding this payout — a dispute, a balance owed, a report still out.']);

        $payout->update(['stage' => 'held', 'held_reason' => $data['held_reason']]);

        return $payout;
    }

    public static function resume(Payout $payout): Payout
    {
        $payout->mustBe('held');

        $payout->update(['stage' => $payout->resumableStage(), 'held_reason' => null]);
        static::storeFigures($payout->refresh());

        return $payout;
    }

    /** A payout that will not be paid stays rejected on the register with its reason. */
    public static function reject(Payout $payout, Request $request): Payout
    {
        $payout->mustBe('queued', 'approved', 'held');

        if ($payout->stream === 'project') {
            Finance::requireRelease('Rejecting the payout on '.$payout->reference);
        }

        $data = $request->validate([
            'rejection_reason' => 'required|string|max:1000|min:10',
        ], ['rejection_reason.min' => 'A payout is refused on a reason the fundi can read — say what is missing.']);

        $payout->update(['stage' => 'rejected', 'rejection_reason' => $data['rejection_reason'], 'held_reason' => null]);

        return $payout;
    }

    /**
     * Module 7 — a dispute decided after the handover changes what the worker is owed, and the payout is the same
     * money record. The figure is carried over and the calculation rewritten, so the register and the work order
     * never show two different deductions for one job.
     */
    public static function carryPenalty(Payout $payout, float $penalty): Payout
    {
        $payout->update(['penalty_ksh' => $penalty]);
        static::storeFigures($payout->refresh());

        return $payout;
    }

    private static function storeFigures(Payout $payout): void
    {
        $figures = $payout->calculate();

        $payout->update([
            'gross_ksh' => $figures['gross'],
            'net_ksh' => $figures['net'],
            'calculation' => $figures['calculation'],
        ]);
    }
}
