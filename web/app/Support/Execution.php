<?php

namespace App\Support;

use App\Models\CtpRegistration;
use App\Models\FundiRegistration;
use App\Models\Payout;
use App\Models\Project;
use App\Models\ProjectApplication;
use App\Models\ProjectMaterial;
use App\Models\StockMovement;
use App\Models\WorkOrder;
use App\Models\WorkOrderDispute;
use App\Models\WorkOrderMilestone;
use App\Models\WorkOrderUpdate;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;

/**
 * Module 7 — the delivery path (§10.4). An award becomes a work order, the store issues materials against it, the
 * worker logs days and photos, a supervisor signs milestones off, the hirer inspects (the office records what they
 * said), a refused job goes into the dispute flow, and a confirmed one hands over to Module 11's payout register.
 * No figure is calculated in here: the payout does the money maths, the material lines do the deduction.
 */
class Execution
{
    /**
     * §10.2 — the order is generated when a project is assigned. Called from Project::awardTo, so whichever screen
     * awarded the job (the project page or the Module 6 desk) the order exists. Re-awarding to a different worker
     * opens a second order; the one the previous worker held is cancelled with the reason, never rewritten.
     */
    public static function openFor(Project $project, ProjectApplication $application): WorkOrder
    {
        $isCtp = $application->applicant_type === 'ctp';
        $assigneeId = (int) $application->applicant_id;

        $existing = WorkOrder::where('project_id', $project->id)
            ->where('assignee_type', $isCtp ? 'ctp' : 'fundi')
            ->where('assignee_id', $assigneeId)
            ->whereNotIn('status', ['cancelled'])
            ->first();

        if ($existing) {
            return $existing;
        }

        // A worker moved off a job keeps their order on record as cancelled — the log of what they were doing stays.
        WorkOrder::where('project_id', $project->id)->whereNotIn('status', ['cancelled', 'closed'])
            ->update([
                'status' => 'cancelled',
                'closed_on' => now(),
                'closure_note' => 'The project was reassigned, so this order was stopped.',
            ]);

        $fundi = $isCtp ? null : FundiRegistration::find($assigneeId);

        $order = WorkOrder::create([
            'reference' => WorkOrder::nextReference(),
            'project_id' => $project->id,
            'assignee_type' => $isCtp ? 'ctp' : 'fundi',
            'assignee_id' => $assigneeId,
            'assignee_name' => $application->full_name ?: ($fundi?->full_name ?? 'The assigned worker'),
            'status' => 'issued',
            'scope' => $project->description,
            'deliverables' => static::defaultDeliverables($project),
            'start_on' => today(),
            'deadline' => $project->deadline,
            'schedule' => 'milestone',
            'split' => WorkOrder::DEFAULT_SPLIT,
            'agreed_amount_ksh' => (float) $project->budget,
            'materials_note' => $project->materials,
            'raised_by_user' => auth()->id(),
        ]);

        static::buildMilestones($order, WorkOrder::DEFAULT_SPLIT);

        return $order;
    }

    /** §10.2 — what has to be handed over at the end, written from the project the office posted. */
    private static function defaultDeliverables(Project $project): string
    {
        return collect([
            $project->title.' at '.($project->ward ?: $project->county ?: 'the site on the project'),
            $project->duration ? 'within '.$project->duration : null,
            $project->materials ? 'with the materials the programme provides: '.str($project->materials)->limit(160)->toString() : null,
        ])->filter()->implode("\n");
    }

