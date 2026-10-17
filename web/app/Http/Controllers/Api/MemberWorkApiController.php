<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CtpRegistration;
use App\Models\FundiRegistration;
use App\Models\SiteInspection;
use App\Models\SiteInspectionItem;
use App\Models\TechnologyTicket;
use App\Models\WorkOrder;
use App\Models\WorkOrderDispute;
use App\Models\WorkOrderUpdate;
use App\Support\Construction;
use App\Support\Execution;
use App\Support\Finance;
use App\Support\MemberPortal;
use App\Support\Technology;
use App\Support\UploadsFromJson;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * The worker's own screens over the API — My work orders (Module 7 §10.4/§10.6), Items to put right
 * (Module 8 §11.4 step 5), My repair jobs (Module 9 §12.7) and the profile photo. Every row is resolved from
 * the signed-in member's record, exactly as the portal controllers do, and each write calls the same support
 * method the website calls.
 */
class MemberWorkApiController extends Controller
{
    use UploadsFromJson;

    private function member()
    {
        $member = MemberPortal::resolve(auth()->user());
        abort_if(! $member, 403, 'Your member record could not be found. Contact the programme office.');

        return $member;
    }

    // ---- My work orders ---------------------------------------------------

    /** GET /api/work — the orders in this member's name, with what each is waiting on. */
    public function work(): JsonResponse
    {
        $member = $this->member();
        $orders = Execution::for($member);

        return response()->json([
            'is_ctp' => $member instanceof CtpRegistration,
            'statuses' => WorkOrder::STATUSES,
            'orders' => $orders->map(fn (WorkOrder $o) => $this->order($o))->values(),
            'waiting' => $orders->where('status', 'issued')->count(),
            'working' => $orders->whereIn('status', ['accepted', 'in_progress'])->count(),
            'questions' => $orders->where('status', 'disputed')->count(),
            'done' => $orders->whereIn('status', ['confirmed', 'closed', 'cancelled'])->count(),
        ]);
    }

    /** GET /api/work/{order} — one order as the worker's sheet: scope, materials, milestones, log and dispute. */
    public function show(WorkOrder $order): JsonResponse
    {
        $this->theirOrder($order);
        $order->load(['project.hirer', 'project.providedMaterials.item', 'milestones.signer',
            'updates.recorder', 'updates.fundi', 'disputes.mediator', 'disputes.decider']);

        $member = $this->member();
        $dispute = Execution::activeDispute($order);

        return response()->json([
            'order' => $this->order($order, true),
            'is_ctp' => $member instanceof CtpRegistration,
            'window' => WorkOrderDispute::WINDOW_HOURS,
            'next' => Execution::nextState($order),
            'percent' => Execution::rollUp($order),
            'payout' => ($payout = Execution::payoutFor($order)) ? [
                'reference' => $payout->reference,
                'stage' => $payout->stage,
                'stage_label' => $payout->stageLabel(),
                'net' => $payout->netLabel(),
                'source' => $payout->sourceLabel(),
            ] : null,
            'dispute' => $dispute ? $this->dispute($dispute) : null,
            'milestones' => $order->milestones->map(fn ($m) => [
                'id' => $m->id,
                'name' => $m->name,
                'share' => $m->share_percent,
                'amount' => $m->amountLabel(),
                'due_on' => $m->due_on?->toDateString(),
                'status' => $m->status,
                'status_label' => $m->statusLabel(),
                'signed_on' => $m->signed_on?->toDateString(),
                'signed_by' => $m->signer->name ?? null,
                'note' => $m->note,
            ])->values(),
            'materials' => $order->project->providedMaterials->map(fn ($m) => [
                'name' => $m->name,
                'quantity' => rtrim(rtrim(number_format((float) $m->quantity, 2, '.', ''), '0'), '.'),
                'unit' => $m->item->unit ?? null,
                'issued_on' => $m->supplied_on?->toDateString(),
                'value' => Finance::ksh((float) $m->quantity * (float) $m->unit_cost_ksh),
            ])->values(),
            'log' => $order->updates->map(fn (WorkOrderUpdate $u) => [
                'id' => $u->id,
                'kind' => $u->kindLabel(),
                'on' => $u->entry_date?->toDateString(),
                'percent' => $u->percent,
                'note' => $u->note,
                'by' => $u->fundi->full_name ?? ($u->recorder->name ?? 'The office'),
                'has_photo' => $u->hasPhoto(),
            ])->values(),
            'disputes' => $order->disputes->map(fn (WorkOrderDispute $d) => $this->dispute($d))->values(),
        ]);
    }

