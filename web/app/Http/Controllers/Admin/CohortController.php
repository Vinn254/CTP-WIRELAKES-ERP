<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Cohort;
use App\Models\CtpRegistration;
use App\Models\Enrollment;
use App\Models\ModuleCompletion;
use App\Models\ProgressEntry;
use App\Models\Trainer;
use App\Models\Training;
use App\Models\TrainingDocument;
use App\Models\TrainingModule;
use App\Models\TrainingVenue;
use App\Support\TrainingTrack;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/**
 * Module 4 — a cohort is one intake of one of the programme's trainings, at one place. The office posts it, takes seats,
 * records what happens to each trainee and carries them through to certification.
 */
class CohortController extends Controller
{
    public function index(Request $request)
    {
        $status = $request->input('status', 'all');

        return view('admin.cohorts.index', [
            'cohorts' => Cohort::with(['training:id,name,code', 'venue:id,name,type,county,sub_county,ward', 'trainer.fundi:id,full_name', 'community:id,name', 'enrollments'])
                ->when($status !== 'all', fn ($q) => $q->where('status', $status))
                ->orderByDesc('starts_on')->orderBy('id')->get(),
            'status' => $status,
            'counts' => Cohort::selectRaw('status, count(*) n')->groupBy('status')->pluck('n', 'status'),
            'pendingSeats' => Enrollment::where('state', 'pending')->count(),
        ]);
    }

    public function create()
    {
        return view('admin.cohorts.form', $this->formData() + [
            'cohort' => new Cohort(['theory_hours' => 40, 'practical_hours' => 60, 'seats' => 25]),
            'title' => 'Post a training',
        ]);
    }

