<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Teaching\TeachingPapers;
use App\Models\Cohort;
use App\Models\CohortSession;
use App\Models\Enrollment;
use App\Models\LessonLine;
use App\Models\LibraryItem;
use App\Models\ModuleCompletion;
use App\Models\ProgressEntry;
use App\Models\Timetable;
use App\Models\TimetableSlot;
use App\Models\Training;
use App\Models\TrainingDocument;
use App\Models\TrainingMaterial;
use App\Models\TrainingModule;
use App\Models\UnitMark;
use App\Models\UnitMarkSheet;
use App\Models\User;
use App\Support\Departments;
use App\Support\Grading;
use App\Support\Library;
use App\Support\TeachingDesk;
use App\Support\TrainingTrack;
use App\Support\UploadsFromJson;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

/**
 * The trainer's five papers over the API — the same desk the office and an E-Fundi trainer's portal fill, answering
 * JSON instead of a page. Every read runs through TeachingPapers, so the week, the roster, the timetable's own hours
 * and the Registrar's answers are the ones already on the website; nothing here is a second set of rules.
 *
 * A non-E-Fundi trainer signs in with their office account: they get these papers and their own week sheet, and no
 * member screen. An E-Fundi who also trains gets both surfaces on one sign-in.
 */
class TrainerTeachingApiController extends TeachingPapers
{
    use UploadsFromJson;

    protected string $prefix = 'api.teaching.';

    /** A trainer sees the cohorts handed to them; the Training office sees every run it can record on. */
    protected function cohorts(): Collection
    {
        $mine = TeachingDesk::cohortsFor(auth()->user());

        if (Departments::canSee(auth()->user(), 'training') || auth()->user()->isSuperAdmin()) {
            return Cohort::where('status', '!=', 'draft')
                ->with('training:id,name', 'venue:id,name')
                ->orderByDesc('starts_on')->get();
        }

        return $mine->unique('id')->sortByDesc('starts_on')->values();
    }

    protected function gate(Cohort $cohort): void
    {
        TeachingDesk::theirCohort($cohort, auth()->user());
    }

    /** GET /api/teaching — the classes this account hands papers in for, with the paper count on each. */
    public function desk(Request $request): JsonResponse
    {
        $cohorts = $this->cohorts();

        return response()->json([
            'trainer' => $this->trainerName(),
            'office' => Departments::canSee($request->user(), 'training') || $request->user()->isSuperAdmin(),
            // Whose account this is: an office file has a staff profile to read, an E-Fundi's record does not.
            'staff' => in_array($request->user()->role, ['admin', 'superadmin'], true),
            'cohorts' => $cohorts->map(fn (Cohort $cohort) => $this->cohortJson($cohort))->values(),
            'note' => $cohorts->isEmpty()
                ? 'No class is handed to you yet. The office posts a trainer on a cohort before its papers open.'
                : null,
        ]);
    }

    // ------------------------------------------------------------------ papers

    /** POST /api/teaching/{cohort}/register — a class, its ticks, and the sheet the class signed. */
    public function saveDay(Request $request, Cohort $cohort)
    {
        $this->liftUploads($request, ['sheet']);

        return parent::saveDay($request, $cohort);
    }

    /** GET /api/teaching/{cohort}/register/{session}/sheet — the signed sheet, only through its own register. */
    public function daySheet(Request $request, Cohort $cohort, CohortSession $session): JsonResponse
    {
        $this->gate($cohort);
        abort_unless($session->cohort_id === $cohort->id && $session->sheet_path, 404);

        return response()->json($this->inline($session->sheet_path, $session->sheet_name ?: 'Signed sheet'));
    }

    /** POST /api/teaching/{cohort}/materials — a handout put on the cohort's shelf. */
    public function storeMaterial(Request $request, Cohort $cohort)
    {
        $this->liftUploads($request, ['file']);

        return parent::storeMaterial($request, $cohort);
    }

    /** PATCH /api/teaching/{cohort}/materials/{material} — a handout rewritten, its file overwritten when one is sent. */
    public function updateMaterial(Request $request, Cohort $cohort, TrainingMaterial $material)
    {
        $this->liftUploads($request, ['file']);

        return parent::updateMaterial($request, $cohort, $material);
    }

