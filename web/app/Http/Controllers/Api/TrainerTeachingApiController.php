<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Teaching\TeachingPapers;
use App\Models\Cohort;
use App\Models\CohortSession;
use App\Models\Enrollment;
use App\Models\LessonLine;
use App\Models\Timetable;
use App\Models\TimetableSlot;
use App\Models\TrainingMaterial;
use App\Models\UnitMark;
use App\Models\UnitMarkSheet;
use App\Models\User;
use App\Support\Departments;
use App\Support\TeachingDesk;
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