    public function edit(Cohort $cohort)
    {
        return view('admin.cohorts.form', $this->formData() + [
            'cohort' => $cohort,
            'title' => 'Edit '.$cohort->reference,
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        $this->guard($data);

        $cohort = Cohort::create([...$data, 'reference' => Cohort::nextReference(), 'status' => 'draft']);

        return redirect()->route('admin.cohorts.show', $cohort)
            ->with('status', $cohort->reference.' — '.$cohort->courseLabel().' created as a draft. Post it when you are ready for CTPs to apply.');
    }

    public function update(Request $request, Cohort $cohort)
    {
        $data = $this->validated($request);
        $this->guard($data);

        $cohort->update($data);

        return back()->with('status', $cohort->reference.' updated.');
    }

    /** Posting a cohort is what makes it visible to CTPs; closing it stops new applications. */
    public function setStatus(Request $request, Cohort $cohort)
    {
        $data = $request->validate([
            'status' => 'required|in:'.implode(',', array_keys(Cohort::STATUSES)),
        ]);

        if ($data['status'] !== 'draft') {
            $this->guard($cohort->only(['venue_id', 'trainer_id', 'community_id', 'training_id']));
        }

        if ($data['status'] === 'open' && $cohort->enrollments()->where('state', 'approved')->exists()) {
            $data['status'] = 'in_progress';
        }

        $cohort->update($data);

        return back()->with('status', $cohort->reference.' is now '.strtolower(Cohort::STATUSES[$data['status']]).'.');
    }

    /** The cohort page: the roster, the seat queue and every trainee's progress. */
    public function show(Cohort $cohort)
    {
        $cohort->load(['training', 'venue', 'trainer.fundi', 'trainer.trainings', 'community']);

        return view('admin.cohorts.show', [
            'cohort' => $cohort,
            'enrollments' => $cohort->enrollments()
                ->with(['ctp:id,full_name,ctp_code,status,county,sub_county,ward', 'entries', 'certification',
                    'moduleMarks', 'documents'])
                ->orderByRaw("case state when 'pending' then 0 when 'approved' then 1 else 2 end")
                ->orderBy('id')->get(),
            'modules' => $cohort->training?->modules ?? collect(),
            'documentKinds' => TrainingDocument::KINDS,
            'candidates' => CtpRegistration::whereIn('status', CtpRegistration::WORKING)
                ->whereNotIn('id', $cohort->enrollments()->pluck('ctp_id'))
                ->orderBy('county')->orderBy('full_name')->get(['id', 'full_name', 'ctp_code', 'county', 'sub_county', 'ward', 'status']),
            'kinds' => ProgressEntry::KINDS,
            'stages' => Enrollment::STAGES,
            'states' => Enrollment::STATES,
        ]);
    }

    /** The office puts a CTP straight into a seat — the same guard the CTP's own application passes. */
    public function enrol(Request $request, Cohort $cohort)
    {
        $data = $request->validate([
            'ctp_id' => 'required|integer|exists:ctp_registrations,id',
            'note' => 'nullable|string|max:1000',
        ], [], ['ctp_id' => 'CTP']);

        $ctp = CtpRegistration::findOrFail($data['ctp_id']);
        $this->guardSeat($cohort, $ctp);

        // One CTP holds one record per cohort: a seat taken back or withdrawn is reopened, never duplicated.
        $enrollment = Enrollment::where('cohort_id', $cohort->id)->where('ctp_id', $ctp->id)->first();

        if ($enrollment) {
            $enrollment->update([
                'state' => 'approved',
                'source' => 'office',
                'note' => $data['note'] ?? $enrollment->note,
                'reviewed_by' => auth()->id(),
                'approved_on' => today(),
            ]);
        } else {
            $enrollment = Enrollment::create([
                'cohort_id' => $cohort->id,
                'ctp_id' => $ctp->id,
                'state' => 'approved',
                'source' => 'office',
                'note' => $data['note'] ?? null,
                'reviewed_by' => auth()->id(),
                'approved_on' => today(),
            ]);
        }

        return back()->with('status', $ctp->full_name.' holds a seat in '.$cohort->reference.'. Their training starts at '.$cohort->placeLabel().'.');
    }

    /** Yes or no on a CTP's application for a seat. */
    public function decide(Request $request, Enrollment $enrollment)
    {
        $data = $request->validate([
            'decision' => 'required|in:approved,declined',
            'note' => 'nullable|string|max:1000',
        ]);

        abort_unless(in_array($enrollment->state, ['pending', 'approved'], true), 422,
            'This application was already '.$enrollment->stateLabel().'.');

        if ($data['decision'] === 'approved') {
            $this->guardSeat($enrollment->cohort, $enrollment->ctp, $enrollment);
        } else {
            // A trainee who has run the cohort to the end keeps the record — a seat is only taken back before that.
            abort_unless($enrollment->state === 'pending' || ! in_array($enrollment->stage, ['completed', 'failed'], true), 422,
                $enrollment->ctp->full_name.' has already reached '.$enrollment->stageLabel().' in '.$enrollment->cohort->reference.'.');
        }

        $enrollment->update([
            'state' => $data['decision'],
            'note' => $data['note'] ?? $enrollment->note,
            'reviewed_by' => auth()->id(),
            'approved_on' => $data['decision'] === 'approved' ? today() : null,
        ]);

        return back()->with('status', $enrollment->ctp->full_name.' '.($data['decision'] === 'approved'
            ? 'has a seat in '.$enrollment->cohort->reference.'.'
            : 'is not taken into '.$enrollment->cohort->reference.'.'));
    }

    /** How far through the cohort a trainee has come (§7.5 steps 2-5). */
    public function setStage(Request $request, Enrollment $enrollment)
    {
        $data = $request->validate([
            'stage' => 'required|in:'.implode(',', array_keys(Enrollment::STAGES)),
        ]);

        abort_unless($enrollment->state === 'approved', 422, 'Only a trainee holding a seat has a stage.');

        $enrollment->update($data);

        TrainingTrack::syncCertification($enrollment, auth()->id());

        return back()->with('status', $enrollment->ctp->full_name.' is now at the '.strtolower($data['stage']).' stage.');
    }

    /** The office marks a module got through — the same row the trainer writes from their own screen. */
    public function markModule(Request $request, Enrollment $enrollment, TrainingModule $module)
    {
        $enrollment->load(['cohort', 'ctp']);

        return back()->with('status', TrainingTrack::markModule(
            $enrollment, $module, ModuleCompletion::dataFrom($request), ['recorded_by_user' => auth()->id()]));
    }

    public function unmarkModule(Enrollment $enrollment, TrainingModule $module)
    {
        $enrollment->load(['cohort', 'ctp']);

        return back()->with('status', TrainingTrack::unmarkModule($enrollment, $module));
    }

    /** Uploading the report a partner school sent is how a placed CTP's paper gets on the record. */
    public function storeDocument(Request $request, Enrollment $enrollment)
    {
        $enrollment->load(['cohort', 'ctp']);

        return back()->with('status', TrainingTrack::storeDocument(
            $enrollment, $request, ['uploaded_by_user' => auth()->id()]));
    }

    /** Documents are private: only someone who can open the cohort page can open the file. */
    public function document(Enrollment $enrollment, TrainingDocument $document)
    {
        abort_unless($document->enrollment_id === $enrollment->id, 404);

        abort_unless(Storage::disk('local')->exists($document->file_path), 404, 'That file is no longer on record.');

        return Storage::disk('local')->response($document->file_path, $document->file_name ?: $document->title);
    }

    /** A mistaken application can be removed; a seat that was filled is declined instead, so the history stays. */
    public function drop(Enrollment $enrollment)
    {
        abort_unless($enrollment->state === 'pending', 422,
            'Only an untouched application can be deleted. Decline it, or withdraw the seat, so the record stays.');

        $enrollment->delete();

        return back()->with('status', 'Application removed.');
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'training_id' => 'required|integer|exists:trainings,id',
            'venue_id' => 'nullable|integer|exists:training_venues,id',
            'trainer_id' => 'nullable|integer|exists:trainers,id',
            'community_id' => 'nullable|integer|exists:communities,id',
            'county' => 'required|string|max:100',
            'sub_county' => 'nullable|string|max:100',
            'ward' => 'nullable|string|max:120',
            'starts_on' => 'required|date',
            'ends_on' => 'required|date|after_or_equal:starts_on',
            'enrol_closes_on' => 'nullable|date|before_or_equal:starts_on',
            'seats' => 'required|integer|min:1|max:500',
            'theory_hours' => 'nullable|integer|min:0|max:2000',
            'practical_hours' => 'nullable|integer|min:0|max:2000',
            'fee_ksh' => 'nullable|numeric|min:0|max:9999999',
            'summary' => 'nullable|string|max:2000',
        ], [], [
            'training_id' => 'Training', 'venue_id' => 'Venue', 'trainer_id' => 'Trainer',
            'community_id' => 'Community', 'county' => 'County', 'sub_county' => 'Sub-county', 'seats' => 'Seats',
            'starts_on' => 'Start date', 'ends_on' => 'End date', 'enrol_closes_on' => 'Applications close',
            'fee_ksh' => 'Training fee',
        ]);
    }

