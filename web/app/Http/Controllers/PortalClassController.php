<?php

namespace App\Http\Controllers;

use App\Models\Cohort;
use App\Models\CtpRegistration;
use App\Models\Enrollment;
use App\Models\Timetable;
use App\Models\TrainingMaterial;
use App\Models\UnitMarkSheet;
use App\Support\MemberPortal;
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
        $seat = $this->seat($cohort);

        // Only a paper the Registrar has answered reaches the trainee's own screen.
        $timetable = Timetable::where('cohort_id', $cohort->id)->where('status', 'approved')
            ->with('slots.module:id,title')->latest('id')->first();

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

        return view('portal.class', MemberPortal::board($this->ctp()) + [
            'seat' => $seat,
            'cohort' => $cohort->load(['training.modules', 'venue', 'trainer']),
            'timetable' => $timetable,
            'week' => $timetable?->week() ?? [],
            'units' => $cohort->training?->modules ?? collect(),
            'marksByUnit' => $marksByUnit,
            'materials' => TrainingMaterial::where('cohort_id', $cohort->id)
                ->with(['module:id,title', 'lesson:id,topic', 'uploaderFundi:id,full_name'])
                ->orderByDesc('given_on')->orderBy('id')->get(),
            'kinds' => UnitMarkSheet::KINDS,
            'mine' => $this->seats($this->ctp())->get(),
        ]);
    }

    /** A handout, opened only through the seat that holds the class it was given to. */
    public function open(Cohort $cohort, TrainingMaterial $material)
    {
        $this->seat($cohort);
        abort_unless($material->cohort_id === $cohort->id, 404);
        abort_unless(Storage::disk('local')->exists($material->file_path), 404, 'That file is no longer on record.');

        return Storage::disk('local')->response($material->file_path, $material->file_name ?: 'Material');
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
