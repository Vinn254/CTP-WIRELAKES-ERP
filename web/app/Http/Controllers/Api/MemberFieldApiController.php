<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Beneficiary;
use App\Models\BudgetLine;
use App\Models\Community;
use App\Models\County;
use App\Models\CtpRegistration;
use App\Models\EWasteCollection;
use App\Models\Enrollment;
use App\Models\FinanceEntry;
use App\Models\FundiRegistration;
use App\Models\InventoryItem;
use App\Models\ModuleCompletion;
use App\Models\Need;
use App\Models\Payout;
use App\Models\ProgressEntry;
use App\Models\Project;
use App\Models\ProjectApplication;
use App\Models\PurchaseRequest;
use App\Models\Trainer;
use App\Models\Training;
use App\Models\TrainingDocument;
use App\Models\TrainingModule;
use App\Models\TrainingVenue;
use App\Support\Finance;
use App\Support\MemberPortal;
use App\Support\TrainingTrack;
use App\Support\UploadsFromJson;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The member's field screens over the API — My communities, My mobilisation, E-Waste, Purchase request,
 * My earnings and Training CTPs. Each method is the web portal action of the same name: the member is resolved
 * from the token, the same model validators and the same support classes write, so the app and the website
 * cannot drift apart.
 */
class MemberFieldApiController extends Controller
{
    use UploadsFromJson;

    private function member()
    {
        $member = MemberPortal::resolve(auth()->user());
        abort_if(! $member, 403, 'Your member record could not be found. Contact the programme office.');

        return $member;
    }

    private function ctp(): CtpRegistration
    {
        $member = $this->member();
        abort_unless($member instanceof CtpRegistration, 403, 'That part of the portal is for CTPs.');

        return $member;
    }

    private function fundi(): FundiRegistration
    {
        $member = $this->member();
        abort_unless($member instanceof FundiRegistration, 403, 'That part of the portal is for E-Fundis.');

        return $member;
    }

    /** The trainer surface belongs to a senior fundi only, exactly as the web portal refuses every other grade. */
    private function teachingFundi(): FundiRegistration
    {
        $fundi = $this->fundi();
        abort_unless($fundi->canTrain(), 403,
            'Only a senior fundi trains CTPs. You are registered as '.FundiRegistration::tierLabel($fundi->tier)
            .' — the programme office moves a fundi up to senior grade when they are ready to carry a cohort.');

        return $fundi;
    }

    // ---- My communities (CTP) -------------------------------------------

    /** GET /api/communities — the areas this CTP mobilises, what they logged and what is still open. */
    public function communities(): JsonResponse
    {
        $ctp = $this->ctp();

        return response()->json([
            'need_types' => Need::TYPES,
            'beneficiary_types' => Beneficiary::TYPES,
            'counties' => County::orderBy('name')->pluck('name'),
            'communities' => MemberPortal::communities($ctp)->map(fn (Community $c) => $this->community($c, $ctp))->values(),
            'recent' => $ctp->needsRecorded()->with('community:id,name')->latest('id')->limit(15)->get()
                ->map(fn (Need $n) => $this->need($n))->values(),
        ]);
    }

    /** POST /api/communities — put an area forward; it lands as a proposal for the office. */
    public function storeCommunity(Request $request): JsonResponse
    {
        $ctp = $this->ctp();
        abort_unless(in_array($ctp->status, CtpRegistration::WORKING, true), 422,
            'Only an active, graduated or deployed CTP can put a community forward. Your status is '.$ctp->statusLabel().'.');

        $community = Community::create([
            ...Community::placeData($request),
            'status' => 'pending_review',
            'created_by_ctp' => $ctp->id,
        ]);

        return response()->json([
            'message' => $community->name.' sent to the programme office — they confirm it and issue its COM ID. '
                .'You can already log its needs.',
            'community' => $this->community($community->load('ctps'), $ctp),
        ], 201);
    }

