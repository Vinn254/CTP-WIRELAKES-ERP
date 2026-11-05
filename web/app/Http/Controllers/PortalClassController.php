<?php

namespace App\Http\Controllers;

use App\Models\Cohort;
use App\Models\CtpRegistration;
use App\Models\Enrollment;
use App\Models\Timetable;
use App\Models\TrainingMaterial;
use App\Models\UnitMarkSheet;
use App\Support\Documents;
use App\Support\Grading;
use App\Support\MemberPortal;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/**
 * The CTP's own class. What a trainer puts up for a cohort lands here once the Academic Registrar has answered it:
 * the week of classes, the units they are doing and the material handed out for each lesson.
 *
 * A trainee only ever sees their own cohort, and a handout opens through the seat that holds it — the file stays on
 * the private disk.
 */
class PortalClassController extends Controller
{
    public function index()
    {
        $ctp = $this->ctp();
        $seats = $this->seats($ctp)->get();

        if ($seats->count() === 1) {
            return redirect()->route('portal.class.cohort', $seats->first()->cohort_id);
        }

        return view('portal.classes', MemberPortal::board($ctp) + [
            'seats' => $seats,
        ]);
    }

    public function show(Cohort $cohort)
    {
        $ctp = $this->ctp();
        $seat = $this->seat($cohort);

        // A trainee follows their own programme: the units of the course they registered under, the week the Registrar
        // laid out for that course, and the marks on those units.
        $programme = (int) ($ctp->training_id ?: $cohort->training_id);

        // Only a paper the Registrar has answered reaches the trainee's own screen. A cohort that has not had its own
        // week approved yet runs on the programme's week, which the Registrar lays out for the whole course.
        $timetable = Timetable::where('cohort_id', $cohort->id)->where('status', 'approved')
            ->when($programme, fn ($q) => $q->where(fn ($w) => $w->whereNull('training_id')->orWhere('training_id', $programme)))
            ->with(Timetable::WEEK_WITH)->latest('id')->first()
            ?? ($programme ? Timetable::forProgramme($programme) : null)
            ?? Timetable::where('cohort_id', $cohort->id)->where('status', 'approved')
                ->with(Timetable::WEEK_WITH)->latest('id')->first();

        $marked = UnitMarkSheet::where('cohort_id', $cohort->id)->where('status', 'approved')->with('marks')->get();

        // Each unit as this trainee stands in it: the day the sheet was covered and the marks counted from it.
        $marksByUnit = [];
        foreach ($marked as $sheet) {
            $row = $marksByUnit[$sheet->training_module_id] ?? ['on' => null, 'marks' => []];
            $row['on'] = $sheet->covered_on?->format('d M Y') ?: $row['on'];

            foreach ($sheet->marks->where('enrollment_id', $seat->id) as $mark) {
                if ($mark->score !== null) {
                    $row['marks'][$mark->kind] = $mark->score;
                }
            }

            $marksByUnit[$sheet->training_module_id] = $row;
        }

        $cohort->load(['units', 'venue', 'trainer', 'trainings']);

        $units = $cohort->unitsFor($programme ?: null);

        // Nothing registered for their own course yet: the trainee still reads what the intake teaches, with the gap
        // named, rather than an empty page.
        $offProgramme = $units->isEmpty() && $cohort->unitList()->isNotEmpty();

        if ($offProgramme) {
            $units = $cohort->unitList();
        }

        // Each unit weighed the way the Registrar set it — the grade a trainee reads on their own page.
        $grades = $units->mapWithKeys(fn ($unit) => [$unit->id => Grading::compile($seat, $unit)])->all();

        // An intake that teaches more than one course hands out handouts for both; a trainee reads their own units'.
        $manyProgrammes = count($cohort->programmeIds()) > 1 && ! $offProgramme;

        return view('portal.class', MemberPortal::board($ctp) + [
            'seat' => $seat,
            'cohort' => $cohort,
            'timetable' => $timetable,
            'week' => $timetable?->week() ?? [],
            'units' => $units,
            'offProgramme' => $offProgramme,
            'unitTrainers' => $cohort->unitTrainers(),
            'programmeName' => $ctp->programme?->name,
            'marksByUnit' => $marksByUnit,
            'grades' => $grades,
            'overall' => Grading::overall(
                collect($grades)->filter(fn (array $g) => $g['marked'])->values()->all()),
            'materials' => TrainingMaterial::where('cohort_id', $cohort->id)
                ->when($manyProgrammes, fn ($q) => $q->where(fn ($w) => $w
                    ->whereNull('training_module_id')
                    ->orWhereIn('training_module_id', $units->pluck('id'))))
                ->with(['module:id,title', 'lesson:id,topic', 'uploaderFundi:id,full_name'])
                ->orderByDesc('given_on')->orderBy('id')->get(),
            'mine' => $this->seats($ctp)->get(),
        ]);
    }

    /** A handout, opened only through the seat that holds the class it was given to. */
    public function open(Request $request, Cohort $cohort, TrainingMaterial $material)
    {
        $this->seat($cohort);
        abort_unless($material->cohort_id === $cohort->id, 404);

        return Documents::file($request, $material->file_path, $material->file_name ?: 'Handout');
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

    private function ctp(): CtpRegistration
    {
        $member = MemberPortal::resolve(auth()->user());

        abort_if(! $member, 403, 'Your member record could not be found. Contact the programme office.');
        abort_unless($member instanceof CtpRegistration, 403,
            'This page is a trainee\'s own class. An E-Fundi records it under Training CTPs.');

        return $member;
    }
}