    /** GET /api/teaching/{cohort}/materials/{material}/open — the handout itself, sent inline. */
    public function openMaterial(Request $request, Cohort $cohort, TrainingMaterial $material): JsonResponse
    {
        $this->gate($cohort);
        abort_unless($material->cohort_id === $cohort->id && $material->file_path, 404,
            'That handout has no file on record.');

        return response()->json($this->inline($material->file_path, $material->file_name ?: $material->title));
    }

    // ------------------------------------------------------------------ library

    /**
     * GET /api/teaching/library — the Registrar's shelf for the programmes this account teaches, exactly as
     * /admin/teaching/library serves it. A trainer is never charged: reading and keeping a title are both open here.
     */
    public function library(Request $request): JsonResponse
    {
        $mine = TeachingDesk::courses($request->user());

        $kind = $request->input('kind');
        $kind = isset(LibraryItem::KINDS[$kind]) ? $kind : null;

        $course = $request->input('training_id');
        $course = $course === 'general' ? 'general'
            : (ctype_digit((string) $course) && in_array((int) $course, $mine, true) ? (int) $course : null);

        $items = Library::shelf($mine)->with(['course:id,name', 'poster:id,name'])
            ->when($course === 'general', fn ($q) => $q->whereNull('training_id'))
            ->when(is_int($course), fn ($q) => $q->where('training_id', $course))
            ->when($kind, fn ($q) => $q->where('kind', $kind))
            ->latest('id')->get();

        return response()->json([
            'items' => $items->map(fn (LibraryItem $item) => [
                'id' => $item->id,
                'title' => $item->title,
                'kind' => $item->kind,
                'kind_label' => $item->kindLabel(),
                'course' => $item->courseLabel(),
                'note' => $item->note,
                'posted_on' => $item->dateLabel(),
                'size' => $item->sizeLabel(),
                'has_file' => (bool) $item->file_path,
                'price' => '',
                'paid' => true,
            ])->values(),
            'courses' => Training::whereIn('id', $mine)->orderBy('name')->get(['id', 'name'])
                ->map(fn (Training $t) => ['id' => $t->id, 'name' => $t->name])->values(),
            'course' => $course,
            'kind' => $kind,
            'kinds' => LibraryItem::KINDS,
            'charges' => false,
        ]);
    }

    /** GET /api/teaching/library/{item} — the file itself, handed to a trainer without a payment. */
    public function libraryFile(Request $request, LibraryItem $item): JsonResponse
    {
        abort_unless($item->status === 'on', 404, 'That reading is off the shelf.');
        abort_unless(Library::reads($item, TeachingDesk::courses($request->user())), 403,
            $item->title.' is filed under '.$item->courseLabel().', a programme you do not teach. '
            .'The office lists the programmes you are licensed for on the Trainers register.');
        abort_unless($item->file_path, 404, 'That title has no file on record.');

        return response()->json($this->inline($item->file_path, $item->file_name ?: $item->title));
    }

    // ------------------------------------------------------- a trainee's record