    /** POST /api/communities/need — what they saw, with an optional photo and a pin. */
    public function storeNeed(Request $request): JsonResponse
    {
        $ctp = $this->ctp();
        $community = $this->theirCommunity($request, $ctp);

        $this->liftUploads($request, ['photo']);

        $data = $request->validate([
            'type' => 'required|in:'.implode(',', array_keys(Need::TYPES)),
            'title' => 'required|string|max:160',
            'detail' => 'nullable|string|max:2000',
            'gps_lat' => 'nullable|numeric|between:-90,90',
            'gps_lng' => 'nullable|numeric|between:-180,180',
            'photo' => 'nullable|image|mimes:jpg,jpeg,png|max:3072',
        ], [], [
            'gps_lat' => 'GPS latitude', 'gps_lng' => 'GPS longitude', 'title' => 'What is needed',
        ]);

        $need = Need::create([
            ...collect($data)->except('photo')->all(),
            'community_id' => $community->id,
            'assessed_by_ctp' => $ctp->id,
            'status' => 'proposed',
        ]);

        if ($request->hasFile('photo')) {
            $need->update(['photo_path' => $request->file('photo')->store('needs/'.$community->id, 'local')]);
        }

        return response()->json([
            'message' => '"'.$need->title.'" logged for '.$community->name.' — the programme office reviews it next.',
            'need' => $this->need($need->load('community:id,name')),
        ], 201);
    }

    /** POST /api/communities/beneficiary — only once the office has confirmed the area. */
    public function storeBeneficiary(Request $request): JsonResponse
    {
        $ctp = $this->ctp();
        $community = $this->theirCommunity($request, $ctp);

        abort_unless($community->status === 'active', 422,
            $community->name.' is not confirmed yet — beneficiaries are counted once the programme office confirms the community.');

        $beneficiary = Beneficiary::create([
            ...Beneficiary::dataFrom($request),
            'community_id' => $community->id,
            'created_by_ctp' => $ctp->id,
        ]);

        return response()->json([
            'message' => $beneficiary->name.' recorded against '.$community->name.'.',
            'beneficiary' => $this->beneficiary($beneficiary),
        ], 201);
    }

    // ---- My mobilisation (CTP) ------------------------------------------

    /** GET /api/mobilisation — the referral code, the credit for people brought, and the areas. */
    public function mobilisation(): JsonResponse
    {
        $ctp = $this->ctp();
        $term = CtpRegistration::currentTerm();
        $thisTerm = $ctp->peopleBroughtThisTerm();
        $next = $ctp->nextMilestone() ?? 30;

        return response()->json([
            'code' => $ctp->ctp_code ?: null,
            'link' => $ctp->referralLink(),
            'term' => $term['label'],
            'this_term' => $thisTerm,
            'lifetime' => $ctp->peopleBrought(),
            'next' => $next,
            'percent' => $next ? min(100, (int) round($thisTerm / $next * 100)) : 0,
            'reward' => $thisTerm >= 15 ? $ctp->discountLabel() : null,
            'hint' => $thisTerm >= 15
                ? null
                : max(0, 15 - $thisTerm).' more to unlock 50% off this term’s fee, '
                    .max(0, 30 - $thisTerm).' more for the full sponsorship credit.',
            'brought' => $ctp->broughtList()->map(fn (array $p) => [
                'name' => $p['name'],
                'code' => $p['code'],
                'kind' => $p['kind'],
                'label' => $p['label'],
                'place' => $p['place'],
                'on' => $p['on']?->toDateString(),
            ])->values(),
        ]);
    }

    // ---- E-Waste collection (both) --------------------------------------

    /** GET /api/ewaste — what this member registered, and (for an E-Fundi) what waits to be carried. */
    public function ewaste(): JsonResponse
    {
        $member = $this->member();
        $isCtp = $member instanceof CtpRegistration;

        $mine = EWasteCollection::ordered()
            ->where($isCtp ? 'ctp_id' : 'fundi_id', $member->id)
            ->with(['community:id,name', 'forwarder:id,full_name,ef_code', 'receiver:id,name'])
            ->get();

        $waiting = $isCtp ? collect() : $this->waitingFor($member);

        return response()->json([
            'is_ctp' => $isCtp,
            'item_types' => EWasteCollection::ITEM_TYPES,
            'conditions' => EWasteCollection::CONDITIONS,
            'statuses' => EWasteCollection::STATUSES,
            'counties' => County::orderBy('name')->pluck('name'),
            'communities' => Community::orderBy('name')->get(['id', 'name', 'county']),
            'mine' => $mine->map(fn (EWasteCollection $c) => $this->collection($c, ! $isCtp))->values(),
            'waiting' => $waiting->map(fn (EWasteCollection $c) => $this->collection($c, true))->values(),
            'totals' => [
                'lines' => $mine->count(),
                'items' => (int) $mine->sum('quantity'),
                'estimated' => round((float) $mine->sum('estimated_weight_kg'), 1),
                'weighed' => round((float) $mine->sum('weighed_weight_kg'), 1),
                'waiting' => $waiting->count(),
            ],
        ]);
    }

