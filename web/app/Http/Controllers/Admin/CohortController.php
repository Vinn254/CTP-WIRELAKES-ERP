<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Cohort;
use App\Models\CohortSession;
use App\Models\County;
use App\Models\CtpRegistration;
use App\Models\Enrollment;
use App\Models\FinanceEntry;
use App\Models\ModuleCompletion;
use App\Models\ProgressEntry;
use App\Models\Trainer;
use App\Models\Training;
use App\Models\TrainingDocument;
use App\Models\TrainingModule;
use App\Models\TrainingVenue;
use App\Models\UnitMarkSheet;
use App\Support\Departments;
use App\Support\Documents;
use App\Support\Finance;
use App\Support\Grading;
use App\Support\TeachingDesk;
use App\Support\TrainingTrack;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * Module 4 — a cohort is one intake of one of the programme's trainings, at one place. The office posts it, takes seats,
 * records what happens to each trainee and carries them through to certification.
 */
class CohortController extends Controller
{
    public function index(Request $request)
    {
        $status = $request->input('status', 'all');
        $year = (int) $request->input('year');

        return view('admin.cohorts.index', $this->formData() + [            'cohorts' => Cohort::with(['training:id,name,code', 'trainings:id,name,code', 'venue:id,name,type,county,sub_county,ward', 'trainer.fundi:id,full_name', 'trainer.staff:id,name', 'trainer.applicant:id,name', 'community:id,name', 'counties:id,name', 'units', 'enrollments'])
                ->when($status !== 'all', fn ($q) => $q->where('status', $status))
                ->when($year, fn ($q) => $q->where('academic_year', $year))
                ->orderByDesc('starts_on')->orderBy('id')->get(),
            'status' => $status,
            'year' => $year,
            'years' => Cohort::whereNotNull('academic_year')->distinct()->orderByDesc('academic_year')->pluck('academic_year'),
            'counts' => Cohort::selectRaw('status, count(*) n')->groupBy('status')->pluck('n', 'status'),
            'pendingSeats' => Enrollment::where('state', 'pending')->count(),
        ]);
    }

    public function create()
    {
        return view('admin.cohorts.form', $this->formData() + [
            'cohort' => new Cohort(['seats' => 25]),
            'title' => 'Post a training',
        ]);
    }