    /**
     * GET /api/teaching/trainee/{enrollment} — one trainee's record, the page the desk reads before it marks.
     * The marks sheet, the track, the modules and the paperwork all come from the same models the web partials use,
     * so a phone never shows a trainee a different total from the class's own sheet.
     */
    public function trainee(Request $request, Enrollment $enrollment): JsonResponse
    {
        $seat = $this->theirTrainee($enrollment);
        $seat->load(['ctp', 'entries', 'moduleMarks', 'documents.module', 'certification', 'cohort.venue']);

        $units = $seat->unitList();
        $board = $seat->moduleBoard();
        $held = $seat->state === 'approved';
        $days = $seat->entries->where('kind', 'attendance');

        return response()->json([
            'screen' => 'trainee',
            'seat' => [
                'id' => (int) $seat->id,
                'name' => $seat->ctp?->full_name ?: 'Trainee '.$seat->id,
                'code' => $seat->ctp?->ctp_code,
                'state_label' => $seat->stateLabel(),
                'stage_label' => $seat->stageLabel(),
                'held' => $held,
                'attendance' => $seat->attendancePct(),
                'attendance_min' => Enrollment::ATTENDANCE_MINIMUM,
                'track' => $seat->trackPct(),
                'days_marked' => $days->count(),
                'days_present' => $days->where('present', true)->count(),
            ],
            'cohort' => $seat->cohort ? $this->cohortJson($seat->cohort) : null,
            'marks' => $units->map(function (TrainingModule $unit) use ($seat) {
                $grade = Grading::compile($seat, $unit);
                $grading = Grading::for($unit);

                return [
                    'unit' => $unit->title,
                    'term' => $unit->termLabel(),
                    'note' => $grading->note,
                    'parts' => collect($grade['parts'])->map(fn (array $part) => [
                        'label' => $part['label'],
                        'score' => $part['score'],
                        'weight' => $part['weight'],
                    ])->values(),
                    'pct' => $grade['pct'],
                    'letter' => $grade['letter'],
                    'marked' => $grade['marked'],
                    'passed' => $grade['passed'],
                    'pass_pct' => $grade['pass_pct'],
                    'remark' => $grade['remark'],
                    'on_sheet' => round(collect($grade['parts'])->except('attendance')->sum('got'), 1),
                ];
            })->values(),
            'parts' => collect($seat->trackParts())->map(fn (array $part) => [
                'label' => $part['label'], 'got' => $part['got'], 'weight' => $part['weight'],
                'detail' => $part['detail'], 'done' => (bool) $part['done'],
            ])->values(),
            'modules' => $board['modules']->map(function (TrainingModule $unit) use ($board) {
                $mark = $board['marked']->get($unit->id);

                return [
                    'id' => (int) $unit->id,
                    'position' => (int) $unit->position,
                    'title' => $unit->title,
                    'hours' => $unit->hoursLabel(),
                    'outcome' => $unit->outcome,
                    'marked_on' => $mark?->done_on?->format('d M Y'),
                    'marked_by' => $mark?->recorderLabel(),
                    'marked_note' => $mark?->note,
                ];
            })->values(),
            'documents' => $seat->documents->sortByDesc('dated_on')->map(fn (TrainingDocument $paper) => [
                'id' => (int) $paper->id,
                'title' => $paper->title,
                'kind_label' => $paper->kindLabel(),
                'dated' => $paper->dated_on?->format('d M Y'),
                'size' => $paper->sizeLabel(),
                'unit' => $paper->module?->title,
                'note' => $paper->note,
                'has_file' => (bool) $paper->file_path,
            ])->values(),
            'cert' => $seat->certification ? [
                'code' => $seat->certification->certificate_code,
                'status_label' => $seat->certification->statusLabel(),
                'reason' => $seat->certification->reason,
            ] : null,
            'units' => $this->unitJson($units),
            'progress_kinds' => ProgressEntry::KINDS,
            'document_kinds' => TrainingDocument::KINDS,
            'period' => $seat->cohort?->dateLabel(),
            'starts_on' => $seat->cohort?->starts_on?->toDateString(),
            'ends_on' => $seat->cohort?->ends_on?->toDateString(),
            'can_write' => $held,
        ]);
    }

    /** POST /api/teaching/trainee/{enrollment}/progress — one line on the trainee's own track. */
    public function saveProgress(Request $request, Enrollment $enrollment): JsonResponse
    {
        $seat = $this->theirTrainee($enrollment);

        return $this->done(TrainingTrack::recordProgress($seat, $request, $this->recorder($seat)));
    }

    /** POST /api/teaching/trainee/{enrollment}/modules/{module} — a module the trainee has got through. */
    public function markModule(Request $request, Enrollment $enrollment, TrainingModule $module): JsonResponse
    {
        $seat = $this->theirTrainee($enrollment);

        return $this->done(TrainingTrack::markModule($seat, $module, ModuleCompletion::dataFrom($request),
            $this->recorder($seat)));
    }

    /** DELETE /api/teaching/trainee/{enrollment}/modules/{module} — the mark taken off again. */
    public function unmarkModule(Enrollment $enrollment, TrainingModule $module): JsonResponse
    {
        $seat = $this->theirTrainee($enrollment);

        return $this->done(TrainingTrack::unmarkModule($seat, $module));
    }

    /** POST /api/teaching/trainee/{enrollment}/documents — a school report or a photo of the board they built. */
    public function storeDocument(Request $request, Enrollment $enrollment)
    {
        $this->liftUploads($request, ['file']);
        $seat = $this->theirTrainee($enrollment);

        return $this->done(TrainingTrack::storeDocument($seat, $request, $this->recorder($seat)));
    }