    /** POST /api/ewaste — register what was picked up, with the photo the collection point sorts from. */
    public function storeEwaste(Request $request): JsonResponse
    {
        $member = $this->member();
        $isCtp = $member instanceof CtpRegistration;

        $this->liftUploads($request, ['photo']);
        $data = EWasteCollection::dataFrom($request);

        $collection = EWasteCollection::create([
            ...$data,
            'reference' => EWasteCollection::nextReference(),
            'status' => 'submitted',
            'ctp_id' => $isCtp ? $member->id : null,
            'fundi_id' => $isCtp ? null : $member->id,
        ]);
        $collection->storePhoto($request);

        return response()->json([
            'message' => $collection->reference.' registered — '.$collection->quantity
                .' × '.$collection->itemTypeLabel().', '.$collection->conditionLabel().'. '
                .($isCtp
                    ? 'An E-Fundi or the office takes it to a collection point, where it is weighed in.'
                    : 'Weigh it in at the collection point and the office records the actual weight.'),
            'collection' => $this->collection($collection, ! $isCtp),
        ], 201);
    }

    /** POST /api/ewaste/{collection}/forward — an E-Fundi carries a CTP's line to a collection point. */
    public function forwardEwaste(Request $request, EWasteCollection $collection): JsonResponse
    {
        $fundi = $this->fundi();

        abort_if($collection->status !== 'submitted', 422,
            $collection->reference.' is already '.$collection->statusLabel().' — the office holds that part of the record.');

        $data = $request->validate([
            'condition' => 'required|in:'.implode(',', array_keys(EWasteCollection::CONDITIONS)),
            'note' => 'nullable|string|max:1000',
        ], [], ['condition' => 'Condition', 'note' => 'What you saw']);

        $collection->update([
            'status' => 'forwarded',
            'condition' => $data['condition'],
            'forwarded_by_fundi' => $fundi->id,
            'forwarded_on' => now(),
            'forward_note' => $data['note'] ?? null,
        ]);

        return response()->json([
            'message' => $collection->reference.' forwarded to the programme office as '
                .EWasteCollection::CONDITIONS[$data['condition']].'. They weigh it in and route it to repair, '
                .'refurbishment, recycling or disposal.',
            'collection' => $this->collection($collection->fresh(), true),
        ]);
    }

    /** GET /api/ewaste/{collection}/photo — only the line's own registrar or carrier opens it. */
    public function ewastePhoto(EWasteCollection $collection): JsonResponse
    {
        $member = $this->member();

        $theirLine = $member instanceof CtpRegistration
            ? (int) $collection->ctp_id === (int) $member->id
            : in_array($member->id, array_filter([$collection->fundi_id, $collection->forwarded_by_fundi]), true);

        abort_unless($theirLine, 404);

        return response()->json($this->inline($collection->photo_path, $collection->reference));
    }

    // ---- Purchase request (E-Fundi only) ---------------------------------