    public function edit(Cohort $cohort)
    {
        return view('admin.cohorts.form', $this->formData($cohort->training_id) + [
            'cohort' => $cohort,
            'title' => 'Edit '.$cohort->reference,
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);

        // Step one is the intake and its programmes only. The units are registered on the intake once it exists, and
        // the trainers go onto those units — so nothing here asks for a hand to teach.
        $cohort = Cohort::create([...$this->withoutLists($data), 'reference' => Cohort::nextReference(), 'status' => 'draft']);

        $this->syncTrainings($cohort, $data['training_ids']);
        $this->syncCounties($cohort, $data['county_scope'], $data['county_ids'] ?? []);

        return redirect()->route('admin.cohorts.units', $cohort)
            ->with('status', $cohort->reference.' created as a draft — now register the units it teaches, then name a '
                .'trainer on each. Post it when you are ready for CTPs to apply.');
    }

    public function update(Request $request, Cohort $cohort)
    {
        $data = $this->validated($request);

        $cohort->update($this->withoutLists($data));

        $this->syncTrainings($cohort, $data['training_ids']);
        $this->syncCounties($cohort, $data['county_scope'], $data['county_ids'] ?? []);

        return back()->with('status', $cohort->reference.' updated.');
    }

    /** Step two: the units each programme brings to this intake, and the trainers on each one. */
    public function units(Cohort $cohort)
    {
        $programmes = $cohort->programmeList();
        $units = $this->unitsOf($programmes->pluck('id')->all());

        return view('admin.cohorts.units', [
            'cohort' => $cohort,
            'programmes' => $programmes,
            'programmeUnits' => $units,
            // Only a person registered to teach that programme can be named on one of its units — the same check the
            // save runs, so the list never offers a hand the office would have to refuse.
            'eligible' => $this->eligibleByProgramme($programmes->pluck('id')->all()),
            'chosenUnits' => $cohort->units->pluck('id')->map(fn ($id) => (int) $id)->all(),
            'unitHands' => $this->postedHands($cohort),
        ]);
    }

    public function saveUnits(Request $request, Cohort $cohort)
    {
        $data = $request->validate([
            'module_ids' => 'nullable|array',
            'module_ids.*' => 'integer|exists:training_modules,id',
            'unit_trainers' => 'nullable|array',
            'unit_trainers.*' => 'nullable|array',
            'unit_trainers.*.*' => 'nullable|integer|exists:trainers,id',
        ], [
            'unit_trainers.*.*.exists' => 'A trainer named on a unit must be on the approved trainers list.',
        ], [
            'module_ids' => 'Units this intake teaches', 'module_ids.*' => 'Unit',
            'unit_trainers' => 'Trainers on a unit', 'unit_trainers.*' => 'Trainers on a unit',
            'unit_trainers.*.*' => 'Trainer on a unit',        ]);

        $trainers = $this->cleanHands($data['unit_trainers'] ?? []);

        // A unit the office named a hand on is a unit they mean to teach, so it goes onto the intake with the rest —
        // nobody has to tick a row and save twice to put a lecturer on it.
        $this->syncUnits($cohort, array_merge($data['module_ids'] ?? [], array_keys(array_filter($trainers))), $trainers);

        $units = collect($trainers)->filter()->count();
        $hands = collect($trainers)->map(fn (array $ids) => count($ids))->sum();
        $taught = count(array_unique(array_merge($data['module_ids'] ?? [], array_keys(array_filter($trainers)))));

        return redirect()->route('admin.cohorts.show', $cohort)->with('status', $cohort->reference.' teaches '
            .$taught.' '.Str::plural('unit', $taught).' with '.($hands ?: 'no').' trainer'.($hands === 1 ? '' : 's')
            .' named'.($hands ? ' on '.$units.' '.Str::plural('unit', $units) : ' yet')
            .'. The trainees on each programme now see their own units.');
    }

    /**
     * A unit that the programme does not list yet, registered here and put on this intake in one move — the Registrar
     * is usually writing the intake's units when they notice the course is short of one. The scheme of work goes with
     * it, so the paper the unit is taught from is on the record from the first day.
     */
    public function addUnit(Request $request, Cohort $cohort)
    {
        abort_unless(Departments::canSee($request->user(), 'training'), 403,
            'Training & Certification decides the units under a programme.');

        $data = $request->validate([
            'training_id' => ['required', 'integer', Rule::in($cohort->programmeIds())],
            'title' => ['required', 'string', 'max:150'],
            'outcome' => 'nullable|string|max:500',
            'theory_hours' => 'nullable|integer|min:0|max:2000',
            'practical_hours' => 'nullable|integer|min:0|max:2000',
            'term' => ['nullable', Rule::in(array_keys(TrainingModule::TERMS))],
            'new_trainers' => 'nullable|array',
            'new_trainers.*' => 'nullable|integer|exists:trainers,id',
            'syllabus' => 'nullable|file|mimes:pdf,jpg,jpeg,png,doc,docx,ppt,pptx,xls,xlsx,zip|max:20480',
        ], [
            'training_id.required' => 'Say which programme the unit belongs to.',
            'title.required' => 'Type the unit’s name.',
            'theory_hours.max' => 'Theory hours are whole numbers between 0 and 2000.',
            'practical_hours.max' => 'Practical hours are whole numbers between 0 and 2000.',
            'syllabus.max' => 'The scheme of work is too big for the site — 20 MB at most.',
        ], [
            'training_id' => 'Programme', 'title' => 'Unit', 'outcome' => 'What the trainee must be able to do',
            'theory_hours' => 'Theory hours', 'practical_hours' => 'Practical hours', 'term' => 'Term',
            'new_trainers' => 'Trainers on this unit', 'new_trainers.*' => 'Trainer', 'syllabus' => 'Scheme of work',
        ]);

        $trainingId = (int) $data['training_id'];

        $unit = TrainingModule::create([
            ...TrainingModule::hourSplit([
                'title' => trim($data['title']),
                'outcome' => trim((string) ($data['outcome'] ?? '')) ?: null,
                'theory_hours' => $data['theory_hours'] ?? null,
                'practical_hours' => $data['practical_hours'] ?? null,
                'term' => isset(TrainingModule::TERMS[$data['term'] ?? null]) ? $data['term'] : 'general',
            ]),
            'training_id' => $trainingId,
            'position' => TrainingModule::nextPosition($trainingId),
        ]);

        if ($request->hasFile('syllabus')) {
            $unit->putSyllabus($request->file('syllabus'));
        }

        // Everything already registered on the intake stays where it was; the new unit joins the list with its hands.
        $held = $this->postedHands($cohort);
        $held[$unit->id] = collect($data['new_trainers'] ?? [])->filter()->map(fn ($id) => (int) $id)->unique()->values()->all();

        $this->syncUnits($cohort, [...$cohort->units->pluck('id')->all(), $unit->id], $held);

        return redirect()->route('admin.cohorts.units', $cohort)->with('status', '“'.$unit->title.'” is registered
            under '.$this->trainingName(['training_id' => $trainingId]).' and on '.$cohort->reference.'.'
            .($held[$unit->id] ? ' Its trainer'.(count($held[$unit->id]) > 1 ? 's are' : ' is').
                ' named, so the trainees see '.count($held[$unit->id]).' '.Str::plural('name', count($held[$unit->id]))
                .' on the unit.' : ' Name a trainer on its row when you are ready.'));
    }

    /** The scheme of work filed on a unit, opened by the desk that teaches it. */
    public function scheme(Request $request, TrainingModule $module)
    {
        abort_unless($module->hasSyllabus(), 404, 'No scheme of work is filed on that unit yet.');

        $holds = $module->cohorts()->whereHas('hands', fn ($q) => $q->whereIn('trainer_id',
            TeachingDesk::trainerIds($request->user())))->exists();

        abort_unless(Departments::canSee($request->user(), 'training') || $holds, 403,
            'The scheme of a unit is open to the Training office and to the trainers it is taught by.');

        return Documents::file($request, $module->syllabus_path, $module->syllabus_name ?: $module->title.' scheme');
    }

    /** The hands already named on each of the intake's units, as ids — the lead name first. */
    private function postedHands(Cohort $cohort): array
    {
        $rows = $cohort->hands()->get()->groupBy('training_module_id');

        return $cohort->units->mapWithKeys(function ($unit) use ($rows) {
            $lead = (int) ($unit->pivot?->trainer_id ?? 0);
            $ids = collect([$lead ?: null])->concat(
                ($rows[$unit->id] ?? collect())->pluck('trainer_id'))->filter()->unique()->values()->all();

            return [$unit->id => $ids];
        })->all();
    }

    /** What the office posted, tidied: a list of trainer ids per unit, blanks and doubles dropped. */
    private function cleanHands(array $posted): array
    {
        return collect($posted)->map(fn ($ids) => collect($ids ?? [])->filter()
            ->map(fn ($id) => (int) $id)->unique()->values()->all())->all();
    }

    /** Posting a cohort is what makes it visible to CTPs; closing it stops new applications. */
    public function setStatus(Request $request, Cohort $cohort)
    {
        $data = $request->validate([
            'status' => 'required|in:'.implode(',', array_keys(Cohort::STATUSES)),
        ]);

        // A community is never a condition for posting: an intake can serve every community or none. What an intake
        // does need before CTPs can apply is its units, so the flash names the step still owed.
        if ($data['status'] === 'open' && $cohort->enrollments()->where('state', 'approved')->exists()) {
            $data['status'] = 'in_progress';
        }

        $cohort->update($data);

        return back()->with('status', $cohort->reference.' is now '.strtolower(Cohort::STATUSES[$data['status']]).'.'
            .($data['status'] === 'open' && $cohort->unitList()->isEmpty()
                ? ' It has no units registered yet — CTPs can apply, but they will see nothing to learn until you '
                  .'tick them under Units and trainers.'
                : ''));
    }

    /** Every cohort's results on one table — the office picks a cohort, or a programme, and reads the trainees in it. */
    public function results(Request $request)
    {
        $cohorts = Cohort::where('status', '!=', 'draft')
            ->with(['training:id,name,code', 'trainings', 'venue:id,name,county',
                'trainer.fundi:id,full_name', 'trainer.staff:id,name', 'trainer.applicant:id,name',
                'enrollments:id,cohort_id,state'])
            ->orderByDesc('academic_year')->orderByDesc('starts_on')->orderBy('id')->get();

        // A programme is read across every intake that has run it: the Registrar grades a course, not only a class.
        $programme = $request->filled('training') ? Training::find((int) $request->input('training')) : null;
        $runs = $programme
            ? $cohorts->filter(fn (Cohort $c) => in_array($programme->id, $c->programmeIds(), true))->values()
            : $cohorts;

        // Reading a course defaults to the newest intake that actually has trainees on it, so the sheet a Marks link
        // opens is a live one rather than an intake that was called off before anyone sat.
        $cohort = $runs->firstWhere('id', (int) $request->input('cohort'))
            ?: ($runs->first(fn (Cohort $c) => $c->enrollments->where('state', 'approved')->isNotEmpty()) ?: $runs->first());

        $seats = fn ($query) => $query
            ->with(['ctp:id,full_name,ctp_code', 'entries', 'moduleMarks', 'certification',
                'cohort:id,reference,training_id,academic_year,term,starts_on,ends_on,status', 'cohort.units', 'cohort.training'])
            ->orderByRaw("case state when 'approved' then 0 when 'pending' then 1 else 2 end")
            ->get()
            ->sortBy(fn ($e) => mb_strtolower($e->ctp->full_name ?? ''))
            ->values();

        $enrollments = $programme
            ? $seats(Enrollment::whereIn('cohort_id', $runs->pluck('id')))
            : ($cohort ? $seats($cohort->enrollments()) : collect());

        $held = $enrollments->where('state', 'approved');

        return view('admin.cohorts.results', [
            'cohorts' => $cohorts,
            'cohort' => $cohort,
            'enrollments' => $enrollments,
            'programme' => $programme,
            'programmes' => $this->programmesRun($cohorts),
            // The marks the unit sheets carry, gathered unit by unit and weighed on the Registrar's grading, so the
            // results read as one table of marks rather than as separate papers.
            'board' => $programme ? Grading::programmeBoard($programme, $held)
                : ($cohort ? Grading::cohortBoard($cohort, $held) : []),
            'canCorrect' => TeachingDesk::approves($request->user()),
            'runs' => $programme ? $runs : ($cohort ? $this->runsOf($cohort) : collect()),
            // The intakes the chosen programme has actually run — the cohort box lists only these while a course is picked.
            'programmeRuns' => $programme ? $runs : collect(),
        ]);
    }

    /** The programmes a posted intake has actually run — the row the Registrar clicks a course from. */
    private function programmesRun($cohorts)
    {
        $ids = $cohorts->flatMap(fn (Cohort $c) => $c->programmeIds())->unique()->values();

        return Training::whereIn('id', $ids)->orderBy('name')->get(['id', 'name', 'code'])
            ->map(fn (Training $t) => [
                'training' => $t,
                'runs' => $cohorts->filter(fn (Cohort $c) => in_array($t->id, $c->programmeIds(), true))->count(),
                'seats' => $cohorts->filter(fn (Cohort $c) => in_array($t->id, $c->programmeIds(), true))
                    ->flatMap(fn (Cohort $c) => $c->enrollments->where('state', 'approved'))->count(),
            ]);
    }

    /** The cohort page: the roster, the seat queue and every trainee's progress. */
    public function show(Cohort $cohort)
    {
        $cohort->load(['training', 'trainings', 'units', 'venue', 'trainer.fundi', 'trainer.staff', 'trainer.applicant', 'trainer.trainings', 'community', 'counties']);

        $candidates = $this->candidates($cohort);

        return view('admin.cohorts.show', [
            'cohort' => $cohort,
            'enrollments' => $cohort->enrollments()
                ->with(['cohort.units', 'cohort.training', 'ctp:id,full_name,ctp_code,status,county,sub_county,ward,training_id',
                    'ctp.programme:id,name', 'entries', 'certification',
                    'moduleMarks', 'documents'])
                ->orderByRaw("case state when 'pending' then 0 when 'approved' then 1 else 2 end")
                ->orderBy('id')->get(),
            'modules' => $cohort->unitList(),
            'unitHands' => $cohort->handsMap(),
            'programmeNames' => Training::whereIn('id', $cohort->unitList()->pluck('training_id')->unique()->all())
                ->pluck('name', 'id')->all(),
            'documentKinds' => TrainingDocument::KINDS,
            'candidates' => $candidates['groups'],
            'candidateTitles' => $cohort->programmeList()->pluck('name', 'id')->all(),
            'elsewhere' => $candidates['elsewhere'],
            'kinds' => ProgressEntry::KINDS,
            'stages' => Enrollment::STAGES,
            'states' => Enrollment::STATES,
            'runs' => $this->runsOf($cohort),
        ]);
    }

    /**
     * The students this intake can seat, fetched by programme: a CTP registered under one of the courses taught here
     * is listed under that course, so the units handed to the intake reach the right people.
     */
    private function candidates(Cohort $cohort): array
    {
        $waiting = CtpRegistration::whereIn('status', CtpRegistration::WORKING)
            ->whereNotIn('id', $cohort->enrollments()->pluck('ctp_id'))
            ->orderBy('full_name')
            ->get(['id', 'full_name', 'ctp_code', 'county', 'sub_county', 'ward', 'status', 'training_id']);

        $mine = $cohort->programmeIds();
        $here = $waiting->filter(fn (CtpRegistration $c) => $c->training_id && in_array((int) $c->training_id, $mine, true));

        // A CTP whose programme the office has not set yet can still be seated — the seat forces the programme onto
        // their file, since their units come from it.
        $unplaced = $waiting->filter(fn (CtpRegistration $c) => ! $c->training_id);

        $groups = $here->groupBy('training_id')->sortKeys();

        // Their group is listed last, so the office picks from the courses this intake teaches first.
        if ($unplaced->isNotEmpty()) {
            $groups[0] = $unplaced->values();
        }

        return ['groups' => $groups, 'elsewhere' => $waiting->count() - $here->count() - $unplaced->count()];
    }

    /** The office puts a CTP straight into a seat — the same guard the CTP's own application passes. */
    public function enrol(Request $request, Cohort $cohort)
    {
        $data = $request->validate([
            'ctp_id' => 'required|integer|exists:ctp_registrations,id',
            'note' => 'nullable|string|max:1000',
        ], ['ctp_id' => 'CTP']);

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

    /**
     * The trainee's course fee is money in. Recorded on the seat, and the ledger gets its line —
     * the cohort only carries one fee per trainee, so a seat can pay it once.
     */
    public function fee(Request $request, Enrollment $enrollment)
    {
        $data = Enrollment::feeFrom($request);
        $amount = (float) ($data['fee_amount'] ?: $enrollment->feeDueKsh());

        abort_unless($enrollment->state === 'approved', 422, 'Only a trainee holding a seat has a fee to record.');
        abort_if($amount <= 0, 422, $enrollment->cohort->reference.' carries no course fee, so there is nothing to record.');

        $already = FinanceEntry::where('kind', 'training_fee')
            ->where('cohort_id', $enrollment->cohort_id)->where('ctp_id', $enrollment->ctp_id)->exists();

        $enrollment->update([
            'fee_status' => $data['fee_status'],
            'fee_method' => $data['fee_status'] === 'paid' ? $data['fee_method'] : null,
            'fee_amount' => $amount,
            'fee_reference' => $data['fee_reference'] ?? null,
            'fee_paid_at' => now(),
            'fee_recorded_by' => auth()->id(),
        ]);

        if ($data['fee_status'] === 'paid' && ! $already) {
            FinanceEntry::post('training_fee', 'Course fee — '.$enrollment->ctp->full_name.' · '.$enrollment->cohort->reference,
                $amount, [
                    'method' => $data['fee_method'] ?: 'other',
                    'cohort_id' => $enrollment->cohort_id,
                    'ctp_id' => $enrollment->ctp_id,
                    'source_type' => Enrollment::class,
                    'source_id' => $enrollment->id,
                    'note' => 'Recorded on the seat'.($data['fee_reference'] ?? null ? ' under '.$data['fee_reference'] : '.'),
                ]);
        }

        return back()->with('status', $enrollment->ctp->full_name.': '.($data['fee_status'] === 'paid'
            ? 'course fee of '.Finance::ksh($amount).' recorded for '.$enrollment->cohort->reference.' — it is on the ledger.'
            : 'course fee waived for '.$enrollment->cohort->reference.'.'));
    }

    /** How far through the cohort a trainee has come. */
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
    public function document(Request $request, Enrollment $enrollment, TrainingDocument $document)
    {
        abort_unless($document->enrollment_id === $enrollment->id, 404);

        return Documents::file($request, $document->file_path, $document->file_name ?: $document->title);
    }

    /** A mistaken application can be removed; a seat that was filled is declined instead, so the history stays. */
    public function drop(Enrollment $enrollment)
    {
        abort_unless($enrollment->state === 'pending', 422,
            'Only an untouched application can be deleted. Decline it, or withdraw the seat, so the record stays.');

        $enrollment->delete();

        return back()->with('status', 'Application removed.');
    }

    /** Every run touching any of this intake's programmes, newest academic year first — a course is expected to run again. */
    private function runsOf(Cohort $cohort)
    {
        $ids = $cohort->programmeIds();

        return Cohort::where(fn ($q) => $q->whereIn('training_id', $ids)
                ->orWhereHas('trainings', fn ($t) => $t->whereIn('trainings.id', $ids)))
            ->with(['trainer.fundi:id,full_name', 'trainer.staff:id,name', 'trainer.applicant:id,name', 'venue:id,name',
                'enrollments' => fn ($q) => $q->select('id', 'cohort_id', 'state', 'stage')])
            ->orderByDesc('academic_year')->orderByDesc('starts_on')->orderBy('id')->get();
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            // An intake can run several programmes at once — the same group of trainees mobilised together. The id on
            // the cohort row is the lead one (the first chosen), because every paper is still written per programme.
            'training_ids' => 'required|array|min:1',
            'training_ids.*' => 'integer|exists:trainings,id',
            'venue_id' => 'nullable|integer|exists:training_venues,id',
            'community_id' => 'nullable|integer|exists:communities,id',
            // Where it physically happens is optional detail; who it is offered to is the reach the office states here.
            'county_scope' => 'required|in:all,listed',
            'county_ids' => 'required_if:county_scope,listed|array|min:1',
            'county_ids.*' => 'integer|exists:counties,id',
            'sub_county' => 'nullable|string|max:100',
            'ward' => 'nullable|string|max:120',
            'starts_on' => 'required|date',
            'ends_on' => 'required|date|after_or_equal:starts_on',
            'academic_year' => 'nullable|integer|min:2000|max:2100',
            'term' => 'nullable|in:'.implode(',', array_keys(Cohort::TERMS)),
            'enrol_closes_on' => 'nullable|date|before_or_equal:starts_on',
            'seats' => 'required|integer|min:1|max:500',
            'fee_ksh' => 'nullable|numeric|min:0|max:9999999',
            'summary' => 'nullable|string|max:2000',
        ], [
            'training_ids.required' => 'Pick the programmes this intake teaches — at least one.',
            'training_ids.*.exists' => 'A programme on an intake must be one the programme runs (see Training).',
            'county_ids.required' => 'Tick at least one county, or choose All counties.',
            'county_ids.*.exists' => 'A county ticked on an intake must be one of the counties the programme works in.',
        ], [
            'training_ids' => 'Programmes taught on this intake', 'training_ids.*' => 'Programme',
            'venue_id' => 'Venue', 'community_id' => 'Community', 'county_scope' => 'Counties it is offered in',
            'county_ids' => 'Counties it is offered in', 'county_ids.*' => 'County',
            'sub_county' => 'Sub-county', 'ward' => 'Ward',
            'seats' => 'Seats', 'starts_on' => 'Start date', 'ends_on' => 'End date',
            'enrol_closes_on' => 'Applications close', 'academic_year' => 'Academic year',
            'term' => 'Term or semester', 'fee_ksh' => 'Training fee',
        ]);
    }