    /** POST /api/work/{order}/accept — §10.4 step 1: sign it and confirm the start day. */
    public function accept(Request $request, WorkOrder $order): JsonResponse
    {
        $this->theirOrder($order);
        Execution::accept($order, $request);

        return response()->json([
            'message' => $order->reference.' accepted and signed by '.$order->signature
                .', starting '.$order->start_on->format('d M Y')
                .'. Issue materials against it at the store, then log your days here.',
            'order' => $this->order($order->fresh()),
        ]);
    }

    /** POST /api/work/{order}/log — §10.4 step 3: a day on the job, with a photo of it. */
    public function log(Request $request, WorkOrder $order): JsonResponse
    {
        $this->theirOrder($order);
        $order->mustBe('accepted', 'in_progress', 'disputed');

        $this->liftUploads($request, ['photo']);
        $data = WorkOrderUpdate::dataFrom($request, ['progress']);

        $fundi = $order->assignee_type === 'fundi' ? FundiRegistration::find($order->assignee_id) : null;
        Execution::log($order, $data, $request, $fundi);

        $day = Carbon::parse($data['entry_date'])->format('d M Y');
        $percent = isset($data['percent']) ? ' — '.$data['percent'].'% complete' : '';

        return response()->json([
            'message' => 'Logged for '.$day.$percent.'. The office and the supervisor read this log.',
            'order' => $this->order($order->fresh()),
        ], 201);
    }

    /** POST /api/work/{order}/complete — §10.4 step 5: the work is done; the hirer inspects next. */
    public function complete(Request $request, WorkOrder $order): JsonResponse
    {
        $this->theirOrder($order);
        $this->liftUploads($request, ['photo']);
        Execution::requestCompletion($order, $request);

        return response()->json([
            'message' => 'Completion requested on '.$order->reference
                .'. The office arranges the hirer\'s inspection — if they raise an issue instead, you will see it here'
                .' and have '.WorkOrderDispute::WINDOW_HOURS.' hours to answer.',
            'order' => $this->order($order->fresh()),
        ]);
    }

    /** POST /api/work/{order}/dispute/{dispute}/answer — §10.6 step 2: the worker's side of it. */
    public function answer(Request $request, WorkOrder $order, WorkOrderDispute $dispute): JsonResponse
    {
        $this->theirOrder($order);
        abort_unless((int) $dispute->work_order_id === (int) $order->id, 403, 'That issue is not on '.$order->reference.'.');

        $member = $this->member();
        abort_unless($member instanceof FundiRegistration, 403,
            'An attachment order has no dispute answer box — the office settles it with the hirer and notes it on the trainee\'s record.');

        Execution::answerDispute($dispute, $request, $member);

        return response()->json([
            'message' => 'Your answer is on the dispute. The Programme Manager mediates and a Director decides (§10.6).',
            'dispute' => $this->dispute($dispute->fresh()),
        ]);
    }

    /** GET /api/work/{order}/update/{update}/photo */
    public function updatePhoto(WorkOrder $order, WorkOrderUpdate $update): JsonResponse
    {
        $this->theirOrder($order);
        abort_unless((int) $update->work_order_id === (int) $order->id, 403, 'That entry is not on your order.');

        return response()->json($this->inline($update->photo_path, $order->reference.'-'.$update->id));
    }

    /** GET /api/work/{order}/dispute/{dispute}/evidence — the file the hirer sent with their issue. */
    public function disputeEvidence(WorkOrder $order, WorkOrderDispute $dispute): JsonResponse
    {
        $this->theirOrder($order);
        abort_unless((int) $dispute->work_order_id === (int) $order->id, 403, 'That issue is not on your order.');

        return response()->json($this->inline($dispute->issue_path, $order->reference.'-issue'));
    }