    /** A cohort must have somewhere to happen and a trainer approved to teach that training at that site. */
    private function guard(array $data): void
    {
        abort_if(empty($data['venue_id']) && empty($data['trainer_id']) && empty($data['community_id']), 422,
            'A training needs a venue, a trainer or a community to run in — pick at least one.');

        if (! empty($data['trainer_id'])) {
            $trainer = Trainer::with('fundi:id,full_name,status')->findOrFail($data['trainer_id']);

            abort_unless($trainer->status === 'approved', 422,
                $trainer->fundi->full_name.' is not an approved trainer for '.$this->siteName($data).' — approve that site under Fundi-trainers first.');

            // A trainer is approved site by site (§7.8), so an approval held at another venue does not carry over.
            if (! empty($data['venue_id'])) {
                abort_unless((int) $trainer->venue_id === (int) $data['venue_id'], 422,
                    $trainer->fundi->full_name.' is approved at '.$trainer->placeLabel().', not at '.$this->siteName($data).'. '
                        .'Hand them a cohort at the site the office approved, or take their application for '.$this->siteName($data).'.');
            }

            abort_unless(! empty($data['training_id']) && $trainer->canTeach((int) $data['training_id']), 422,
                $trainer->fundi->full_name.' is not registered to teach '.$this->trainingName($data).'. Add that training to their record first.');
        }
    }

    private function siteName(array $data): string
    {
        return empty($data['venue_id'])
            ? 'this cohort'
            : (TrainingVenue::whereKey($data['venue_id'])->value('name') ?? 'this site');
    }

    /** Who may hold a seat: an approved CTP who is in the programme, and room left in the cohort. */
    private function guardSeat(Cohort $cohort, CtpRegistration $ctp, ?Enrollment $ignoring = null): void
    {
        abort_unless(in_array($ctp->status, CtpRegistration::WORKING, true), 422,
            $ctp->full_name.' is '.$ctp->statusLabel().'. Training seats go to a CTP once they are active.');

        abort_if(in_array($cohort->status, ['completed', 'cancelled'], true), 422,
            $cohort->reference.' is '.$cohort->statusLabel().'.');

        abort_if($cohort->isFull(), 422, $cohort->reference.' is full — all '.$cohort->seats.' seats are taken.');

        abort_if($cohort->enrollments()->where('ctp_id', $ctp->id)
            ->when($ignoring, fn ($q) => $q->where('id', '!=', $ignoring->id))
            ->whereIn('state', ['pending', 'approved'])->exists(), 422,
            $ctp->full_name.' is already on that cohort.');
    }

    private function trainingName(array $data): string
    {
        return Training::find($data['training_id'] ?? null)?->name ?? 'that training';
    }

    private function formData(): array
    {
        return [
            'trainings' => TrainingController::options(),
            'venues' => VenueController::options(),
            'trainers' => Trainer::approved()->with(['fundi:id,full_name,ef_code', 'trainings:id,name'])->get(),
            'communities' => \App\Models\Community::where('status', 'active')
                ->orderBy('county')->orderBy('name')->get(['id', 'name', 'county', 'sub_county', 'ward']),
            'counties' => \App\Models\County::orderBy('name')->pluck('name'),
        ];
    }
}