    /**
     * §10.5 — the split becomes the stages. The stages still on the list are matched to the new shares by position, so
     * a fourth stage is added or the last one taken off rather than the whole plan being rewritten underneath. Nothing
     * a supervisor signed is touched: re-planning over a signature needs the sign-off lifted first, and the office is
     * told so instead of the signed figure being quietly changed. A stage a new split drops is marked off the list,
     * never deleted, so the order keeps the plan it was issued with.
     */
    public static function buildMilestones(WorkOrder $order, array $split): void
    {
        $names = static::milestoneNames(count($split));
        $agreed = (float) $order->agreed_amount_ksh;
        $start = $order->start_on?->copy() ?? today();
        $end = ($order->deadline?->isFuture() ?? false) ? $order->deadline->copy() : $start->copy()->addDays(28);
        $span = max(1, (int) $start->diffInDays($end));
        $count = max(1, count($split));

        $live = $order->milestones()->where('status', '!=', WorkOrderMilestone::DROPPED)
            ->orderBy('id')->get()->values();

        $dropping = $live->slice(count($split));
        $held = $dropping->first(fn (WorkOrderMilestone $m) => $m->isSigned());

        if ($held) {
            abort(422, $held->name.' on '.$order->reference.' was signed off, so a split of only '.$count
                .' '.str('stage')->plural($count).' would drop a stage that has a signature on it. '
                .'Lift that sign-off first if the stage really is off the plan.');
        }

        foreach (array_values($split) as $i => $share) {
            $fields = [
                'name' => $names[$i],
                'share_percent' => (int) $share,
                'amount_ksh' => round($agreed * (int) $share / 100, 2),
                'due_on' => $start->copy()->addDays((int) ceil(($i + 1) / $count * $span))->toDateString(),
            ];

            $row = $live->get($i);

            if ($row && $row->isSigned()) {
                if ((int) $row->share_percent !== (int) $share) {
                    abort(422, $row->name.' on '.$order->reference.' was signed off at '
                        .(int) $row->share_percent.'% — '.$row->amountLabel().'. A signature is not re-planned: lift the '
                        .'sign-off first if this stage\'s share really changes (§10.5).');
                }

                $row->update(['due_on' => $fields['due_on']]);

                continue;
            }

            $row ? $row->update($fields + ['status' => WorkOrderMilestone::PENDING])
                : $order->milestones()->create($fields + ['status' => WorkOrderMilestone::PENDING]);
        }

        foreach ($dropping as $row) {
            $row->update(['status' => WorkOrderMilestone::DROPPED, 'note' => 'A new split no longer has this stage on it.']);
        }
    }

    /** The blueprint's own names for the stages: mobilization first, completion last, milestones in between. */
    public static function milestoneNames(int $count): array
    {
        return match (true) {
            $count <= 1 => ['Completion'],
            $count === 2 => ['Mobilization', 'Completion'],
            $count === 3 => ['Mobilization', 'Milestone', 'Completion'],
            default => collect(range(1, $count))->map(fn ($i) => match (true) {
                $i === 1 => 'Mobilization',
                $i === $count => 'Completion',
                default => 'Milestone '.($i - 1),
            })->all(),
        };
    }

    /** §10.4 step 1 — the worker accepts, signs digitally and confirms the start date. Only they can do it. */
    public static function accept(WorkOrder $order, Request $request): WorkOrder
    {
        $order->mustBe('issued');

        $data = $request->validate([
            'signature' => 'required|string|min:3|max:160',
            'start_on' => 'required|date',
            'accept_note' => 'nullable|string|max:1000',
        ], [
            'signature.required' => 'Type your name as your signature — the order is signed when you accept it (§10.4 step 1).',
            'start_on.required' => 'Confirm the day you are starting. The deadline is measured from it.',
        ], [
            'signature' => 'Digital signature',
            'start_on' => 'Start date',
            'accept_note' => 'Anything the office should know',
        ]);

        $order->update([
            ...$data,
            'accepted_on' => now(),
            'status' => 'accepted',
        ]);

        return $order;
    }

    /**
     * §10.4 steps 3–4 — a day logged, by the worker or as a supervisor's visit. The order keeps the highest figure
     * reported on it and never goes down: a wrong percentage is answered by the next line, not by rewriting the old one.
     */
    public static function log(WorkOrder $order, array $data, Request $request, ?FundiRegistration $by): WorkOrderUpdate
    {
        $entry = WorkOrderUpdate::create([
            'work_order_id' => $order->id,
            'kind' => $data['kind'],
            'entry_date' => $data['entry_date'],
            'percent' => $data['percent'] ?? null,
            'note' => $data['note'],
            'recorded_by_user' => $by ? null : auth()->id(),
            'recorded_by_fundi' => $by?->id,
        ]);

        $entry->storePhoto($request);

        if (isset($data['percent'])) {
            $order->update([
                'percent_complete' => max((int) $order->percent_complete, (int) $data['percent']),
                'status' => in_array($order->status, ['accepted', 'in_progress'], true) ? 'in_progress' : $order->status,
            ]);
        }

        return $entry;
    }