    // ---- Items to put right ---------------------------------------------

    /** GET /api/fixes — the failed inspection items on this worker's own sites still waiting on proof. */
    public function fixes(): JsonResponse
    {
        $member = $this->member();

        return response()->json([
            'fixes' => Construction::fixesFor($member)->map(fn (SiteInspectionItem $i) => $this->fix($i))->values(),
            'cleared' => SiteInspectionItem::with(['inspection.project'])
                ->whereIn('inspection_id', $this->inspectionIds($member))
                ->where('result', 'fail')
                ->whereNotNull('rechecked_on')
                ->orderByDesc('rechecked_on')
                ->limit(10)->get()
                ->map(fn (SiteInspectionItem $i) => $this->fix($i, true))->values(),
        ]);
    }

    /** POST /api/fixes/{item}/proof — what was done, with the photograph the inspector re-checks. */
    public function proveFix(Request $request, SiteInspectionItem $item): JsonResponse
    {
        $member = $this->member();
        abort_unless(Construction::fixesFor($member)->firstWhere('id', $item->id) !== null, 404,
            'That item is not a failure waiting on you. It may already be re-checked, or it sits on somebody else\'s site.');

        $this->liftUploads($request, ['proof']);
        Construction::proveFix($item, $request, $member->id);

        return response()->json([
            'message' => 'Proof of the fix on "'.$item->item.'" filed for the inspector to re-check (§11.4 step 6).',
        ], 201);
    }

    /** GET /api/fixes/{item}/photo */
    public function fixPhoto(SiteInspectionItem $item): JsonResponse
    {
        $member = $this->member();
        abort_unless(
            SiteInspectionItem::where('id', $item->id)->whereIn('inspection_id', $this->inspectionIds($member))->exists(),
            404,
            'That photograph is not on your site.'
        );

        return response()->json($this->inline($item->proof_path, 'fix-'.$item->id));
    }

    // ---- My repair jobs --------------------------------------------------

    /** GET /api/tech — the technology tickets assigned to this fundi to fix (§12.7). */
    public function jobs(): JsonResponse
    {
        $member = $this->member();

        return response()->json([
            'statuses' => Technology::TICKET_STATUSES,
            'jobs' => Technology::jobsFor($member)->map(fn (TechnologyTicket $t) => [
                'id' => $t->id,
                'reference' => $t->reference,
                'title' => $t->title,
                'fault' => $t->fault,
                'priority' => $t->priorityLabel(),
                'status' => $t->status,
                'status_label' => $t->statusLabel(),
                'asset' => $t->asset ? $t->asset->name.' · '.$t->asset->asset_code : null,
                'resolution' => $t->resolution,
                'resolved_on' => $t->resolved_on?->toDateString(),
                'can_resolve' => in_array($t->status, ['assigned', 'in_progress'], true),
            ])->values(),
        ]);
    }

    /** POST /api/tech/{ticket}/resolve — what was done; the office confirms and closes it. */
    public function resolveJob(Request $request, TechnologyTicket $ticket): JsonResponse
    {
        $member = $this->member();
        abort_unless(Technology::jobsFor($member)->firstWhere('id', $ticket->id) !== null, 404,
            'That repair job is not one of yours.');

        Technology::resolveTicket($ticket, $request, $member->id);

        return response()->json([
            'message' => $ticket->reference.' recorded as fixed. The office confirms and closes it (§12.7).',
        ]);
    }

    // ---- Profile photo ---------------------------------------------------

    /** POST /api/me/photo — the member puts their own face up; the office sees the same picture. */
    public function storePhoto(Request $request): JsonResponse
    {
        $member = $this->member();
        $this->liftUploads($request, ['passport_photo']);

        $request->validate([
            'passport_photo' => 'required|image|mimes:jpg,jpeg,png|max:1024',
        ], [
            'passport_photo.required' => 'Choose the photo to put on your profile.',
            'passport_photo.image' => 'A profile photo is a picture — jpg or png.',
        ], ['passport_photo' => 'Profile photo']);

        $member->attachPhoto($request);

        return response()->json(['message' => 'Your profile photo is up. The programme office sees the same picture you do.']);
    }