    /** The approved postings that could be named on a unit of one of these programmes. */
    private function eligibleByProgramme(array $trainingIds): array
    {
        $byId = Trainer::where('status', 'approved')
            ->with(['fundi:id,full_name', 'staff:id,name', 'applicant:id,name', 'trainings:id,name'])
            ->get()->keyBy('id');

        return collect($trainingIds)->mapWithKeys(fn ($id) => [$id => $byId->filter(
            fn (Trainer $t) => $t->canTeach((int) $id))->values()->all()])->all();
    }

    /** Every unit of these programmes, grouped under the programme it belongs to. */
    private function unitsOf(array $trainingIds)
    {
        return TrainingModule::whereIn('training_id', $trainingIds)
            ->orderBy('position')->orderBy('id')->get()->groupBy('training_id');
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

        // The dates decide too: a draft takes no seats, and a run whose end date has passed is finished whatever the
        // status box still says.
        abort_unless($cohort->canHoldSeats(), 422,
            $cohort->reference.' can take no seat — '.$cohort->seatRefusalReason().'.');

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

    /** The ticked units and the programme list are kept on their own tables, so they come out before the row is written. */
    private function withoutLists(array $data): array
    {
        $chosen = collect($data['training_ids'] ?? [])->map(fn ($id) => (int) $id)->filter()->values();
        $lead = $chosen->first();

        unset($data['module_ids'], $data['unit_trainers'], $data['training_ids'], $data['county_ids']);

        // The intake keeps one lead programme, because every paper already written names one course. It is the first
        // programme the office chose, and a course already chosen stays the lead when the intake is edited.
        $data['training_id'] = $lead ?: ($data['training_id'] ?? null);

        return $data;
    }

    /** Register the programmes this intake teaches. The lead programme is always on the list. */
    private function syncTrainings(Cohort $cohort, array $ids): void
    {
        $ids = collect($ids)->map(fn ($id) => (int) $id)->filter()
            ->push((int) $cohort->training_id)->unique()->values()->all();

        $cohort->trainings()->sync($ids);
    }

    /**
     * Which counties the intake is offered in. All counties leaves the list empty; the county written on the cohort row
     * is the first one ticked, because a printed place and the impact map read one name, not a list.
     */
    private function syncCounties(Cohort $cohort, string $scope, array $ids): void
    {
        $names = $scope === 'listed'
            ? County::whereIn('id', collect($ids)->map(fn ($id) => (int) $id)->all())->orderBy('name')->get()
            : collect();

        $cohort->counties()->sync($names->pluck('id')->all());
        $cohort->update(['county_scope' => $scope, 'county' => $names->first()?->name]);
    }

    /**
     * Save the office's ticks and the trainers named on each. A unit belongs to one of the intake's programmes — and a
     * unit a student has already been marked on cannot be taken off the intake, so no mark is ever orphaned by a
     * retick. The first name on a unit is its lead: it stays on the pivot column every paper already reads.
     */
    private function syncUnits(Cohort $cohort, array $ids, array $trainers = []): void
    {
        $allowed = TrainingModule::whereIn('training_id', $cohort->programmeIds())->pluck('id')->all();
        // Cast to ints: the posted ticks arrive as strings, and a trainer named on a unit is looked up by its id.
        $ids = array_values(array_map('intval', array_intersect($ids, $allowed)));
        $trainers = $this->cleanHands($trainers);

        $held = $cohort->units()->pluck('training_module_id')->all();
        $dropping = array_diff(array_map('intval', $held), $ids);

        if ($dropping) {
            $read = UnitMarkSheet::where('cohort_id', $cohort->id)->whereIn('training_module_id', $dropping)->exists()
                || CohortSession::where('cohort_id', $cohort->id)->whereIn('training_module_id', $dropping)->exists()
                || ModuleCompletion::whereIn('enrollment_id', $cohort->enrollments()->pluck('id'))
                    ->whereIn('training_module_id', $dropping)->exists();

            if ($read) {
                $title = TrainingModule::whereIn('id', $dropping)->orderBy('position')->value('title');

                abort(422, '“'.$title.'” already has a register, a marks sheet or a module mark on this intake. '
                    .'A unit a student has been read on stays on it — clear that mark first, or leave the unit ticked.');
            }
        }

        foreach ($ids as $moduleId) {
            foreach ($trainers[$moduleId] ?? [] as $trainerId) {
                $this->guardUnitTrainer($cohort, $trainerId, $moduleId);
            }
        }

        $cohort->units()->sync(collect($ids)->mapWithKeys(
            fn ($id) => [$id => ['trainer_id' => ($trainers[$id] ?? [])[0] ?? null]]
        )->all());

        // The list of hands is rewritten whole: a name taken off a unit goes, and no row is left pointing at a unit
        // that is no longer ticked.
        $cohort->hands()->whereIn('training_module_id', $dropping)->delete();

        foreach ($ids as $moduleId) {
            $wanted = $trainers[$moduleId] ?? [];
            $cohort->hands()->where('training_module_id', $moduleId)->whereNotIn('trainer_id', $wanted)->delete();

            $there = $cohort->hands()->where('training_module_id', $moduleId)->pluck('trainer_id')->all();

            foreach (array_diff($wanted, $there) as $trainerId) {
                $cohort->hands()->create(['training_module_id' => $moduleId, 'trainer_id' => $trainerId]);
            }
        }
    }

    /**
     * A unit handed to a person is a unit that person must be approved to teach here: approved on the site, approved
     * at this venue, and registered for the programme the unit itself belongs to — an intake can run several courses,
     * so it is the unit's own programme that decides, not the intake's lead one.
     */
    private function guardUnitTrainer(Cohort $cohort, int $trainerId, int $moduleId): void
    {
        $trainer = Trainer::with(['fundi:id,full_name,status', 'staff:id,name', 'applicant:id,name'])->find($trainerId);
        $unit = TrainingModule::find($moduleId);
        abort_if(! $trainer || ! $unit, 422, 'The trainer named on a unit is not on the approved list.');

        $who = $trainer->personName();
        $what = $this->trainingName(['training_id' => $unit->training_id]);

        abort_unless($trainer->status === 'approved', 422,
            $who.' is not an approved trainer — approve them under Trainers before handing them “'.$unit->title.'”.');

        if ($cohort->venue_id) {
            abort_unless((int) $trainer->venue_id === (int) $cohort->venue_id, 422,
                $who.' is approved at '.$trainer->placeLabel().', not at '.$this->siteName(['venue_id' => $cohort->venue_id])
                    .' — “'.$unit->title.'” can only go to a trainer the office has approved for this site.');
        }

        abort_unless($trainer->canTeach((int) $unit->training_id), 422,
            $who.' is not registered to teach '.$what.' (the programme “'.$unit->title.'” belongs to). '
                .'Add that programme to their record before handing them the unit.');
    }

    private function formData(?int $trainingId = null): array
    {
        return [
            'trainings' => TrainingController::options($trainingId),
            'venues' => VenueController::options(),
            'communities' => \App\Models\Community::where('status', 'active')
                ->orderBy('county')->orderBy('name')->get(['id', 'name', 'county', 'sub_county', 'ward']),
            'counties' => County::orderBy('name')->get(['id', 'name']),
        ];
    }
}