    /** GET /api/teaching/trainee/{enrollment}/documents/{document} — the paper itself, opened from their record. */
    public function document(Request $request, Enrollment $enrollment, TrainingDocument $paper): JsonResponse
    {
        $seat = $this->theirTrainee($enrollment);
        abort_unless($paper->enrollment_id === $seat->id && $paper->file_path, 404,
            'That document has no file on record.');

        return response()->json($this->inline($paper->file_path, $paper->file_name ?: $paper->title));
    }

    /** A trainee outside a class this account papers for 404s, so no roster is reached by id. */
    private function theirTrainee(Enrollment $enrollment): Enrollment
    {
        $enrollment->loadMissing('cohort');
        abort_unless($enrollment->cohort, 404);
        $this->gate($enrollment->cohort);

        return $enrollment;
    }

    /** Whose hand put the line down — the same hand the papers are filed under. */
    private function recorder(Enrollment $enrollment): array
    {
        $who = $this->actor($enrollment->cohort);

        return array_filter([
            'recorded_by_fundi' => $who['taken_by_fundi'],
            'recorded_by_user' => $who['taken_by_user'],
        ], fn ($value) => $value !== null);
    }

    // ------------------------------------------------------------------ seams

    /** A paper written from the phone answers a sentence, not a page. */
    protected function done(string $message)
    {
        return response()->json(['message' => $message]);
    }

    /** The screen a paper opens on, as data: the class, its roster and whatever that paper holds. */
    protected function view(string $screen, Cohort $cohort, array $data = [])
    {
        $cohort->loadMissing(['venue:id,name,type,county', 'training:id,name']);
        $roll = $cohort->enrollments()->where('state', 'approved')->with('ctp')->orderBy('id')->get();

        $payload = [
            'screen' => $screen,
            'cohort' => $this->cohortJson($cohort),
            'roll' => $this->rollJson($roll),
            'units' => $this->unitJson($data['units'] ?? collect()),
            'programme_week' => $this->weekJson($cohort->training_id
                ? Timetable::forProgramme((int) $cohort->training_id)
                : null),
        ];

        return response()->json(match ($screen) {
            'register' => $payload + [
                'week' => $this->calendarJson($data['week']),
                'kinds' => CohortSession::KINDS,
                'on_the_week' => $data['onTheWeek'],
                'classes' => collect($data['classes'])->map(fn (array $row) => [
                    'date' => $row['date'],
                    'day' => $row['day']->format('D d M Y'),
                    'time' => $row['time'],
                    'hours' => $row['hours'],
                    'kind' => $row['kind'],
                    'kind_label' => CohortSession::KINDS[$row['kind']] ?? $row['kind'],
                    'unit_id' => $row['unit_id'],
                    'unit' => $row['unit'],
                    'room' => $row['room'],
                    'owed' => (bool) ($row['owed'] ?? false),
                    'off_timetable' => (bool) ($row['off_timetable'] ?? false),
                    'session' => $row['session'] ? $this->sessionJson($row['session']) : null,
                ])->values(),
            ],

            'marks' => $payload + [
                'sheets' => $data['sheets']->map(fn (UnitMarkSheet $sheet) => $this->sheetJson($sheet))->values(),
                'sheet' => $data['sheet'] ? $this->sheetJson($data['sheet']) : null,
                'kinds' => UnitMarkSheet::KINDS,
                'out_of' => UnitMarkSheet::OUT_OF,
                'grading' => $data['grading'] ? [
                    'weights' => $data['grading']->weights(),
                    'pass_pct' => (float) $data['grading']->pass_pct,
                    'note' => $data['grading']->note,
                ] : null,
                'can_correct' => $data['canCorrect'],
            ],

            'timetable' => $payload + [
                'paper' => $data['paper'] ? $this->timetableJson($data['paper']) : null,
                'days' => Timetable::DAYS,
                'kinds' => TimetableSlot::KINDS,
            ],

            'lessons' => $payload + [
                'plan' => $data['plan'] ? $this->paperState($data['plan']) + [
                    'id' => $data['plan']->id,
                    'note' => $data['plan']->note,
                    'lessons' => $data['plan']->lessonCount(),
                ] : null,
                'lines' => collect($data['lines'])->map(fn (LessonLine $line) => $this->lessonJson($line))->values(),
                'can_write' => $data['canWrite'],
                'plan_note' => $data['planNote'],
                'material_kinds' => $data['materialKinds'],
            ],

            'materials' => $payload + [
                'papers' => $data['papers']->map(fn (TrainingMaterial $paper) => $this->materialJson($paper))->values(),
                'lessons' => $data['lessons']->map(fn (LessonLine $line) => [
                    'id' => $line->id, 'position' => $line->position, 'topic' => $line->topic,
                    'planned_on' => $line->planned_on?->toDateString(),
                ])->values(),
                'kinds' => $data['kinds'],
            ],

            default => $payload,
        });
    }