    /**
     * The percentage the sheet shows. It is the highest figure ever reported on the log — by the worker or by a
     * supervisor's visit — and it never goes down: a wrong figure is answered by the next line, not by a rewrite.
     */
    public static function rollUp(WorkOrder $order): int
    {
        return (int) $order->percent_complete;
    }

    /** §10.3 — the storekeeper hands a material line over against the order. A line from a stock card also goes off the shelf. */
    public static function issueMaterial(WorkOrder $order, ProjectMaterial $material, Request $request): ProjectMaterial
    {
        $data = $request->validate([
            'supplied_on' => 'required|date',
            'note' => 'nullable|string|max:1000',
        ], [
            'supplied_on.required' => 'Say the day the materials left the store — the payout is deducted from what was actually handed over.',
        ], ['supplied_on' => 'Date issued']);

        abort_unless((int) $material->project_id === (int) $order->project_id, 403,
            'That material line belongs to another project, so it cannot be issued against '.$order->reference.'.');
        abort_if($material->isSupplied(), 422, $material->name.' was already issued on '
            .($material->supplied_on?->format('d M Y') ?? 'a date on the line').'.');

        $material->update([
            'supplied_on' => $data['supplied_on'],
            'supplied_by_user' => auth()->id(),
            'note' => $data['note'] ?: $material->note,
        ]);

        if ($material->inventory_item_id) {
            $card = $material->item;

            abort_if((float) $card->qty_on_hand < (float) $material->quantity, 422,
                $card->name.' has only '.$card->quantityLabel().' on the shelf and this line needs '
                .$material->quantityLabel().'. Raise a purchase request in Module 11, or issue a smaller quantity on a second line.');

            StockMovement::create([
                'inventory_item_id' => $card->id,
                'direction' => 'out',
                'quantity' => $material->quantity,
                'unit_cost_ksh' => $material->unit_cost_ksh,
                'reason' => 'project_issue',
                'note' => 'Issued against '.$order->reference.' ('.($order->project->title ?? 'the project').').',
                'project_id' => $order->project_id,
                'source_type' => WorkOrder::class,
                'source_id' => $order->id,
                'moved_on' => $data['supplied_on'],
                'recorded_by_user' => auth()->id(),
            ]);

            $card->applyMovement('out', (float) $material->quantity);
        }

        return $material;
    }

    /** §10.4 step 4 — a supervisor or manager signs a stage off. */
    public static function signMilestone(WorkOrderMilestone $milestone, Request $request): WorkOrderMilestone
    {
        abort_if($milestone->isSigned(), 422, $milestone->name.' was already signed off on '
            .($milestone->signed_on?->format('d M Y') ?? 'a date on the record').'.');

        $data = $request->validate([
            'note' => 'required|string|max:1000',
        ], ['note.required' => 'Say what you saw when you signed this stage off — the signature is yours, not the worker\'s.'],
            ['note' => 'What was verified']);

        $milestone->update([
            'status' => WorkOrderMilestone::SIGNED,
            'signed_on' => now(),
            'signed_by_user' => auth()->id(),
            'note' => $data['note'],
        ]);

        return $milestone;
    }

    /** A wrong sign-off is lifted, not deleted — the milestone goes back to pending and the trail carries both moves. */
    public static function unsignMilestone(WorkOrderMilestone $milestone, Request $request): WorkOrderMilestone
    {
        $data = $request->validate([
            'note' => 'required|string|max:1000',
        ], ['note.required' => 'Say why the sign-off is being lifted.']);

        $milestone->update([
            'status' => WorkOrderMilestone::PENDING,
            'signed_on' => null,
            'signed_by_user' => null,
            'note' => 'Sign-off lifted: '.str($data['note'])->limit(700),
        ]);

        return $milestone;
    }