    // ---- guards ----------------------------------------------------------

    private function theirOrder(WorkOrder $order): void
    {
        abort_unless($order->isHeldBy($this->member()), 404,
            'That work order is not in your name. The delivery desk keeps every order on the project it was awarded for.');
    }

    private function inspectionIds($member)
    {
        return SiteInspection::whereIn('work_order_id', WorkOrder::where(
            'assignee_type', $member instanceof CtpRegistration ? 'ctp' : 'fundi')
            ->where('assignee_id', $member->id)
            ->pluck('id'))->pluck('id');
    }

    // ---- serialisers -----------------------------------------------------

    private function order(WorkOrder $o, bool $detail = false): array
    {
        $base = [
            'id' => $o->id,
            'reference' => $o->reference,
            'project' => $o->project->title ?? null,
            'project_reference' => $o->project->reference ?? null,
            'hirer' => $o->project->hirer->name ?? null,
            'status' => $o->status,
            'status_label' => $o->statusLabel(),
            'percent' => (int) $o->percent_complete,
            'start_on' => $o->start_on?->toDateString(),
            'deadline' => $o->deadline?->toDateString(),
            'county' => $o->project->county ?? null,
            'next' => Execution::nextState($o),
            'can_accept' => $o->status === 'issued',
            'can_log' => in_array($o->status, ['accepted', 'in_progress', 'disputed'], true),
            'can_complete' => in_array($o->status, ['accepted', 'in_progress'], true),
        ];

        return $detail ? $base + [
            'scope' => $o->scope,
            'deliverables' => $o->deliverables,
            'schedule' => $o->scheduleLabel(),
            'split' => $o->splitLabel(),
            'agreed' => $o->agreedLabel(),
            'materials_note' => $o->materials_note,
            'materials_planned' => \App\Support\Finance::ksh($o->materialsPlannedKsh()),
            'materials_issued' => \App\Support\Finance::ksh($o->materialsIssuedKsh()),
            'signature' => $o->signature,
            'accepted_on' => $o->accepted_on?->toDateTimeString(),
            'milestones_outstanding' => $o->milestonesOutstanding(),
            'closure_note' => $o->closure_note,
            'confirmed_by' => $o->confirmed_by_name,
        ] : $base;
    }

    private function dispute(WorkOrderDispute $d): array
    {
        return [
            'id' => $d->id,
            'issue' => $d->issue,
            'raised_on' => $d->raised_on?->toDateString(),
            'time_label' => $d->timeLabel(),
            'awaiting_worker' => $d->isAwaitingWorker(),
            'has_evidence' => filled($d->issue_path),
            'response' => $d->response,
            'responded_on' => $d->responded_on?->toDateString(),
            'mediation' => $d->mediation_note,
            'outcome' => $d->outcomeLabel(),
            'outcome_note' => $d->outcome_note,
            'penalty' => $d->penaltyLabel(),
            'decided_on' => $d->decided_on?->toDateString(),
        ];
    }

    private function fix(SiteInspectionItem $i, bool $cleared = false): array
    {
        return [
            'id' => $i->id,
            'item' => $i->item,
            'result' => $i->resultLabel(),
            'note' => $i->note,
            'project' => $i->inspection->project->title ?? null,
            'order' => $i->inspection->workOrder->reference ?? null,
            'inspected_on' => $i->inspection->inspected_on?->toDateString(),
            'proof_note' => $i->proof_note,
            'proof_on' => $i->proof_on?->toDateString(),
            'has_proof' => $i->hasProof(),
            'rechecked_on' => $i->rechecked_on?->toDateString(),
            'cleared' => $cleared,
            'can_prove' => ! $cleared && $i->result === 'fail' && $i->rechecked_on === null,
        ];
    }
}