    /** GET /api/procurement — the E-Fundi's own requests and how far each has travelled on the §14.4 path. */
    public function procurement(): JsonResponse
    {
        $member = $this->member();
        PurchaseRequest::assertMemberMayRaise($member);

        $mine = PurchaseRequest::ordered()
            ->where('fundi_id', $member->id)
            ->with(['project:id,title', 'cohort:id,reference', 'item:id,sku,name,unit,qty_on_hand',
                'orders:id,purchase_request_id,reference,status,amount_ksh',
                'quotations:id,purchase_request_id,supplier_id,amount_ksh', 'quotations.supplier:id,name'])
            ->get();

        return response()->json([
            'units' => InventoryItem::UNITS,
            'categories' => BudgetLine::CATEGORIES,
            'statuses' => PurchaseRequest::STATUSES,
            'path' => PurchaseRequest::PATH,
            'mine' => $mine->map(fn (PurchaseRequest $p) => $this->request($p))->values(),
            'awarded' => Project::whereHas('applications', fn ($q) => $q
                ->where('applicant_type', MemberPortal::applicantType($member))
                ->where('applicant_id', $member->id)->where('status', 'approved'))
                ->orderByDesc('id')->get(['id', 'title', 'reference', 'status'])
                ->map(fn (Project $p) => ['id' => $p->id, 'title' => $p->title, 'reference' => $p->reference])->values(),
            'totals' => [
                'lines' => $mine->count(),
                'waiting' => $mine->whereIn('status', ['submitted', 'approved'])->count(),
                'moving' => $mine->whereIn('status', ['quoted', 'ordered', 'received', 'matched'])->count(),
                'filled' => $mine->where('status', 'paid')->count(),
            ],
        ]);
    }

    /** POST /api/procurement — an E-Fundi raises a request; the Programme Manager decides it at the office. */
    public function storeProcurement(Request $request): JsonResponse
    {
        $member = $this->member();
        PurchaseRequest::assertMemberMayRaise($member);

        $data = PurchaseRequest::dataFrom($request);

        if (! empty($data['project_id'])) {
            $theirs = ProjectApplication::where('project_id', $data['project_id'])
                ->where('applicant_type', MemberPortal::applicantType($member))
                ->where('applicant_id', $member->id)->exists();
            abort_unless($theirs, 403,
                'You can only charge a purchase to a project you were awarded or applied for — otherwise raise it as a general programme need.');
        }

        $pr = PurchaseRequest::create([
            ...$data,
            'reference' => PurchaseRequest::nextReference(),
            'county' => $data['county'] ?? $member->county,
            'raised_by_user' => $member->user_id,
            'fundi_id' => $member->id,
            'status' => 'submitted',
        ]);

        return response()->json([
            'message' => $pr->reference.' is raised for '.$pr->item_name.' ('.$pr->quantityLabel()
                .'), charged to '.$pr->budgetLabel()
                .'. The Programme Manager decides first (§14.4 step 2) — you will see the answer on this screen.',
            'request' => $this->request($pr->fresh()),
        ], 201);
    }

    // ---- My earnings (E-Fundi) ------------------------------------------