    /** §10.4 step 5 — the worker says the job is done. The final photos are logged on the same screen. */
    public static function requestCompletion(WorkOrder $order, Request $request): WorkOrder
    {
        $order->mustBe('accepted', 'in_progress');

        $data = $request->validate([
            'note' => 'required|string|max:5000',
            'photo' => 'nullable|image|max:8192',
        ], [
            'note.required' => 'Write what was completed. The hirer reads this when they inspect (§10.4 step 6).',
        ], ['note' => 'What was completed', 'photo' => 'Final photo']);

        $entry = WorkOrderUpdate::create([
            'work_order_id' => $order->id,
            'kind' => 'completion',
            'entry_date' => today(),
            'percent' => 100,
            'note' => $data['note'],
            'recorded_by_fundi' => $order->assignee_type === 'fundi' ? $order->assignee_id : null,
            'recorded_by_user' => $order->assignee_type === 'fundi' ? null : auth()->id(),
        ]);
        $entry->storePhoto($request);

        $order->update([
            'completion_requested_on' => now(),
            'percent_complete' => 100,
            'status' => 'completion_submitted',
        ]);

        return $order;
    }

    /**
     * §10.4 steps 6–7 — the hirer inspects. They have no account, so the office records what was said with the
     * signed sheet attached. Confirmed work marks the project completed and the payout can be opened.
     */
    public static function confirmCompletion(WorkOrder $order, Request $request): WorkOrder
    {
        $order->mustBe('completion_submitted', 'disputed');

        // The office records the hirer's words, but it cannot use that record to walk past a dispute that is still
        // standing: the dispute has an outcome and a decision on it before the work can read as confirmed (§10.6).
        if ($open = static::activeDispute($order)) {
            abort(422, $order->reference.' has an issue on it that no Director has decided yet — '
                .'"'.str($open->issue)->limit(120).'".'
                .($open->responded_on ? '' : ' The worker has not answered it either.')
                .' Confirm the work only once the dispute carries an outcome (§10.6).');
        }

        $data = WorkOrder::confirmationFrom($request);

        if ($order->schedule === 'milestone' && $outstanding = $order->milestonesOutstanding()) {
            abort(422, $order->reference.' still has '.$outstanding.' '
                .str('stage')->plural($outstanding).' on the split that nobody signed off (§10.4 step 4). '
                .'Sign the '.str('stage')->plural($outstanding).' off, lift a wrong sign-off, or put the order into dispute.');
        }

        $order->update([
            'confirmed_on' => $data['confirmed_on'],
            'confirmed_by_name' => $data['confirmed_by_name'],
            'confirmation_note' => $data['confirmation_note'],
            'completed_on' => $data['confirmed_on'],
            'status' => 'confirmed',
        ]);
        $order->storeConfirmationSheet($request);

        // §10.4 step 7 — the job is done, so the project leaves the award queue. The completion itself is the date
        // on this order, which is what M&E and the payout both read.
        $order->project->update(['status' => 'closed']);

        if ($order->assignee_type === 'fundi') {
            static::freeWorker($order, 'Completed '.$order->reference.' ('.($order->project->title ?? 'a project').') on '
                .Carbon::parse($data['confirmed_on'])->format('d M Y').'.');
        }

        return $order;
    }

    /** §10.4 step 6 — the hirer raises an issue instead, which opens the dispute flow (§10.6). */
    public static function raiseIssue(WorkOrder $order, Request $request): WorkOrderDispute
    {
        $order->mustBe('completion_submitted', 'confirmed');

        $data = WorkOrderDispute::dataFrom($request);

        $dispute = WorkOrderDispute::create([
            'work_order_id' => $order->id,
            'issue' => $data['issue'],
            'raised_by_user' => auth()->id(),
            'raised_on' => now(),
        ]);
        $dispute->storeEvidence($request);

        $order->update(['status' => 'disputed', 'confirmed_on' => null, 'completed_on' => null]);

        return $dispute;
    }

    /** §10.6 step 2 — the worker answers. Late answers are recorded too; the mediation reads them either way. */
    public static function answerDispute(WorkOrderDispute $dispute, Request $request, FundiRegistration $fundi): WorkOrderDispute
    {
        abort_unless($dispute->isAwaitingWorker(), 422,
            $dispute->responded_on
                ? 'Your answer is already on this dispute, and the office has it.'
                : 'This dispute has been decided, so there is nothing left to answer.');

        $data = $request->validate([
            'response' => 'required|string|max:5000',
        ], ['response.required' => 'Say your side of it — the Programme Manager reads both statements before mediating (§10.6).'],
            ['response' => 'Your answer']);

        $dispute->update([
            'response' => $data['response'],
            'responded_on' => now(),
            'responded_by_fundi' => $fundi->id,
        ]);

        return $dispute;
    }

