<?php

namespace App\Http\Controllers;

use App\Models\Beneficiary;
use App\Models\Cohort;
use App\Models\Community;
use App\Models\County;
use App\Models\CtpRegistration;
use App\Models\EWasteCollection;
use App\Models\Enrollment;
use App\Models\FundiRegistration;
use App\Models\ModuleCompletion;
use App\Models\Need;
use App\Models\ProgressEntry;
use App\Models\Project;
use App\Models\ProjectApplication;
use App\Models\Trainer;
use App\Models\Training;
use App\Models\TrainingDocument;
use App\Models\TrainingModule;
use App\Models\TrainingVenue;
use App\Support\MemberPortal;
use App\Support\TrainingTrack;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class PortalController extends Controller
{
    /** The member record (CTP or E-Fundi) behind the signed-in account. */
    private function member()
    {
        return MemberPortal::resolve(auth()->user());
    }

    /** Sections only a CTP has — an E-Fundi is refused here, and their sidebar never offers them. */
    private function ctp(): CtpRegistration
    {
        $member = $this->member();
        abort_if(! $member, 403, 'Your member record could not be found. Contact the programme office.');
        abort_unless($member instanceof CtpRegistration, 403, 'That part of the portal is for CTPs.');

        return $member;
    }

    public function dashboard()
    {
        $member = $this->member();
        abort_if(! $member, 403, 'Your member record could not be found. Contact the programme office.');

        return view('portal.dashboard', MemberPortal::board($member));
    }

    /** Sections only an E-Fundi has — a CTP is refused here, and their sidebar never offers them. */
    private function fundi(): FundiRegistration
    {
        $member = $this->member();
        abort_if(! $member, 403, 'Your member record could not be found. Contact the programme office.');
        abort_unless($member instanceof FundiRegistration, 403, 'That part of the portal is for E-Fundis.');

        return $member;
    }

    /** The trainer surface belongs to a senior fundi only. Their trainee routes are closed to every other grade. */
    private function teachingFundi(): FundiRegistration
    {
        $fundi = $this->fundi();
        abort_unless($fundi->canTrain(), 403,
            'Only a senior fundi trains CTPs. You are registered as '.FundiRegistration::tierLabel($fundi->tier)
            .' — you work under a senior fundi on a job, and the programme office moves you up when you are ready to carry a cohort.');

        return $fundi;
    }

    public function training()
    {
        $ctp = $this->ctp();

        return view('portal.training', MemberPortal::board($ctp) + MemberPortal::training($ctp) + [
            'stages' => Enrollment::STAGES,
            'states' => Enrollment::STATES,
        ]);
    }

    /**
     * The E-Fundi's own record (§8): the grade the office holds, the skills and counties they keep
     * themselves, their availability, the sites they train at and the paperwork on file.
     */
    public function efundi()
    {
        $fundi = $this->fundi();

        return view('portal.efundi', MemberPortal::board($fundi) + [
            'sites' => $fundi->trainingSites(),
            'docs' => $fundi->documents(),
            'counties' => County::orderBy('name')->pluck('name'),
            'specialisations' => FundiRegistration::SPECIALISATIONS,
            'availabilities' => FundiRegistration::AVAILABILITY,
            'tiers' => FundiRegistration::TIERS,
        ]);
    }

    /** The paperwork on their own record. The slot comes from the record, never from the url alone. */
    public function efundiFile(string $slot)
    {
        $fundi = $this->fundi();
        $docs = $fundi->documents();
        abort_unless(isset($docs[$slot]), 404, 'That file is not on your record.');

        return Storage::disk('local')->response($docs[$slot]['path'], $docs[$slot]['label']);
    }

    /** Availability is the fundi's own call — the office can correct it, but nobody else sets it day to day. */
    public function saveAvailability(Request $request)
    {
        $fundi = $this->fundi();
        $fundi->update(FundiRegistration::availabilityFrom($request));

        return back()->with('status', 'You are showing as '.$fundi->availabilityLabel().'.'
            .($fundi->availability === 'available' ? ' The programme office and matching now see you in the work pool.' : ''));
    }

    /** The fundi keeps their own skills and the counties they will travel to. Both stay on the programme's list. */
    public function saveSkills(Request $request)
    {
        $fundi = $this->fundi();
        $fundi->update(FundiRegistration::skillsFrom($request));

        return back()->with('status', 'Your skills are updated. The office reviews the record when a job is matched to you.');
    }

    /** A CTP puts themselves forward for an open intake. The office confirms the seat and the placement. */
    public function applyCohort(Request $request)
    {
        $ctp = $this->ctp();

        $data = $request->validate([
            'cohort_id' => 'required|integer|exists:cohorts,id',
            'note' => 'nullable|string|max:1000',
        ], [], ['cohort_id' => 'Training']);

        $cohort = Cohort::findOrFail($data['cohort_id']);

        abort_unless(in_array($ctp->status, CtpRegistration::WORKING, true), 422,
            'Training seats go to a CTP once they are active — your status is '.$ctp->statusLabel().'.');

        abort_unless($cohort->isOpenForEnrol(), 422,
            $cohort->reference.' is not open for applications: '.$cohort->enrolClosedReason().'.');

        $existing = $cohort->enrollments()->where('ctp_id', $ctp->id)->first();

        abort_if($existing && in_array($existing->state, ['pending', 'approved'], true), 422,
            'You have already put yourself forward for '.$cohort->reference.'.');

        $message = $existing
            ? 'Your application for '.$cohort->reference.' is back with the programme office.'
            : 'Applied for '.$cohort->reference.' — the programme office confirms the seat and tells you where you are placed.';

        if ($existing) {
            // One record per CTP per cohort: applying again after a decline reopens it for the office to decide.
            $existing->update([
                'state' => 'pending',
                'source' => 'self',
                'note' => $data['note'] ?? $existing->note,
                'reviewed_by' => null,
                'approved_on' => null,
            ]);
        } else {
            Enrollment::create([
                'cohort_id' => $cohort->id,
                'ctp_id' => $ctp->id,
                'state' => 'pending',
                'source' => 'self',
                'note' => $data['note'] ?? null,
            ]);
        }

        return back()->with('status', $message);
    }

    public function withdrawSeat(Enrollment $enrollment)
    {
        $ctp = $this->ctp();
        abort_unless($enrollment->ctp_id === $ctp->id, 404);
        abort_unless(in_array($enrollment->state, ['pending', 'approved'], true), 422,
            'That seat is already '.$enrollment->stateLabel().'.');
        abort_unless(! in_array($enrollment->stage, ['completed', 'failed'], true), 422,
            'You have already completed '.$enrollment->cohort->reference.' — the record stays on your file.');

        $enrollment->update(['state' => 'withdrawn']);

        return back()->with('status', 'Withdrawn from '.$enrollment->cohort->reference.'.');
    }

    /** The E-Fundi's trainer surface: the sites they are approved at, the cohorts they teach and the marks they put in. */
    public function trainer()
    {
        $fundi = $this->fundi();

        return view('portal.trainer', [
            'member' => $fundi,
            'trainers' => Trainer::with([
                'trainings:id,name',
                'venue:id,name,type,county,sub_county,ward',
                'cohorts' => fn ($q) => $q->where('status', '!=', 'draft')
                    ->with([
                        'training.modules',
                        'enrollments' => fn ($e) => $e->with([
                            'ctp:id,full_name,ctp_code', 'entries', 'moduleMarks', 'documents', 'certification',
                        ])->orderBy('id'),
                    ])
                    ->orderByDesc('starts_on'),
            ])->where('fundi_id', $fundi->id)->orderBy('id')->get(),
            'trainings' => Training::where('status', 'active')->orderBy('name')->get(['id', 'name', 'code']),
            'venues' => TrainingVenue::where('status', 'active')->orderBy('county')->orderBy('name')->get(['id', 'name', 'county']),
            'counties' => County::orderBy('name')->pluck('name'),
            'kinds' => ProgressEntry::KINDS,
            'documentKinds' => TrainingDocument::KINDS,
        ]);
    }

    /**
     * One trainee on the trainer's own roll — the per-student record page the roster opens.
     * Anyone outside a cohort this trainer teaches 404s, so no roster is leaked.
     */
    public function trainee(Enrollment $enrollment)
    {
        $their = $this->theirTrainee($enrollment);

        $their->load([
            'ctp', 'cohort.training.modules', 'cohort.venue', 'entries', 'moduleMarks', 'documents', 'certification',
        ]);

        return view('portal.trainer-trainee', [
            'member' => $this->fundi(),
            'enrollment' => $their,
            'documentKinds' => TrainingDocument::KINDS,
        ]);
    }

    /** A senior fundi asks to train AT ONE SITE. The office approves site by site before a cohort is handed over. */
    public function applyTrainer(Request $request)
    {
        $fundi = $this->fundi();

        abort_unless($fundi->canTrain(), 403,
            'Only a senior fundi trains CTPs. You are registered as '.FundiRegistration::tierLabel($fundi->tier)
            .' — the programme office moves a fundi up to senior grade when they are ready to carry a cohort.');

        $request->merge(['fundi_id' => $fundi->id]);
        $data = Trainer::dataFrom($request);

        $held = Trainer::forSite($fundi->id, (int) $data['venue_id']);
        abort_if($held && in_array($held->status, ['applied', 'approved'], true), 422,
            'You are already '.$held->statusLabel().' at '.$held->venue->name.' — the programme office holds that record.');

        $columns = $data;
        unset($columns['trainings']);

        if ($held) {
            // One record per fundi per site: reapplying after a decline reopens that row rather than duplicating it.
            $held->update($columns + ['status' => 'applied', 'reviewed_by' => null]);
            $held->trainings()->sync($data['trainings']);
            $trainer = $held;
        } else {
            $trainer = Trainer::create($columns + ['status' => 'applied']);
            $trainer->trainings()->sync($data['trainings']);
        }
        $trainer->load('venue:id,name');

        return back()->with('status', 'Sent to the programme office for '.$trainer->venue->name.'. '
            .'They approve a trainer site by site before handing them a cohort.');
    }

    /** A trainer marks their own trainee — the line lands on the CTP's record and the office sees it too. */
    public function markProgress(Request $request)
    {
        $enrollment = $this->theirTrainee($request);

        return back()->with('status', TrainingTrack::recordProgress(
            $enrollment, $request, ['recorded_by_fundi' => $this->fundi()->id]));
    }

    /** The trainer marks a module as delivered and got through, on their own roll. */
    public function markModule(Request $request, Enrollment $enrollment, TrainingModule $module)
    {
        $their = $this->theirTrainee($enrollment);

        return back()->with('status', TrainingTrack::markModule(
            $their, $module, ModuleCompletion::dataFrom($request), ['recorded_by_fundi' => $this->fundi()->id]));
    }

    public function unmarkModule(Enrollment $enrollment, TrainingModule $module)
    {
        $their = $this->theirTrainee($enrollment);

        return back()->with('status', TrainingTrack::unmarkModule($their, $module));
    }

    /** A trainer can put the evidence on the record too — what they covered, a photo of the work. */
    public function storeDocument(Request $request, Enrollment $enrollment)
    {
        $their = $this->theirTrainee($enrollment);

        return back()->with('status', TrainingTrack::storeDocument(
            $their, $request, ['uploaded_by_fundi' => $this->fundi()->id]));
    }

    public function document(Enrollment $enrollment, TrainingDocument $document)
    {
        $their = $this->theirTrainee($enrollment);
        abort_unless($document->enrollment_id === $their->id, 404);
        abort_unless(Storage::disk('local')->exists($document->file_path), 404, 'That file is no longer on record.');

        return Storage::disk('local')->response($document->file_path, $document->file_name ?: $document->title);
    }

    /**
     * Module 10, field side. A CTP or an E-Fundi registers the e-waste they picked up — what it is, how much,
     * in what condition and a photo. The register itself, the weighing and the routing belong to the office.
     */
    public function ewaste()
    {
        $member = $this->member();
        abort_if(! $member, 403, 'Your member record could not be found. Contact the programme office.');
        $isCtp = $member instanceof CtpRegistration;

        $mine = EWasteCollection::ordered()
            ->where($isCtp ? 'ctp_id' : 'fundi_id', $member->id)
            ->with(['community:id,name', 'forwarder:id,full_name,ef_code', 'receiver:id,name'])
            ->get();

        // An E-Fundi carries what the CTPs registered to a collection point, so they see the ones waiting near them.
        $waiting = $isCtp ? collect() : EWasteCollection::ordered()
            ->where('status', 'submitted')->whereNull('fundi_id')
            ->where(fn ($q) => $q->where('county', $member->county)->orWhereNull('county'))
            ->where(fn ($q) => $q->where('ctp_id', '!=', $member->id)->orWhereNull('ctp_id'))
            ->with(['ctp:id,full_name,ctp_code', 'community:id,name'])
            ->get();

        return view('portal.ewaste', MemberPortal::board($member) + [
            'isCtp' => $isCtp,
            'mine' => $mine,
            'waiting' => $waiting,
            'itemTypes' => EWasteCollection::ITEM_TYPES,
            'conditions' => EWasteCollection::CONDITIONS,
            'counties' => County::orderBy('name')->pluck('name'),
            'communities' => Community::orderBy('name')->get(['id', 'name', 'county']),
            'totals' => [
                'lines' => $mine->count(),
                'items' => $mine->sum('quantity'),
                'estimated' => round((float) $mine->sum('estimated_weight_kg'), 1),
                'weighed' => round((float) $mine->sum('weighed_weight_kg'), 1),
                'waiting' => $waiting->count(),
            ],
        ]);
    }

    public function storeEwaste(Request $request)
    {
        $member = $this->member();
        abort_if(! $member, 403, 'Your member record could not be found. Contact the programme office.');
        $isCtp = $member instanceof CtpRegistration;

        $data = EWasteCollection::dataFrom($request);

        $collection = EWasteCollection::create([
            ...$data,
            'reference' => EWasteCollection::nextReference(),
            'status' => 'submitted',
            'ctp_id' => $isCtp ? $member->id : null,
            'fundi_id' => $isCtp ? null : $member->id,
        ]);
        $collection->storePhoto($request);

        return back()->with('status', $collection->reference.' registered — '.$collection->quantity
            .' × '.$collection->itemTypeLabel().', '.$collection->conditionLabel().'. '
            .($isCtp
                ? 'An E-Fundi or the office takes it to a collection point, where it is weighed in.'
                : 'Weigh it in at the collection point and the office records the actual weight.'));
    }

    /** An E-Fundi takes a CTP's registered collection to a collection point and sends its condition to the office. */
    public function forwardEwaste(Request $request, EWasteCollection $collection)
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

        return back()->with('status', $collection->reference.' forwarded to the programme office as '
            .EWasteCollection::CONDITIONS[$data['condition']].'. They weigh it in and route it to repair, '
            .'refurbishment, recycling or disposal.');
    }

    /** The photo on a collection line. Only the one who registered it, or the E-Fundi who carried it, opens it. */
    public function ewastePhoto(EWasteCollection $collection)
    {
        $member = $this->member();
        abort_if(! $member, 403);

        $theirLine = $member instanceof CtpRegistration
            ? $collection->ctp_id === $member->id
            : in_array($member->id, array_filter([$collection->fundi_id, $collection->forwarded_by_fundi]), true);

        abort_unless($theirLine, 404);
        abort_unless($collection->photo_path && Storage::disk('local')->exists($collection->photo_path), 404,
            'That photo is no longer on record.');

        return Storage::disk('local')->response($collection->photo_path, $collection->photo_name ?: $collection->reference.'.jpg');
    }

    /**
     * Only a trainee in a cohort this trainer teaches — anyone else's roster 404s and leaks nothing.
     * The seat id comes from the url, so it is always resolved through the trainer's own record.
     */
    private function theirTrainee(Request|Enrollment $request): Enrollment
    {
        $fundi = $this->teachingFundi();
        // A senior fundi may hold an approval at several sites; any of them can carry a cohort.
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

    public function mobilisation()
    {
        $ctp = $this->ctp();

        return view('portal.mobilisation', MemberPortal::board($ctp) + [
            'brought' => $ctp->broughtList(),
        ]);
    }

    public function communities()
    {
        $ctp = $this->ctp();

        return view('portal.communities', MemberPortal::board($ctp) + [
            'types' => Need::TYPES,
            'myNeeds' => $ctp->needsRecorded()->with('community:id,name,community_code,status')->latest('id')->limit(15)->get(),
            'counties' => County::orderBy('name')->pluck('name'),
        ]);
    }

    /** A CTP puts a community forward — it lands on the register as a proposal for the programme office. */
    public function storeCommunity(Request $request)
    {
        $ctp = $this->ctp();
        abort_unless(in_array($ctp->status, CtpRegistration::WORKING, true), 422,
            'Only an active, graduated or deployed CTP can put a community forward. Your status is '.$ctp->statusLabel().'.');

        $community = Community::create([
            ...Community::placeData($request),
            'status' => 'pending_review',
            'created_by_ctp' => $ctp->id,
        ]);

        return back()->with('status', $community->name.' sent to the programme office — they confirm it and issue its COM ID. '
            .'You can already log its needs.');
    }

    /** Step 1-4 of the assessment workflow: the CTP records what they saw, with a photo and a pin. */
    public function storeNeed(Request $request)
    {
        $ctp = $this->ctp();
        $community = $this->theirCommunity($request, $ctp);

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

        return back()->with('status', '"'.$need->title.'" logged for '.$community->name.' — the programme office reviews it next.');
    }

    /** Beneficiaries are only counted once the community is confirmed. */
    public function storeBeneficiary(Request $request)
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

        return back()->with('status', $beneficiary->name.' recorded against '.$community->name.'.');
    }

    /**
     * Communities this CTP is allowed to work on — the roster or their own proposals. The id comes
     * from the form, so it is always resolved through their own record: another CTP's community 404s.
     */
    private function theirCommunity(Request $request, CtpRegistration $ctp): Community
    {
        $request->validate(['community_id' => 'required|integer']);

        return Community::forCtp($ctp)->findOrFail($request->input('community_id'));
    }

    public function projects()
    {
        return view('portal.projects', MemberPortal::board($this->member()));
    }

    public function applications()
    {
        return view('portal.applications', MemberPortal::board($this->member()));
    }

    public function project(Request $request, Project $project)
    {
        $member = $this->member();
        abort_if(! $member, 403, 'Your member record could not be found.');
        // A draft is the office's own worksheet — it does not exist for a member yet.
        abort_if($project->status === 'draft', 404);
        abort_unless($project->viewsTo($member), 403, 'This project is for a different member level.');

        return view('portal.project', [
            'member' => $member,
            'project' => $project->load('hirer'),
            'application' => ProjectApplication::where('project_id', $project->id)
                ->where('applicant_type', MemberPortal::applicantType($member))
                ->where('applicant_id', $member->id)
                ->first(),
        ]);
    }

    public function apply(Request $request, Project $project)
    {
        $member = $this->member();
        abort_if(! $member, 403, 'Your member record could not be found.');
        abort_unless($project->accepts($member), 403, 'This project is for a different member level.');
        if ($project->status !== 'open') {
            return back()->with('error', 'This project is no longer open for applications.');
        }

        $type = MemberPortal::applicantType($member);
        if (ProjectApplication::where('project_id', $project->id)->where('applicant_type', $type)->where('applicant_id', $member->id)->exists()) {
            return back()->with('error', 'You have already applied for this project.');
        }

        $data = $request->validate(['note' => 'nullable|string|max:2000']);

        ProjectApplication::create($data + [
            'project_id' => $project->id,
            'applicant_type' => $type,
            'applicant_id' => $member->id,
            'full_name' => $member->full_name,
            'phone' => $member->phone,
            'email' => $member->email,
        ]);

        return back()->with('status', 'Applied — the programme office will review and assign the project.');
    }

    public function withdraw(ProjectApplication $application)
    {
        $member = $this->member();
        abort_if(! $member, 403, 'Your member record could not be found.');

        abort_unless($application->applicant_type === MemberPortal::applicantType($member) && $application->applicant_id === $member->id, 403);
        abort_unless($application->status === 'pending_review', 422, 'This application has already been reviewed.');

        $application->delete();

        return back()->with('status', 'Application withdrawn.');
    }
}
