<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Cohort;
use App\Models\CtpRegistration;
use App\Models\Enrollment;
use App\Models\LibraryItem;
use App\Models\Timetable;
use App\Models\TrainingMaterial;
use App\Models\UnitMarkSheet;
use App\Support\MemberPortal;
use App\Support\UploadsFromJson;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * My class and My library, served as JSON for the mobile app. Every query is the one the portal controller of the
 * same name runs — the same seat guard, the same approved-paper filter and the same entitlement on a library title —
 * so the phone cannot be shown a class or a reading the website would refuse. Handouts open as the file itself, read
 * off the private disk and handed over inline.
 */
class MemberClassApiController extends Controller
{
    use UploadsFromJson;

    /** GET /api/me/class — the classes this trainee holds an approved seat in. */
    public function classes(): JsonResponse
    {
        $seats = $this->seats($this->ctp())->get();

        return response()->json([
            'seats' => $seats->map(fn (Enrollment $e) => [
                'cohort_id' => $e->cohort_id,
                'reference' => $e->cohort?->reference,
                'course' => $e->cohort?->courseLabel(),
                'place' => $e->cohort?->placeLabel(),
                'period' => $e->cohort?->periodLabel(),
                'dates' => $e->cohort?->dateLabel(),
            ])->values(),
        ]);
    }

    /** GET /api/me/class/{cohort} — one class: the approved week, the units with their marks, the handouts. */
    public function show(Cohort $cohort): JsonResponse
    {
        $seat = $this->seat($cohort);

        $timetable = Timetable::where('cohort_id', $cohort->id)->where('status', 'approved')
            ->with('slots.module:id,title')->latest('id')->first();

        $marksByUnit = $this->marksByUnit($cohort, $seat);

        return response()->json([
            'class' => [
                'id' => $cohort->id,
                'reference' => $cohort->reference,
                'course' => $cohort->courseLabel(),
                'place' => $cohort->venue?->name ?: 'No venue set',
                'period' => $cohort->periodLabel(),
                'dates' => $cohort->dateLabel(),
                'trainer' => $cohort->trainer?->personName() ?: 'the office',
                'track_pct' => round($seat->trackPct(), 1),
            ],
            'timetable_on' => $timetable?->reviewed_at?->format('d M Y'),
            'week' => collect($timetable?->week() ?? [])->map(fn (array $day) => [
                'label' => $day['label'],
                'slots' => collect($day['slots'])->map(fn ($slot) => [
                    'time' => $slot->timeLabel(),
                    'class' => $slot->kindLabel(),
                    'unit' => $slot->module?->title,
                    'room' => $slot->room,
                ])->values(),
            ])->values(),
            'units' => $cohort->training?->modules->map(fn ($unit) => [
                'position' => $unit->position,
                'title' => $unit->title,
                'covered_on' => $marksByUnit[$unit->id]['on'] ?? null,
                'marks' => collect($marksByUnit[$unit->id]['marks'] ?? [])
                    ->map(fn ($score, $kind) => ['kind' => $kind, 'label' => UnitMarkSheet::KINDS[$kind] ?? $kind, 'score' => $score])
                    ->values(),
            ])->values() ?? collect(),
            'materials' => $this->materials($cohort)->map(fn (TrainingMaterial $m) => $this->material($m))->values(),
            'seats' => $this->seats($this->ctp())->get()
                ->map(fn (Enrollment $e) => ['cohort_id' => $e->cohort_id, 'label' => $e->cohort?->reference.' — '.$e->cohort?->courseLabel()])
                ->values(),
        ]);
    }

    /** GET /api/me/class/{cohort}/material/{material} — a handout, only through the seat that holds the class. */
    public function materialFile(Cohort $cohort, TrainingMaterial $material): JsonResponse
    {
        $this->seat($cohort);
        abort_unless($material->cohort_id === $cohort->id, 404);
        abort_unless($material->file_path, 404, 'That handout has no file on record.');

        return response()->json($this->inline($material->file_path, $material->file_name ?: 'Handout'));
    }