    /** §10.6 step 3 — the Programme Manager mediates. Staff record it; the decision is an executive's. */
    public static function mediate(WorkOrderDispute $dispute, Request $request): WorkOrderDispute
    {
        $data = $request->validate([
            'note' => 'required|string|max:1000',
        ], ['note.required' => 'Write what mediation achieved — a dispute is settled on the record of what was tried.']);

        $dispute->update([
            'mediated_by_user' => auth()->id(),
            'mediation_note' => str($data['note'])->limit(1000)->toString(),
        ]);

        return $dispute;
    }

    /**
     * §10.6 step 3 — a Director decides. The five outcomes are closed, and each has a fixed consequence on the
     * order: work goes back, or the job is treated as confirmed, or it closes with no payout. The money itself is
     * Module 11's; a penalty recorded here is what the payout handover carries across.
     */
    public static function decide(WorkOrderDispute $dispute, Request $request): WorkOrderDispute
    {
        abort_if($dispute->isDecided(), 422, 'This dispute was decided on '
            .($dispute->decided_on?->format('d M Y') ?? 'a date on the record').' — a decision is answered by a new dispute, not by rewriting it.');

        Finance::requireRelease('Deciding the dispute on '.($dispute->workOrder->reference ?? 'a work order'));

        $data = $request->validate([
            'outcome' => 'required|in:'.implode(',', array_keys(WorkOrderDispute::OUTCOMES)),
            'penalty_ksh' => 'nullable|numeric|min:0|max:1000000000',
            'outcome_note' => 'required|string|max:1000',
        ], [
            'outcome.required' => 'Pick one of the five outcomes §10.6 allows — a dispute is never closed without an outcome.',
            'outcome_note.required' => 'Write what the decision means for the worker and the hirer.',
        ], [
            'outcome' => 'Outcome',
            'penalty_ksh' => 'Deduction from the payout (KSh)',
            'outcome_note' => 'The decision',
        ]);

        $penalty = (float) ($data['penalty_ksh'] ?? 0);
        $order = $dispute->workOrder;

        // The handover carried the deductions that existed at that moment. A dispute decided afterwards is the same
        // money record, so it moves the payout with it — but never over an approval or a release.
        $payout = $penalty > 0 ? static::payoutFor($order) : null;
        if ($payout) {
            abort_if(in_array($payout->stage, ['approved', 'released'], true), 422,
                $payout->reference.' has already been '.($payout->stage === 'released' ? 'released' : 'approved')
                .' on the Finance register. Ask Finance to put it '
                .'on hold before deciding a deduction on '.($order->reference ?? 'the order').'.');

            $taken = (float) $payout->materials_ksh + (float) $payout->commission_ksh + (float) $payout->mobilization_ksh
                + static::penalties($order) + $penalty;
            abort_if($taken > (float) $payout->agreed_amount_ksh, 422,
                'The materials, commission, mobilization credit and deductions on '.($order->reference ?? 'the order')
                .' would come to '.Finance::ksh($taken).' against an agreed '.Finance::ksh((float) $payout->agreed_amount_ksh)
                .' — '.($payout->fundi->name ?? 'the worker').' is never taken below zero.');
        }

        $dispute->update([
            'outcome' => $data['outcome'],
            'penalty_ksh' => $penalty,
            'outcome_note' => $data['outcome_note'],
            'decided_by_user' => auth()->id(),
            'decided_on' => now(),
        ]);

        match ($data['outcome']) {
            'rework' => $order->update([
                'status' => 'in_progress',
                'completion_requested_on' => null,
                'percent_complete' => min((int) $order->percent_complete, 95),
            ]),
            'refund' => $order->update([
                'status' => 'closed',
                'closed_on' => now(),
                'closure_note' => 'Dispute decided: refund. No payout is opened — Finance settles the refund in Module 11.',
            ]),
            default => $order->update(['status' => 'confirmed', 'completed_on' => $order->completed_on ?? now()]),
        };

        // A refund means no money goes to the worker, so the payout that is already on Finance's register cannot be
        // left sitting there waiting to be released. It is stopped there first, not here.
        if ($data['outcome'] === 'refund' && $open = static::payoutFor($order)) {
            abort_unless(in_array($open->stage, ['rejected', 'held'], true), 422,
                $open->reference.' is still open on the Finance register for '.($order->reference ?? 'the order')
                .'. Put it on hold or reject it there first — a refund is settled by Finance (§14.6), not by this decision.');
        }

        // The deduction a Director decides after the handover belongs on the payout the order already sits on,
        // so the two records show one figure (§10.6 -> §14.6).
        if ($payout) {
            Payouts::carryPenalty($payout, static::penalties($order));
        }

        // A payment outcome ends the job: the project leaves the award queue the same way a confirmed one does, and the
        // worker comes back off the job list — the order is finished, whether or not a payout was handed over.
        if (in_array($data['outcome'], ['full_payment', 'partial_payment', 'penalty'], true)) {
            $order->project->update(['status' => 'closed']);
            static::freeWorker($order, $order->reference.' was decided off its dispute on '.now()->format('d M Y').'.');
        }

        return $dispute;
    }

