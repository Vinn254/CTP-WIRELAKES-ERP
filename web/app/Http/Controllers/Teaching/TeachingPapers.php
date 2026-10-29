<?php

namespace App\Http\Controllers\Teaching;

use App\Http\Controllers\Controller;
use App\Models\Cohort;
use App\Models\CohortSession;
use App\Models\CohortSessionMark;
use App\Models\LessonLine;
use App\Models\LessonPlan;
use App\Models\Timetable;
use App\Models\TimetableSlot;
use App\Models\Trainer;
use App\Models\TrainingMaterial;
use App\Models\UnitMark;
use App\Models\UnitMarkSheet;
use App\Support\MemberPortal;
use App\Support\TeachingDesk;
use App\Support\TimeSheet;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * The five papers a trainer works on for one cohort: the attendance register, a unit's marks, the timetable, the
 * lesson plan and the learning material.
 *
 * The office desk and an E-Fundi trainer's own portal screens run through this one class, so the same paper is
 * filled exactly the same way in both places — only the layout, the urls and who may open the cohort differ.
 */
abstract class TeachingPapers extends Controller
{
    protected string $layout = 'layouts.admin';

    /** The route name prefix this desk's screens are addressed by. */
    protected string $prefix = 'admin.teaching.';

    /** The cohorts this desk hands papers in for. */
    abstract protected function cohorts(): Collection;

    /** A cohort this desk cannot work on is not found, so no roster is reached by id. */
    abstract protected function gate(Cohort $cohort): void;

    protected function url(string $name, array $params = []): string
    {
        return route($this->prefix.$name, $params);
    }

    protected function view(string $screen, Cohort $cohort, array $data = [])
    {
        $cohort->loadMissing(['training.modules', 'venue:id,name,type,county', 'trainer']);

        return view('teaching.'.$screen, $data + [
            'layout' => $this->layout,
            'member' => MemberPortal::resolve(auth()->user()),
            'cohort' => $cohort,
            'screen' => $screen,
            'tabs' => $this->tabs($cohort),
            'url' => fn (string $name, array $params = []) => $this->url($name, $params),
            'mine' => $this->cohorts()->keyBy('id'),
            'roll' => $cohort->enrollments()->where('state', 'approved')->with('ctp')->orderBy('id')->get(),
        ]);
    }

    private function tabs(Cohort $cohort): array
    {
        $href = fn (string $screen) => $this->url($screen, ['cohort' => $cohort->id]);

        return [
            ['key' => 'register', 'label' => 'Attendance', 'href' => $href('register')],
            ['key' => 'marks', 'label' => 'Marks', 'href' => $href('marks')],
            ['key' => 'timetable', 'label' => 'Timetable', 'href' => $href('timetable')],
            ['key' => 'lessons', 'label' => 'Lesson plan', 'href' => $href('lessons')],
            ['key' => 'materials', 'label' => 'Material', 'href' => $href('materials')],
        ];
    }

    /** A register or a mark sheet is dated the day the class happened, never a day that has not come. */
    protected function guardDay(Cohort $cohort, string $date): void
    {
        abort_if($date > today()->toDateString(), 422,
            'A paper is dated the day the class happened, never a day that has not come yet.');

        $this->guardPeriod($cohort, $date);
    }

    /** A lesson plan is written for the teaching ahead, so its days run to the end of the cohort's period. */
    protected function guardPeriod(Cohort $cohort, string $date): void
    {
        if ($cohort->starts_on && $date < $cohort->starts_on->toDateString()) {
            abort(422, $cohort->reference.' runs from '.$cohort->starts_on->format('d M Y')
                .' — a paper dated '.$date.' falls before the training started.');
        }

        if ($cohort->ends_on && $date > $cohort->ends_on->toDateString()) {
            abort(422, $cohort->reference.' ended on '.$cohort->ends_on->format('d M Y')
                .' — nothing is recorded on a paper outside the period the office set.');
        }
    }