    /** GET /api/earnings — the two income streams kept apart, read-only (§14.6). */
    public function earnings(): JsonResponse
    {
        $fundi = $this->fundi();

        $payouts = Payout::with(['project:id,title', 'cohort:id,reference', 'cohort.training:id,name',
            'trainer.fundi:id,full_name', 'approver:id,name', 'releaser:id,name'])
            ->where('fundi_id', $fundi->id)
            ->orderByRaw("case stage when 'released' then 0 when 'approved' then 1 when 'queued' then 2
                when 'held' then 3 when 'awaiting_hirer_payment' then 4 when 'awaiting_report' then 4 else 5 end")
            ->orderByDesc('id')->get();

        return response()->json([
            'is_trainer' => $fundi->canTrain(),
            'stages' => Payout::STAGES,
            'payouts' => $payouts->map(fn (Payout $p) => $this->payout($p))->values(),
            'ledger' => FinanceEntry::where('fundi_id', $fundi->id)
                ->orderByDesc('happened_on')->limit(25)->get()
                ->map(fn (FinanceEntry $e) => [
                    'id' => $e->id,
                    'date' => $e->happened_on?->toDateString(),
                    'label' => $e->title ?: $e->kindLabel(),
                    'kind' => $e->kindLabel(),
                    'direction' => $e->direction,
                    'amount' => Finance::ksh((float) $e->amount_ksh),
                ])->values(),
            'totals' => [
                'project' => Finance::ksh((float) $payouts->where('stream', 'project')->where('stage', 'released')->sum('net_ksh')),
                'training' => Finance::ksh((float) $payouts->where('stream', 'training')->where('stage', 'released')->sum('net_ksh')),
                'waiting' => Finance::ksh((float) $payouts->whereNotIn('stage', ['released', 'rejected'])->sum('net_ksh')),
                'lines' => $payouts->count(),
            ],
        ]);
    }

    // ---- Training CTPs (senior E-Fundi) ---------------------------------

    /** GET /api/trainer — the trainer's own sites, the cohorts they carry and every trainee on the roll. */
    public function trainer(): JsonResponse
    {
        $fundi = $this->fundi();

        $trainers = Trainer::with([
            'trainings:id,name',
            'venue:id,name,type,county,sub_county,ward',
            'cohorts' => fn ($q) => $q->where('status', '!=', 'draft')
                ->with(['training.modules', 'enrollments' => fn ($e) => $e->with([
                    'ctp:id,full_name,ctp_code', 'entries', 'moduleMarks', 'documents', 'certification',
                ])->orderBy('id')])
                ->orderByDesc('starts_on'),
        ])->where('fundi_id', $fundi->id)->orderBy('id')->get();

        return response()->json([
            'can_train' => $fundi->canTrain(),
            'kinds' => ProgressEntry::KINDS,
            'document_kinds' => TrainingDocument::KINDS,
            'trainings' => Training::where('status', 'active')->orderBy('name')->get(['id', 'name', 'code'])
                ->map(fn (Training $t) => ['id' => $t->id, 'name' => $t->name, 'code' => $t->code])->values(),
            'venues' => TrainingVenue::where('status', 'active')->orderBy('county')->orderBy('name')->get(['id', 'name', 'county'])
                ->map(fn (TrainingVenue $v) => ['id' => $v->id, 'name' => $v->name, 'county' => $v->county])->values(),
            'counties' => County::orderBy('name')->pluck('name'),
            'sites' => $trainers->map(fn (Trainer $t) => $this->site($t))->values(),
        ]);
    }

    /** POST /api/trainer/apply — ask to train at ONE site; the office approves site by site. */
    public function applyTrainer(Request $request): JsonResponse
    {
        $fundi = $this->teachingFundi();

        $request->merge(['fundi_id' => $fundi->id]);
        $data = Trainer::dataFrom($request);

        $held = Trainer::forSite($fundi->id, (int) $data['venue_id']);
        abort_if($held && in_array($held->status, ['applied', 'approved'], true), 422,
            'You are already '.$held->statusLabel().' at '.$held->venue->name.' — the programme office holds that record.');

        $columns = $data;
        unset($columns['trainings']);

        if ($held) {
            $held->update($columns + ['status' => 'applied', 'reviewed_by' => null]);
            $held->trainings()->sync($data['trainings']);
            $trainer = $held;
        } else {
            $trainer = Trainer::create($columns + ['status' => 'applied']);
            $trainer->trainings()->sync($data['trainings']);
        }

        return response()->json([
            'message' => 'Sent to the programme office for '.$trainer->venue()->value('name').'. '
                .'They approve a trainer site by site before handing them a cohort.',
        ], 201);
    }

    /** POST /api/trainer/progress — a mark on the trainer's own trainee. */
    public function markProgress(Request $request): JsonResponse
    {
        $enrollment = $this->theirTrainee($request);
        $message = TrainingTrack::recordProgress($enrollment, $request, ['recorded_by_fundi' => $this->fundi()->id]);

        return response()->json(['message' => $message, 'enrollment' => $this->trainee($enrollment->fresh())]);
    }

    /** POST /api/trainer/{enrollment}/modules/{module} */
    public function markModule(Request $request, Enrollment $enrollment, TrainingModule $module): JsonResponse
    {
        $their = $this->theirTrainee($enrollment);
        $message = TrainingTrack::markModule(
            $their, $module, ModuleCompletion::dataFrom($request), ['recorded_by_fundi' => $this->fundi()->id]);

        return response()->json(['message' => $message, 'enrollment' => $this->trainee($their->fresh())]);
    }

    /** DELETE /api/trainer/{enrollment}/modules/{module} */
    public function unmarkModule(Enrollment $enrollment, TrainingModule $module): JsonResponse
    {
        $their = $this->theirTrainee($enrollment);

        return response()->json([
            'message' => TrainingTrack::unmarkModule($their, $module),
            'enrollment' => $this->trainee($their->fresh()),
        ]);
    }

    /** POST /api/trainer/{enrollment}/documents — the evidence goes on the record too. */
    public function storeDocument(Request $request, Enrollment $enrollment): JsonResponse
    {
        $their = $this->theirTrainee($enrollment);
        $this->liftUploads($request, ['file']);

        return response()->json([
            'message' => TrainingTrack::storeDocument(
                $their, $request, ['uploaded_by_fundi' => $this->fundi()->id]),
        ], 201);
    }

    // ---- guards ----------------------------------------------------------

    private function theirCommunity(Request $request, CtpRegistration $ctp): Community
    {
        $request->validate(['community_id' => 'required|integer']);

        return Community::forCtp($ctp)->findOrFail($request->input('community_id'));
    }

    private function theirTrainee(Request|Enrollment $request): Enrollment
    {
        $fundi = $this->teachingFundi();
        $myTrainerIds = Trainer::approved()->where('fundi_id', $fundi->id)->pluck('id');

        abort_unless($myTrainerIds->isNotEmpty(), 403, 'Only an approved trainer can record a trainee.');

        $enrollment = $request instanceof Enrollment
            ? $request->load(['cohort', 'ctp'])
            : (function (Request $r) {
                $r->validate(['enrollment_id' => 'required|integer']);

                return Enrollment::with(['cohort', 'ctp'])->findOrFail($r->input('enrollment_id'));
            })($request);

        abort_unless($enrollment->cohort && $myTrainerIds->contains($enrollment->cohort->trainer_id), 404);

        return $enrollment;
    }

    private function waitingFor(FundiRegistration $fundi)
    {
        return EWasteCollection::ordered()
            ->where('status', 'submitted')->whereNull('fundi_id')
            ->where(fn ($q) => $q->where('county', $fundi->county)->orWhereNull('county'))
            ->where(fn ($q) => $q->where('ctp_id', '!=', $fundi->id)->orWhereNull('ctp_id'))
            ->with(['ctp:id,full_name,ctp_code', 'community:id,name'])
            ->get();
    }

    // ---- serialisers -----------------------------------------------------

    private function community(Community $c, CtpRegistration $ctp): array
    {
        $mine = $c->ctps->firstWhere('id', $ctp->id);

        return [
            'id' => $c->id,
            'name' => $c->name,
            'code' => $c->community_code,
            'location' => $c->locationLabel(),
            'status' => $c->status,
            'status_label' => $c->statusLabel(),
            'role' => $mine && $mine->pivot->role === 'lead' ? 'Lead CTP' : 'Mobilizer',
            'ctps' => $c->ctps->count(),
            'population' => $c->population,
            'households' => $c->households,
            'needs' => $c->needs->take(8)->map(fn (Need $n) => $this->need($n))->values(),
            'beneficiaries' => $c->beneficiaries->take(8)->map(fn (Beneficiary $b) => $this->beneficiary($b))->values(),
        ];
    }

    private function need(Need $n): array
    {
        return [
            'id' => $n->id,
            'title' => $n->title,
            'type' => $n->type,
            'type_label' => Need::TYPES[$n->type] ?? $n->type,
            'detail' => $n->detail,
            'status' => $n->status,
            'status_label' => $n->statusLabel(),
            'community' => $n->community->name ?? null,
            'has_photo' => filled($n->photo_path),
            'on' => $n->created_at?->toDateString(),
        ];
    }

    private function beneficiary(Beneficiary $b): array
    {
        return [
            'id' => $b->id,
            'name' => $b->name,
            'type' => $b->type,
            'type_label' => Beneficiary::TYPES[$b->type] ?? $b->type,
            'community' => $b->community->name ?? null,
            'project' => $b->project->title ?? null,
            'on' => $b->benefited_on?->toDateString(),
        ];
    }

    private function collection(EWasteCollection $c, bool $isFundi): array
    {
        return [
            'id' => $c->id,
            'reference' => $c->reference,
            'item' => $c->itemTypeLabel(),
            'quantity' => $c->quantity,
            'condition' => $c->conditionLabel(),
            'status' => $c->status,
            'status_label' => $c->statusLabel(),
            'collected_on' => $c->collected_on?->toDateString(),
            'place' => $c->placeLabel(),
            'community' => $c->community->name ?? null,
            'weight' => $c->weightLabel(),
            'collector' => $c->collectorLabel(),
            'note' => $c->note,
            'forward_note' => $c->forward_note,
            'has_photo' => filled($c->photo_path),
            // The forward box only sits on a CTP's line that an E-Fundi has not yet carried.
            'can_forward' => $c->status === 'submitted' && $c->ctp_id !== null && $isFundi,
        ];
    }

    private function request(PurchaseRequest $p): array
    {
        return [
            'id' => $p->id,
            'reference' => $p->reference,
            'item_name' => $p->item_name,
            'quantity' => $p->quantityLabel(),
            'budget' => $p->budgetLabel(),
            'charge' => $p->chargeLabel(),
            'cost' => $p->costLabel(),
            'status' => $p->status,
            'status_label' => $p->statusLabel(),
            'step' => $p->stepIndex(),
            'closed' => $p->isClosed(),
            'needed_by' => $p->needed_by?->toDateString(),
            'decision' => $p->decision_note ?: $p->rejection_reason,
            'justification' => $p->justification,
            'raised_on' => $p->created_at?->toDateString(),
        ];
    }

    private function payout(Payout $p): array
    {
        return [
            'id' => $p->id,
            'reference' => $p->reference,
            'stream' => $p->stream,
            'stream_label' => $p->streamLabel(),
            'stage' => $p->stage,
            'stage_label' => $p->stageLabel(),
            'source' => $p->sourceLabel(),
            'gross' => $p->grossLabel(),
            'net' => $p->netLabel(),
            'calculation' => $p->calculation,
            'method' => $p->methodLabel(),
            'released_on' => $p->released_on?->toDateString(),
            'step' => $p->stepIndex(),
            'path' => collect($p->path())->map(fn ($s) => $s['action'])->values(),
        ];
    }

    private function site(Trainer $t): array
    {
        return [
            'id' => $t->id,
            'venue' => $t->venue?->name,
            'place' => $t->venue?->placeLabel() ?: $t->county,
            'status' => $t->status,
            'status_label' => Trainer::STATUSES[$t->status] ?? $t->status,
            'trainings' => $t->trainings->pluck('name')->values(),
            'cohorts' => $t->cohorts->map(fn ($c) => [
                'id' => $c->id,
                'reference' => $c->reference,
                'course' => $c->courseLabel(),
                'period' => $c->periodLabel(),
                'dates' => $c->dateLabel(),
                'status' => $c->status,
                'trainees' => $c->enrollments->map(fn (Enrollment $e) => $this->trainee($e))->values(),
            ])->values(),
        ];
    }

    private function trainee(Enrollment $e): array
    {
        $board = $e->moduleBoard();

        return [
            'id' => $e->id,
            'ctp' => $e->ctp?->full_name,
            'ctp_code' => $e->ctp?->ctp_code,
            'state' => $e->state,
            'state_label' => $e->stateLabel(),
            'stage' => $e->stage,
            'stage_label' => $e->stageLabel(),
            'track_pct' => $e->trackPct(),
            'attendance_pct' => $e->attendancePct(),
            'modules_marked' => $board['marked']->count(),
            'modules_total' => $board['modules']->count(),
            'modules' => $board['modules']->map(fn (TrainingModule $m) => [
                'id' => $m->id,
                'title' => $m->title,
                'marked' => $board['marked']->has($m->id),
            ])->values(),
            'documents' => $e->documents->take(6)->map(fn (TrainingDocument $d) => [
                'id' => $d->id,
                'title' => $d->title,
                'kind' => $d->kindLabel(),
                'on' => $d->dated_on?->toDateString(),
            ])->values(),
            'entries' => $e->entries->take(8)->map(fn (ProgressEntry $p) => [
                'id' => $p->id,
                'kind' => ProgressEntry::KINDS[$p->kind] ?? $p->kind,
                'on' => $p->entry_date?->toDateString(),
                'note' => $p->note,
                'score' => $p->score,
                'present' => $p->present,
            ])->values(),
        ];
    }
}