    /** What the payout handover carries: every penalty a Director decided on this order. */
    public static function penalties(WorkOrder $order): float
    {
        return round((float) $order->disputes()->whereNotNull('decided_on')->sum('penalty_ksh'), 2);
    }

    /** The dispute still on the order, for the sheet's own header. */
    public static function activeDispute(WorkOrder $order): ?WorkOrderDispute
    {
        return $order->disputes()->whereNull('decided_on')->latest('id')->first();
    }

    /**
     * §10.4 steps 8–10 — the hand into Module 11. The payout row is opened there, on its own register, already
     * behind the gate that waits for the hirer's money. Materials are not typed in: they are the value of what the
     * store actually handed over (§14.6 step 6).
     */
    public static function handover(WorkOrder $order, Request $request): Payout
    {
        $order->mustBe('confirmed');

        $data = $request->validate([
            'commission_ksh' => 'nullable|numeric|min:0|max:1000000000',
            'mobilization_ksh' => 'nullable|numeric|min:0|max:1000000000',
            'note' => 'nullable|string|max:1000',
        ], [], [
            'commission_ksh' => 'Service commission',
            'mobilization_ksh' => 'Mobilization credit',
        ]);

        if ($payout = static::payoutFor($order)) {
            return $payout;
        }

        abort_unless($order->assignee_type === 'fundi', 422,
            'This order was awarded to a CTP as attachment work, so there is no E-Fundi payout to open — '
            .'attachment is training, and it is credited on the trainee\'s record instead (§10.2).');

        $penalty = static::penalties($order);
        $agreed = (float) $order->agreed_amount_ksh;
        $materials = $order->materialsIssuedKsh();

        abort_if(($materials + $penalty + (float) ($data['commission_ksh'] ?? 0) + (float) ($data['mobilization_ksh'] ?? 0)) > $agreed, 422,
            'The deductions add up to more than the '.Finance::ksh($agreed).' agreed for '
            .($order->project->title ?? 'the job').'. Record what was actually issued and actually owed.');

        return Payouts::raise([
            'stream' => 'project',
            'fundi_id' => $order->assignee_id,
            'project_id' => $order->project_id,
            'agreed_amount_ksh' => $agreed,
            'commission_ksh' => (float) ($data['commission_ksh'] ?? 0),
            'mobilization_ksh' => (float) ($data['mobilization_ksh'] ?? 0),
            'penalty_ksh' => $penalty,
            'method' => 'mpesa',
            'payee_phone' => $order->fundi->phone,
            'note' => 'Opened from '.$order->reference.'. '
                .($data['note'] ?? '')
                .($penalty > 0 ? ' Carries the deduction decided on its dispute ('.$order->disputes()->where('penalty_ksh', '>', 0)->count().' on record).' : ''),
        ]);
    }