    // -------------------------------------------------------------- shapes

    private function trainerName(): string
    {
        $user = auth()->user();

        return $user instanceof User ? $user->name : '';
    }

    private function cohortJson(Cohort $cohort): array
    {
        $cohort->loadMissing(['venue:id,name,type,county', 'training:id,name']);

        return [
            'id' => $cohort->id,
            'reference' => $cohort->reference,
            'course' => $cohort->courseLabel(),
            'programme' => $cohort->training?->name,
            'venue' => $cohort->venue?->name,
            'place' => $cohort->placeLabel(),
            'period' => $cohort->periodLabel(),
            'term' => $cohort->termLabel(),
            'status' => $cohort->status,
            'status_label' => $cohort->statusLabel(),
            'stage' => $cohort->stageLabel(),
            'starts_on' => $cohort->starts_on?->toDateString(),
            'ends_on' => $cohort->ends_on?->toDateString(),
            'seats' => $cohort->seatsUsed(),
            'units' => $cohort->unitList()->count(),
        ];
    }

    private function rollJson(Collection $roll): array
    {
        return $roll->map(fn (Enrollment $seat) => [
            'id' => (int) $seat->id,
            'name' => $seat->ctp?->full_name ?: 'Trainee '.$seat->id,
            'reference' => $seat->ctp?->reference,
        ])->values()->all();
    }

    private function unitJson(Collection $units): array
    {
        return $units->map(fn ($unit) => [
            'id' => (int) $unit->id,
            'title' => $unit->title,
            'term' => $unit->termLabel(),
        ])->values()->all();
    }

    /** The Registrar's week for the programme, read beside the week the trainer writes. */
    private function weekJson(?Timetable $paper): ?array
    {
        return $paper ? $this->timetableJson($paper) : null;
    }

    private function calendarJson(array $week): array
    {
        return [
            'start' => $week['start']->toDateString(),
            'label' => $week['label'],
            'month' => $week['month'],
            'week_no' => $week['week_no'],
            'days' => collect($week['days'])->map(fn ($day) => [
                'date' => $day->toDateString(),
                'day' => $day->format('l'),
                'label' => $day->format('D d M Y'),
            ])->values(),
            'weeks' => collect($week['weeks'])->map(fn (array $row) => [
                'label' => $row['label'], 'dates' => $row['dates'],
                'start' => $row['start']->toDateString(), 'end' => $row['end']->toDateString(),
            ])->values(),
        ];
    }

    /** Where a paper stands with the Registrar — the same four states every desk reads. */
    private function paperState(object $paper): array
    {
        return [
            'status' => $paper->status ?? 'draft',
            'status_label' => $paper->statusLabel(),
            'editable' => $paper->isEditable(),
            'review_note' => $paper->review_note ?? null,
            'submitted_at' => $paper->submitted_at?->toDateTimeString(),
        ];
    }

    private function sessionJson(CohortSession $session): array
    {
        $session->loadMissing(['marks', 'module:id,title']);

        return $this->paperState($session) + [
            'id' => $session->id,
            'held_on' => $session->held_on->toDateString(),
            'date_label' => $session->dateLabel(),
            'kind' => $session->kind,
            'kind_label' => $session->kindLabel(),
            'unit_id' => $session->training_module_id,
            'unit' => $session->unitLabel(),
            'topic' => $session->topic,
            'hours' => (float) $session->hours,
            'hours_label' => $session->hoursLabel(),
            'note' => $session->note,
            'present' => $session->presentCount(),
            'has_sheet' => (bool) $session->sheet_path,
            'sheet_name' => $session->sheet_name,
            'ticks' => $session->marks->mapWithKeys(fn ($mark) => [(int) $mark->enrollment_id => (bool) $mark->present])->all(),
            'tick_notes' => $session->marks->filter(fn ($mark) => filled($mark->note))
                ->mapWithKeys(fn ($mark) => [(int) $mark->enrollment_id => $mark->note])->all(),
        ];
    }