    /** GET /api/me/library — the reading filed for this trainee's courses, plus what is for every student. */
    public function library(Request $request): JsonResponse
    {
        $ctp = $this->ctp();
        $courses = $this->courses($ctp);

        $kind = $request->input('kind');
        $kind = isset(LibraryItem::KINDS[$kind]) ? $kind : null;

        $course = $request->input('training_id');
        $course = $course === 'general' ? 'general'
            : (ctype_digit((string) $course) && $courses->contains('id', (int) $course) ? (int) $course : null);

        $items = LibraryItem::onShelf()->with('course:id,name')
            ->when($courses->isNotEmpty(), fn ($q) => $q->where(fn ($w) => $w
                ->whereNull('training_id')->orWhereIn('training_id', $courses->pluck('id'))))
            ->when($courses->isEmpty(), fn ($q) => $q->whereNull('training_id'))
            ->when($course === 'general', fn ($q) => $q->whereNull('training_id'))
            ->when(is_int($course), fn ($q) => $q->where('training_id', $course))
            ->when($kind, fn ($q) => $q->where('kind', $kind))
            ->latest('id')->get();

        return response()->json([
            'items' => $items->map(fn (LibraryItem $i) => [
                'id' => $i->id,
                'title' => $i->title,
                'kind' => $i->kind,
                'kind_label' => $i->kindLabel(),
                'course' => $i->courseLabel(),
                'note' => $i->note,
                'posted_on' => $i->dateLabel(),
                'size' => $i->sizeLabel(),
                'has_file' => (bool) $i->file_path,
            ])->values(),
            'courses' => $courses->map(fn ($t) => ['id' => $t->id, 'name' => $t->name])->values(),
            'course' => $course,
            'kind' => $kind,
            'kinds' => LibraryItem::KINDS,
        ]);
    }

    /** GET /api/me/library/{item} — one title, only for a student whose course it is filed under. */
    public function libraryFile(LibraryItem $item): JsonResponse
    {
        $courses = $this->courses($this->ctp());

        abort_unless($item->status === 'on', 404, 'That reading is off the shelf.');
        abort_unless($item->readsFor(null) || $courses->contains('id', $item->training_id), 403,
            'That title is filed under another course.');
        abort_unless($item->file_path, 404, 'That title has no file on record.');

        return response()->json($this->inline($item->file_path, $item->file_name ?: $item->title));
    }

    // ---- the portal's own queries ---------------------------------------

    private function ctp(): CtpRegistration
    {
        $member = MemberPortal::resolve(auth()->user());

        abort_if(! $member, 403, 'Your member record could not be found. Contact the programme office.');
        abort_unless($member instanceof CtpRegistration, 403,
            'The class and the library are a trainee\'s own screens. An E-Fundi is given material by their trainer.');

        return $member;
    }

    /** The seats this CTP holds that are running — an application is not a class. */
    private function seats(CtpRegistration $ctp)
    {
        return $ctp->enrollments()->where('state', 'approved')
            ->with(['cohort:id,reference,training_id,venue_id,trainer_id,starts_on,ends_on,academic_year,term,status',
                'cohort.training:id,name', 'cohort.venue:id,name', 'certification'])
            ->latest('id');
    }

    private function seat(Cohort $cohort): Enrollment
    {
        $seat = $this->seats($this->ctp())->where('cohort_id', $cohort->id)->first();

        abort_unless($seat !== null, 403, 'You do not hold a seat in this class.');

        return $seat;
    }

    private function materials(Cohort $cohort)
    {
        return TrainingMaterial::where('cohort_id', $cohort->id)
            ->with(['module:id,title', 'lesson:id,topic', 'uploaderFundi:id,full_name'])
            ->orderByDesc('given_on')->orderBy('id')->get();
    }

    /** Each unit as this trainee stands in it: the day the sheet was covered and the marks counted from it. */
    private function marksByUnit(Cohort $cohort, Enrollment $seat): array
    {
        $marked = UnitMarkSheet::where('cohort_id', $cohort->id)->where('status', 'approved')->with('marks')->get();

        $byUnit = [];
        foreach ($marked as $sheet) {
            $row = $byUnit[$sheet->training_module_id] ?? ['on' => null, 'marks' => []];
            $row['on'] = $sheet->covered_on?->format('d M Y') ?: $row['on'];

            foreach ($sheet->marks->where('enrollment_id', $seat->id) as $mark) {
                if ($mark->score !== null) {
                    $row['marks'][$mark->kind] = $mark->score;
                }
            }

            $byUnit[$sheet->training_module_id] = $row;
        }

        return $byUnit;
    }

    /** The trainings this CTP is actually sitting in class for — an application is not a class. */
    private function courses(CtpRegistration $ctp)
    {
        return $ctp->enrollments()->where('state', 'approved')
            ->with('cohort.training:id,name')->get()
            ->pluck('cohort.training')->filter()->unique('id')->values();
    }

    private function material(TrainingMaterial $m): array
    {
        return [
            'id' => $m->id,
            'title' => $m->title,
            'kind_label' => $m->kindLabel(),
            'unit' => $m->module?->title,
            'lesson' => $m->lesson?->topic,
            'given_on' => $m->dateLabel(),
            'size' => $m->sizeLabel(),
            'given_by' => $m->uploaderFundi?->full_name,
            'note' => $m->note,
            'has_file' => (bool) $m->file_path,
        ];
    }
}