    /** The payout this order handed over — found on the register rather than a duplicated figure. */
    public static function payoutFor(WorkOrder $order): ?Payout
    {
        if ($order->assignee_type !== 'fundi') {
            return null;
        }

        return Payout::where('stream', 'project')
            ->where('project_id', $order->project_id)
            ->where('fundi_id', $order->assignee_id)
            ->latest('id')
            ->first();
    }

    /** Closing the order after the money has gone: the row stays, marked closed with the note. */
    public static function close(WorkOrder $order, Request $request): WorkOrder
    {
        $order->mustBe('confirmed');

        if ($order->assignee_type === 'fundi' && ! static::payoutFor($order)) {
            abort(422, $order->reference.' is confirmed but no payout is open for it on the Finance register. '
                .'Hand it to Finance first (§10.4 steps 8–10) — a closed order with no payout row leaves the worker unpaid.');
        }

        $data = $request->validate([
            'closure_note' => 'required|string|max:1000',
        ], ['closure_note.required' => 'Say what closed the order — the payout reference, or the reason nothing is owed.'],
            ['closure_note' => 'Why it is closed']);

        $order->update(['status' => 'closed', 'closed_on' => now(), 'closure_note' => $data['closure_note']]);

        return $order;
    }

    /** An order that will not be delivered is stopped with its reason. It is never deleted. */
    public static function cancel(WorkOrder $order, Request $request): WorkOrder
    {
        $order->mustBe('issued', 'accepted', 'in_progress', 'completion_submitted', 'disputed');

        $data = $request->validate([
            'closure_note' => 'required|string|min:10|max:1000',
        ], ['closure_note.min' => 'Say why the job is stopped — the worker reads this on their own record.'],
            ['closure_note' => 'Why it is stopped']);

        $order->update(['status' => 'cancelled', 'closed_on' => now(), 'closure_note' => $data['closure_note']]);

        if ($order->assignee_type === 'fundi') {
            static::freeWorker($order, $order->reference.' was stopped: '.str($data['closure_note'])->limit(140));
        }

        return $order;
    }

    /** A fundi who has finished, been stopped or been decided off a job goes back on the available list (§8.3). */
    private static function freeWorker(WorkOrder $order, string $note): void
    {
        if ($order->fundi->exists ?? false) {
            $order->fundi->update(['availability' => 'available', 'availability_note' => str($note)->limit(190)->toString()]);
        }
    }

    /** The desk's own waiting counts. */
    public static function queues(): array
    {
        $open = fn (array $statuses) => WorkOrder::whereIn('status', $statuses)->count();

        return [
            'awaiting' => $open(['issued']),
            'on_site' => $open(['accepted', 'in_progress']),
            'inspection' => $open(['completion_submitted']),
            'disputes' => WorkOrderDispute::whereNull('decided_on')->count(),
            'unhanded' => WorkOrder::where('status', 'confirmed')->count(),
        ];
    }

    /** Where an order sits, in one line — used on the register and on the member's own list. */
    public static function nextState(WorkOrder $order): string
    {
        $payout = static::payoutFor($order);

        return match (true) {
            $order->status === 'issued' => 'Waiting for '.$order->assignee_name.' to accept and sign (§10.4 step 1).',
            $order->status === 'disputed' => (static::activeDispute($order)?->isAwaitingWorker()
                ? 'The worker has to answer the hirer\'s issue within 48 hours.'
                : 'Mediation is with the Programme Manager; a Director decides the outcome.'),
            $order->status === 'completion_submitted' => 'Waiting for the hirer to inspect — the office records what they say.',
            $order->status === 'confirmed' && ! $payout => 'Confirmed. Hand it to Finance to open the payout.',
            $payout && $payout->stage === 'awaiting_hirer_payment' => 'Payout '.$payout->reference.' is waiting for the hirer to pay.',
            $payout && $payout->stage === 'released' => $payout->reference.' was released — the order can be closed.',
            $payout => 'Payout '.$payout->reference.' is '.$payout->stageLabel().'.',
            $order->status === 'cancelled' => (string) $order->closure_note,
            $order->status === 'closed' => (string) $order->closure_note,
            default => $order->percent_complete.'% reported on site.',
        };
    }

    /** The sheet's photo/file reader — one gate for every attachment on the module's own rows. */
    public static function serve(?string $path, ?string $name, string $fallback): mixed
    {
        abort_unless(filled($path) && Storage::disk('local')->exists($path), 404,
            'That attachment is no longer on record.');

        return Storage::disk('local')->response($path, $name ?: $fallback);
    }