    private function sheetJson(UnitMarkSheet $sheet): array
    {
        $sheet->loadMissing(['marks', 'module:id,title']);

        return $this->paperState($sheet) + [
            'id' => $sheet->id,
            'unit_id' => $sheet->training_module_id,
            'unit' => $sheet->unitLabel(),
            'covered_on' => $sheet->covered_on?->toDateString(),
            'date_label' => $sheet->dateLabel(),
            'note' => $sheet->note,
            'marks' => $sheet->marks->map(fn (UnitMark $mark) => [
                'enrollment_id' => (int) $mark->enrollment_id,
                'kind' => $mark->kind,
                'score' => $mark->score === null ? null : (float) $mark->score,
                'note' => $mark->note,
            ])->values(),
            'written' => $sheet->marks->count(),
        ];
    }

    private function timetableJson(Timetable $paper): array
    {
        $paper->loadMissing(['slots.module:id,title', 'slots.lecturer']);

        return $this->paperState($paper) + [
            'id' => $paper->id,
            'note' => $paper->note,
            'own' => (bool) $paper->cohort_id,
            'slots' => $paper->slots->sortBy('starts_at')->map(fn (TimetableSlot $slot) => [
                'id' => $slot->id,
                'day' => $slot->day,
                'day_label' => $slot->dayLabel(),
                'starts_at' => substr((string) $slot->starts_at, 0, 5),
                'ends_at' => substr((string) $slot->ends_at, 0, 5),
                'time' => $slot->timeLabel(),
                'hours' => $slot->hours(),
                'hours_label' => $slot->hoursLabel(),
                'kind' => $slot->kind,
                'kind_label' => $slot->kindLabel(),
                'unit_id' => $slot->training_module_id,
                'unit' => $slot->module?->title,
                'room' => $slot->room,
                'lecturer' => $slot->lecturerLabel(),
            ])->values(),
        ];
    }

    private function lessonJson(LessonLine $line): array
    {
        $line->loadMissing(['module:id,title', 'materials.module:id,title']);

        return [
            'id' => $line->id,
            'position' => $line->position,
            'unit_id' => $line->training_module_id,
            'unit' => $line->module?->title,
            'planned_on' => $line->planned_on?->toDateString(),
            'date_label' => $line->dateLabel(),
            'starts_at' => substr((string) $line->starts_at, 0, 5) ?: null,
            'ends_at' => substr((string) $line->ends_at, 0, 5) ?: null,
            'time' => $line->timeLabel(),
            'topic' => $line->topic,
            'summary' => $line->summary,
            'objectives' => $line->objectives,
            'activities' => $line->activities,
            'resources' => $line->resources,
            'assessment_note' => $line->assessment_note,
            'materials' => $line->materials->map(fn (TrainingMaterial $paper) => [
                'id' => $paper->id, 'title' => $paper->title, 'kind_label' => $paper->kindLabel(),
            ])->values(),
        ];
    }

    private function materialJson(TrainingMaterial $paper): array
    {
        $paper->loadMissing(['module:id,title', 'lesson']);

        return [
            'id' => $paper->id,
            'title' => $paper->title,
            'kind' => $paper->kind,
            'kind_label' => $paper->kindLabel(),
            'unit_id' => $paper->training_module_id,
            'unit' => $paper->module?->title,
            'lesson_id' => $paper->lesson_line_id,
            'lesson' => $paper->lesson ? 'Lesson '.$paper->lesson->position.' — '.$paper->lesson->topic : null,
            'given_on' => $paper->given_on?->toDateString(),
            'date_label' => $paper->dateLabel(),
            'note' => $paper->note,
            'file_name' => $paper->file_name,
            'size_label' => $paper->sizeLabel(),
        ];
    }
}