    /** Whose hand filled the paper: the posting that holds the cohort, or this office account when none does. */
    protected function actor(Cohort $cohort): array
    {
        $trainer = $cohort->trainer_id ? Trainer::find($cohort->trainer_id) : null;

        return [
            'trainer_id' => $trainer?->id,
            'taken_by_user' => match (true) {
                $trainer && $trainer->holder_type === 'staff' => $trainer->user_id,
                $trainer !== null => null,
                default => auth()->id(),
            },
            'taken_by_fundi' => $trainer?->fundi_id,
        ];
    }

    /** Forward a paper the desk just saved, checking it is this cohort's. */
    private function forward(Cohort $cohort, object $paper): string
    {
        abort_unless($paper->cohort_id === $cohort->id, 404);

        return TeachingDesk::forward($paper);
    }

    /** Who put a handout on the shelf — the same hand that fills the cohort's papers, on the columns they carry. */
    protected function uploader(Cohort $cohort): array
    {
        $actor = $this->actor($cohort);

        return ['uploaded_by_user' => $actor['taken_by_user'], 'uploaded_by_fundi' => $actor['taken_by_fundi']];
    }

    // ---------------------------------------------------------------- register

    public function register(Request $request, Cohort $cohort)
    {
        $this->gate($cohort);
        $week = TeachingDesk::week(TimeSheet::monthParam($request), $request->input('week'));
        $last = $week['start']->copy()->addDays(5)->toDateString();

        $held = CohortSession::where('cohort_id', $cohort->id)
            ->whereBetween('held_on', [$week['start']->toDateString(), $last])
            ->with('marks')->get()->groupBy(fn (CohortSession $s) => $s->held_on->toDateString());

        return $this->view('register', $cohort, [
            'week' => $week,
            'held' => $held,
            'kinds' => CohortSession::KINDS,
            'years' => TimeSheet::years(),
        ]);
    }

    /** Write one class day: the session itself and a tick per trainee. A day already forwarded is not rewritten here. */
    public function saveDay(Request $request, Cohort $cohort)
    {
        $this->gate($cohort);

        $data = $request->validate([
            'held_on' => 'required|date',
            'kind' => 'required|in:'.implode(',', array_keys(CohortSession::KINDS)),
            'topic' => 'nullable|string|max:190',
            'hours' => 'nullable|integer|min:0|max:16',
            'note' => 'nullable|string|max:500',
            'sheet' => 'nullable|file|mimes:pdf,jpg,jpeg,png,doc,docx|max:8192',
            'ticks' => 'nullable|array',
            'ticks.*' => 'boolean',
        ], [], [
            'held_on' => 'Day', 'kind' => 'Class', 'topic' => 'What was covered', 'hours' => 'Hours',
            'sheet' => 'Signed sheet',
        ]);

        $day = Carbon::parse($data['held_on']);
        $this->guardDay($cohort, $day->toDateString());

        $session = CohortSession::firstOrNew([
            'cohort_id' => $cohort->id, 'held_on' => $day->toDateString(), 'kind' => $data['kind'],
        ], $this->actor($cohort));

        abort_unless(! $session->exists || $session->isEditable(), 422,
            $session->statusLabel().' — a register the Registrar has already answered is corrected by them, not here.');

        if ($request->hasFile('sheet')) {
            $file = $request->file('sheet');
            $session->sheet_path = $file->store('training/'.$cohort->reference.'/register', 'local');
            $session->sheet_name = $file->getClientOriginalName();
        }

        $session->topic = $data['topic'] ?? null;
        $session->hours = $data['hours'] ?? 0;
        $session->note = $data['note'] ?? null;
        $session->save();

        $roll = $cohort->enrollments()->where('state', 'approved')->pluck('id');
        $here = $request->input('ticks');

        // A day written for its class details first keeps every tick already on it; the grid only replaces the
        // attendance when the grid itself was submitted.
        if (is_array($here)) {
            foreach ($roll as $id) {
                CohortSessionMark::updateOrCreate(
                    ['session_id' => $session->id, 'enrollment_id' => $id],
                    ['present' => ! empty($here[$id]), 'note' => $request->input('tick_note.'.$id) ?: null],
                );
            }
        }

        return back()->with('status', 'Attendance saved — '.Str::lower(CohortSession::KINDS[$data['kind']])
            .' on '.$day->format('D d M Y').'. Forward the day when it is complete.');
    }

