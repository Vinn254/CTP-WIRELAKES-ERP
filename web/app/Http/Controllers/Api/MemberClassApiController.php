<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Cohort;
use App\Models\CtpRegistration;
use App\Models\Enrollment;
use App\Models\LibraryItem;
use App\Models\Timetable;
use App\Models\TrainingMaterial;
use App\Models\UnitGrading;
use App\Models\UnitMarkSheet;
use App\Support\Documents;
use App\Support\Finance;
use App\Support\Grading;
use App\Support\Library;
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

    /** GET /api/me/class/{cohort} — one class: the approved week, the units with their lecturer and marks, the handouts. */
    public function show(Cohort $cohort): JsonResponse
    {
        $ctp = $this->ctp();
        $seat = $this->seat($cohort);

        // A trainee follows their own programme, the same as on the website.
        $programme = (int) ($ctp->training_id ?: $cohort->training_id);

        $timetable = Timetable::where('cohort_id', $cohort->id)->where('status', 'approved')
            ->when($programme, fn ($q) => $q->where(fn ($w) => $w->whereNull('training_id')->orWhere('training_id', $programme)))
            ->with(Timetable::WEEK_WITH)->latest('id')->first()
            ?? ($programme ? Timetable::forProgramme($programme) : null)
            ?? Timetable::where('cohort_id', $cohort->id)->where('status', 'approved')
                ->with(Timetable::WEEK_WITH)->latest('id')->first();

        $marksByUnit = $this->marksByUnit($cohort, $seat);

        // What the office handed this student, or the units their programme carries on this intake.
        $units = $seat->unitList();

        $hands = $cohort->handsMap();
        $tutored = $units->flatMap(fn ($unit) => $hands[$unit->id] ?? collect())->unique(fn ($t) => $t->id);

        $marked = $units->map(function ($unit) use ($seat) {
            return ['unit' => $unit, 'grade' => Grading::compile($seat, $unit)];
        });

        $overall = Grading::overall($marked->pluck('grade')->filter(fn (array $g) => $g['marked'])->values()->all());

        return response()->json([
            'class' => [
                'id' => $cohort->id,
                'reference' => $cohort->reference,
                'course' => $ctp->programme?->name ?: $cohort->courseLabel(),
                'place' => $cohort->venue?->name ?: 'No venue set',
                'period' => $cohort->periodLabel(),
                'dates' => $cohort->dateLabel(),
                'trainer' => match (true) {
                    $tutored->count() === 1 => $tutored->first()->personName(),
                    $tutored->count() > 1 => $tutored->count().' lecturers on your units',
                    default => $cohort->trainer?->personName() ?: 'the office',
                },
                'track_pct' => round($seat->trackPct(), 1),
                'grade_pct' => $overall,
                'grade_letter' => $overall === null ? null : Grading::letter($overall),
                'units_source' => $seat->unitSource(),
                'units_note' => $seat->unitSourceLabel(),
                'certificate' => $seat->certification?->isLive() ? [
                    'code' => $seat->certification->certificate_code,
                    'on' => $seat->certification->approved_on?->toDateString(),
                ] : null,
            ],
            'timetable_on' => $timetable?->reviewed_at?->format('d M Y'),
            'timetable_from' => $timetable ? ($timetable->cohort_id ? 'cohort' : 'programme') : null,
            'week' => collect($timetable?->week() ?? [])->map(fn (array $day) => [
                'label' => $day['label'],
                'slots' => collect($day['slots'])->map(fn ($slot) => [
                    'time' => $slot->timeLabel(),
                    'class' => $slot->kindLabel(),
                    'unit' => $slot->module?->title,
                    'lecturer' => $slot->lecturer?->personName(),
                    'room' => $slot->room,
                ])->values(),
            ])->values(),
            'units' => $marked->map(function (array $row) use ($marksByUnit, $hands) {
                $unit = $row['unit'];
                $grade = $row['grade'];

                return [
                    'position' => $unit->position,
                    'title' => $unit->title,
                    'term' => $unit->termLabel(),
                    'lecturer' => ($hands[$unit->id] ?? collect())->map->personName()->filter()->join(', ') ?: null,
                    'covered_on' => $marksByUnit[$unit->id]['on'] ?? null,
                    'marks' => collect(UnitGrading::PARTS)->map(fn (string $label, string $part) => [
                        'kind' => $part,
                        'label' => $label,
                        'weight' => $grade['parts'][$part]['weight'],
                        'score' => $grade['parts'][$part]['score'],
                    ])->values(),
                    'pct' => $grade['marked'] ? $grade['pct'] : null,
                    'letter' => $grade['marked'] ? $grade['letter'] : null,
                    'pass_pct' => $grade['pass_pct'],
                    'passed' => $grade['passed'],
                    'remark' => $grade['remark'],
                ];
            })->values(),
            'materials' => $this->materials($cohort, $units)->map(fn (TrainingMaterial $m) => $this->material($m))->values(),
            'seats' => $this->seats($ctp)->get()
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

    /** GET /api/me/library — the reading filed for this trainee's own programmes, plus what is for every course. */
    public function library(Request $request): JsonResponse
    {
        $ctp = $this->ctp();
        $mine = MemberPortal::readings($ctp);

        $kind = $request->input('kind');
        $kind = isset(LibraryItem::KINDS[$kind]) ? $kind : null;

        $course = $request->input('training_id');
        $course = $course === 'general' ? 'general'
            : (ctype_digit((string) $course) && $mine->contains('id', (int) $course) ? (int) $course : null);

        $items = Library::shelf($mine->pluck('id')->all())->with('course:id,name')
            ->when($course === 'general', fn ($q) => $q->whereNull('training_id'))
            ->when(is_int($course), fn ($q) => $q->where('training_id', $course))
            ->when($kind, fn ($q) => $q->where('kind', $kind))
            ->latest('id')->get();

        // Reading is free on the phone too; only saving the file is charged, and the website's own gate decides it.
        $kept = Library::kept($ctp);
        $charges = Library::charges($request->user());

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
                'price' => Library::priceLabel(Library::price($i)),
                'status' => $kept->get($i->id)?->status,
                'paid' => (bool) $kept->get($i->id)?->isPaid(),
            ])->values(),
            'courses' => $mine->map(fn ($t) => ['id' => $t->id, 'name' => $t->name])->values(),
            'course' => $course,
            'kind' => $kind,
            'kinds' => LibraryItem::KINDS,
            'charges' => $charges,
            'paybill' => Finance::paybill(),
        ]);
    }

    /**
     * GET /api/me/library/{item} — one title, handed to the trainees it was filed for, exactly as the website hands
     * it. Reading it on the phone is free; ?save=1 is the app asking to keep the file, which is the paid part.
     */
    public function libraryFile(Request $request, LibraryItem $item): JsonResponse
    {
        $ctp = $this->ctp();

        abort_unless($item->status === 'on', 404, 'That reading is off the shelf.');
        $this->reads($item, $ctp);
        abort_unless($item->file_path, 404, 'That title has no file on record.');

        if ($request->boolean('save') && ! Library::entitled($ctp, $item, $request->user())) {
            abort(402, Library::priceLabel(Library::price($item)).' — the office confirms the payment before you keep this.');
        }

        return response()->json($this->inline($item->file_path, $item->file_name ?: $item->title));
    }

    /** POST /api/me/library/{item}/pay — the trainee says the money went in; the Registrar confirms it on the website. */
    public function libraryPay(Request $request, LibraryItem $item): JsonResponse
    {
        $ctp = $this->ctp();

        abort_unless($item->status === 'on', 404, 'That reading is off the shelf.');
        $this->reads($item, $ctp);
        abort_unless(Library::charges($request->user()), 422, 'You are not charged for library reading.');

        $purchase = Library::request($request->user(), $ctp, $item);

        return response()->json([
            'status' => $purchase->status,
            'message' => 'Noted — the Academic Registrar will confirm the '
                .Library::priceLabel($purchase->amount).' for '.$item->title.'. Your download opens as soon as they do.',
        ]);
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

    private function materials(Cohort $cohort, $units = null)
    {
        return TrainingMaterial::where('cohort_id', $cohort->id)
            // An intake that teaches more than one course hands out handouts for both; a trainee reads their own units'.
            ->when($units !== null && count($cohort->programmeIds()) > 1, fn ($q) => $q->where(fn ($w) => $w
                ->whereNull('training_module_id')
                ->orWhereIn('training_module_id', $units->pluck('id'))))
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

    /** The shelf rule shared with the website: a title filed under another programme is not this trainee's reading. */
    private function reads(LibraryItem $item, CtpRegistration $ctp): void
    {
        abort_unless(Library::reads($item, MemberPortal::readings($ctp)->pluck('id')->all()), 403,
            $item->title.' is filed under '.$item->courseLabel().', a programme you are not on. '
            .'Reading filed for every course is open to you already.');
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