    /**
     * What a page of workers has actually delivered, read off their own orders (§10.4). One read for a whole
     * register rather than one per row: what each finished, whether the work landed inside the deadline written on
     * the order, what is still running, and what a Director had to decide on its disputes. The programme keeps no
     * review score, so "how they got on" is built from these facts alone — every figure traces back to an order row.
     *
     * @return array<int, array{delivered: int, late: int, running: int, disputes: int, penalty: float,
     *                           value: float, orders: Collection, verdict: string}>
     */
    public static function ledgerFor(string $type, array $ids): array
    {
        $ids = array_values(array_unique(array_filter($ids)));

        if (! $ids) {
            return [];
        }

        $orders = WorkOrder::with(['project:id,reference,title', 'disputes'])
            ->where('assignee_type', $type)
            ->whereIn('assignee_id', $ids)
            ->orderByDesc('id')
            ->get()
            ->groupBy('assignee_id');

        return collect($ids)->mapWithKeys(fn ($id) => [$id => static::ledgerRow($orders->get($id, collect()))])->all();
    }

    /** The one line a register shows per worker: the figures, and the sentence that reads them out. */
    private static function ledgerRow(Collection $orders): array
    {
        $done = $orders->filter(fn (WorkOrder $o) => filled($o->completed_on));
        $late = $done->filter(fn (WorkOrder $o) => $o->deadline && $o->completed_on->gt($o->deadline));
        $open = $orders->filter(fn (WorkOrder $o) => in_array($o->status, ['issued', 'accepted', 'in_progress', 'completion_submitted', 'disputed'], true));
        $files = $orders->flatMap(fn (WorkOrder $o) => $o->disputes);
        $penalty = round((float) $files->sum('penalty_ksh'), 2);

        $say = fn (int $n, string $word) => $n.' '.str($word)->plural($n);

        $verdict = collect([
            $done->isNotEmpty()
                ? $say($done->count(), 'project').' delivered'.($late->isEmpty() ? ', all inside the deadline' : ' ('.$late->count().' late)')
                : null,
            $open->isNotEmpty() ? $say($open->count(), 'job').' still running' : null,
            $files->isNotEmpty()
                ? $say($files->count(), 'dispute').' on record'.($penalty > 0 ? ', '.Finance::ksh($penalty).' deducted' : '')
                : null,
        ])->filter()->implode(' · ');

        return [
            'delivered' => $done->count(),
            'late' => $late->count(),
            'running' => $open->count(),
            'disputes' => $files->count(),
            'penalty' => $penalty,
            'value' => round((float) $done->sum('agreed_amount_ksh'), 2),
            'orders' => $orders,
            'verdict' => $verdict !== '' ? $verdict : 'No work handed over yet',
        ];
    }

    /** Orders that are waiting on the worker themselves: unsigned, or a dispute they have not answered (§10.4 step 1, §10.6 step 2). */
    public static function awaitingWorker(Model $member): int
    {
        $type = $member instanceof CtpRegistration ? 'ctp' : 'fundi';

        return WorkOrder::where('assignee_type', $type)->where('assignee_id', $member->id)
            ->where(fn ($q) => $q->where('status', 'issued')
                ->orWhereHas('disputes', fn ($d) => $d->whereNull('decided_on')->whereNull('responded_on')))
            ->count();
    }

    /** A worker's own orders, newest first, with what belongs to each. */
    public static function for(FundiRegistration|CtpRegistration $member)
    {
        $type = $member instanceof CtpRegistration ? 'ctp' : 'fundi';

        return WorkOrder::with(['project.hirer', 'project.providedMaterials', 'milestones', 'updates' => fn ($q) => $q->latest('id')->limit(1),
            'disputes' => fn ($q) => $q->latest('id')->limit(1)])
            ->where('assignee_type', $type)
            ->where('assignee_id', $member->id)
            ->orderByRaw("field(status,'issued','completion_submitted','disputed','accepted','in_progress','confirmed','closed','cancelled')")
            ->orderByDesc('id')
            ->get();
    }
}