    public function forwardDay(Cohort $cohort, CohortSession $session)
    {
        $this->gate($cohort);

        return back()->with('status', $this->forward($cohort, $session));
    }

    /** The signed sheet, opened only through the register it belongs to. */
    public function daySheet(Cohort $cohort, CohortSession $session)
    {
        $this->gate($cohort);
        abort_unless($session->cohort_id === $cohort->id && $session->sheet_path, 404);
        abort_unless(Storage::disk('local')->exists($session->sheet_path), 404, 'That file is no longer on record.');

        return Storage::disk('local')->response($session->sheet_path, $session->sheet_name ?: 'Signed sheet');
    }

    // ------------------------------------------------------------------- marks

    public function marks(Request $request, Cohort $cohort)
    {
        $this->gate($cohort);

        $sheets = UnitMarkSheet::where('cohort_id', $cohort->id)
            ->with(['module:id,title', 'marks'])->orderByDesc('covered_on')->orderByDesc('id')->get();

        $sheet = null;

        if ($request->filled('training_module_id') && $request->filled('covered_on')) {
            $day = Carbon::parse($request->input('covered_on'));
            $this->guardDay($cohort, $day->toDateString());

            $sheet = UnitMarkSheet::firstOrNew([
                'cohort_id' => $cohort->id, 'training_module_id' => $request->integer('training_module_id'),
                'covered_on' => $day->toDateString(),
            ]);

            if ($sheet->exists) {
                $sheet->load('marks');
            }
        }

        return $this->view('marks', $cohort, [
            'sheets' => $sheets,
            'sheet' => $sheet,
            'units' => $cohort->training?->modules ?? collect(),
            'kinds' => UnitMarkSheet::KINDS,
            'outOf' => UnitMarkSheet::OUT_OF,
        ]);
    }

    /** Save the sheet the grid was opened on, then write each mark typed into it. */
    public function saveSheet(Request $request, Cohort $cohort)
    {
        $this->gate($cohort);

        $data = UnitMarkSheet::dataFrom($request);
        $request->validate([
            'training_module_id' => 'required|exists:training_modules,id',
            'mark_note' => 'nullable|array',
            'mark_note.*.*' => 'nullable|string|max:190',
        ], [], ['training_module_id' => 'Unit']);

        $this->guardDay($cohort, $data['covered_on']);

        $sheet = UnitMarkSheet::firstOrNew([
            'cohort_id' => $cohort->id, 'training_module_id' => $request->integer('training_module_id'),
            'covered_on' => $data['covered_on'],
        ], $this->actor($cohort));

        abort_unless(! $sheet->exists || $sheet->isEditable(), 422,
            $sheet->statusLabel().' — marks already with the Registrar are corrected by them, not here.');

        $sheet->note = $data['note'] ?? $sheet->note;
        $sheet->save();

        $grid = UnitMark::gridFrom($request);
        $roll = $cohort->enrollments()->where('state', 'approved')->pluck('id')->all();
        $written = 0;

        foreach ($grid as $id => $row) {
            if (! in_array((int) $id, $roll, true)) {
                continue;
            }

            foreach ($row as $kind => $score) {
                if (! isset(UnitMarkSheet::KINDS[$kind])) {
                    continue;
                }

                UnitMark::updateOrCreate(
                    ['sheet_id' => $sheet->id, 'enrollment_id' => (int) $id, 'kind' => $kind],
                    ['score' => $score, 'note' => $request->input("mark_note.$id.$kind") ?: null],
                );
                $written++;
            }
        }

        return back()->with('status', $written.' '.Str::plural('mark', $written)
            .' saved on the sheet for '.$sheet->unitLabel().'. Forward it when the CTPs on it are in.');
    }

    public function forwardSheet(Cohort $cohort, UnitMarkSheet $sheet)
    {
        $this->gate($cohort);

        return back()->with('status', $this->forward($cohort, $sheet));
    }

    // --------------------------------------------------------------- timetable

    public function timetable(Request $request, Cohort $cohort)
    {
        $this->gate($cohort);

        $paper = Timetable::where('cohort_id', $cohort->id)->first();

        return $this->view('timetable', $cohort, [
            'paper' => $paper?->load('slots.module:id,title', 'slots'),
            'units' => $cohort->training?->modules ?? collect(),
            'days' => Timetable::DAYS,
            'kinds' => TimetableSlot::KINDS,
        ]);
    }

    /** The week is written whole: an edited timetable never leaves a class the trainer removed sitting behind. */
    public function saveTimetable(Request $request, Cohort $cohort)
    {
        $this->gate($cohort);

        $rows = TimetableSlot::rowsFrom($request);

        $paper = Timetable::firstOrNew(['cohort_id' => $cohort->id], $this->actor($cohort));
        abort_unless(! $paper->exists || $paper->isEditable(), 422,
            $paper->statusLabel().' — a timetable already with the Registrar is answered by them.');

        $paper->note = $request->input('note') ?: $paper->note;
        $paper->save();

        $paper->slots()->delete();

        foreach ($rows as $row) {
            $paper->slots()->create($row);
        }

        return back()->with('status', 'The week holds '.count($rows).' '.Str::plural('class', count($rows))
            .'. Forward it to the Registrar when it reads right.');
    }

    public function forwardTimetable(Cohort $cohort, Timetable $timetable)
    {
        $this->gate($cohort);

        return back()->with('status', $this->forward($cohort, $timetable));
    }

    // ------------------------------------------------------------ lesson plan

    public function lessons(Request $request, Cohort $cohort)
    {
        $this->gate($cohort);

        $paper = LessonPlan::where('cohort_id', $cohort->id)
            ->with(['lines.module:id,title', 'lines.materials'])->first();

        return $this->view('lessons', $cohort, [
            'plan' => $paper,
            'lines' => $paper?->lines ?? collect(),
            'units' => $cohort->training?->modules ?? collect(),
            'materialKinds' => TrainingMaterial::KINDS,
        ]);
    }

    /** One lesson on the plan. A lesson opened for editing is overwritten, never put beside itself. */
    public function saveLesson(Request $request, Cohort $cohort, ?LessonLine $line = null)
    {
        $this->gate($cohort);

        $data = LessonLine::dataFrom($request);

        if (! empty($data['planned_on'])) {
            $this->guardPeriod($cohort, Carbon::parse($data['planned_on'])->toDateString());
        }

        $plan = LessonPlan::firstOrNew(['cohort_id' => $cohort->id], $this->actor($cohort));

        abort_unless(! $plan->exists || $plan->isEditable(), 422,
            $plan->statusLabel().' — a plan already with the Registrar is answered by them.');

        $plan->save();

        if ($line) {
            abort_unless($line->lesson_plan_id === $plan->id, 404);
            $line->update($data);

            return back()->with('status', 'Lesson '.$line->position.' rewritten on the plan.');
        }

        $plan->lines()->create([...$data, 'position' => LessonLine::nextPosition($plan->id)]);

        return back()->with('status', 'Lesson '.$plan->lessonCount().' added to the plan.');
    }

    /** A lesson struck off is followed up by the ones behind it, so the plan still runs 1, 2, 3 to the last lesson. */
    public function removeLesson(Cohort $cohort, LessonLine $line)
    {
        $this->gate($cohort);

        $plan = $line->plan()->findOrFail();
        abort_unless($plan->isEditable(), 422, 'The Registrar answers a plan already forwarded.');
        abort_unless($line->lesson_plan_id === $plan->id, 404);

        $line->delete();

        foreach ($plan->lines()->orderBy('position')->orderBy('id')->get() as $i => $rest) {
            $rest->update(['position' => $i + 1]);
        }

        return back()->with('status', 'Lesson taken off the plan, and the lessons behind it moved up.');
    }

    public function forwardLesson(Cohort $cohort, LessonPlan $plan)
    {
        $this->gate($cohort);

        return back()->with('status', $this->forward($cohort, $plan));
    }

    // ---------------------------------------------------------------- material

    public function materials(Request $request, Cohort $cohort)
    {
        $this->gate($cohort);

        return $this->view('materials', $cohort, [
            'papers' => TrainingMaterial::where('cohort_id', $cohort->id)
                ->with(['module:id,title', 'lesson'])->orderByDesc('given_on')->orderByDesc('id')->get(),
            'units' => $cohort->training?->modules ?? collect(),
            'lessons' => LessonLine::whereHas('plan', fn ($q) => $q->where('cohort_id', $cohort->id))
                ->orderBy('position')->get(['id', 'position', 'topic', 'planned_on']),
            'kinds' => TrainingMaterial::KINDS,
        ]);
    }

    public function storeMaterial(Request $request, Cohort $cohort)
    {
        $this->gate($cohort);

        $data = TrainingMaterial::dataFrom($request);
        $file = $request->file('file');

        $paper = TrainingMaterial::create([
            'cohort_id' => $cohort->id,
            'training_module_id' => $data['training_module_id'] ?? null,
            'lesson_line_id' => $data['lesson_line_id'] ?? null,
            'given_on' => $data['given_on'] ?? today()->toDateString(),
            'title' => $data['title'],
            'kind' => $data['kind'],
            'note' => $data['note'] ?? null,
            'file_path' => $file->store('training/'.$cohort->reference.'/material', 'local'),
            'file_name' => $file->getClientOriginalName(),
            'file_size' => $file->getSize(),
        ] + $this->uploader($cohort));

        return back()->with('status', $paper->title.' is on the cohort’s shelf for every trainee to open.');
    }

    /** Replacing a handout overwrites it: the file the trainees open becomes the one saved now. */
    public function updateMaterial(Request $request, Cohort $cohort, TrainingMaterial $material)
    {
        $this->gate($cohort);
        abort_unless($material->cohort_id === $cohort->id, 404);

        $data = TrainingMaterial::dataFrom($request, false);
        $changes = collect($data)->except('file')->filter(fn ($value) => $value !== null)->all();

        if ($request->hasFile('file')) {
            Storage::disk('local')->delete($material->file_path);
            $file = $request->file('file');
            $changes['file_path'] = $file->store('training/'.$cohort->reference.'/material', 'local');
            $changes['file_name'] = $file->getClientOriginalName();
            $changes['file_size'] = $file->getSize();
        }

        $material->update($changes);

        return back()->with('status', $material->title.' rewritten.');
    }

    public function destroyMaterial(Cohort $cohort, TrainingMaterial $material)
    {
        $this->gate($cohort);
        abort_unless($material->cohort_id === $cohort->id, 404);

        Storage::disk('local')->delete($material->file_path);
        $title = $material->title;
        $material->delete();

        return back()->with('status', $title.' taken off the shelf.');
    }

    public function openMaterial(Cohort $cohort, TrainingMaterial $material)
    {
        $this->gate($cohort);
        abort_unless($material->cohort_id === $cohort->id, 404);
        abort_unless(Storage::disk('local')->exists($material->file_path), 404, 'That file is no longer on record.');

        return Storage::disk('local')->response($material->file_path, $material->file_name ?: $material->title);
    }
}
