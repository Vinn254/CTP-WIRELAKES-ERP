<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CtpRegistration;
use App\Models\Enrollment;
use App\Models\TrainingDocument;
use App\Models\UnitMarkSheet;
use App\Support\Documents;
use App\Support\Grading;
use App\Support\Library;
use App\Support\MemberPortal;
use App\Support\TeachingDesk;
use Illuminate\Http\Request;

/**
 * A trainee's whole record in one page: the person as they registered, then every course they have sat, term by
 * term, with the marks each unit was taken through and the certificate the office issued. The transcript is the same
 * page laid out on paper, so what the office reads on screen is what the trainee walks away with.
 *
 * The Training department owns the route; the CTP desk keeps the profile itself.
 */
class StudentController extends Controller
{
    public function show(Request $request, CtpRegistration $ctp)
    {
        $seats = $this->seats($ctp);

        return view('admin.students.show', [
            'ctp' => $ctp,
            'seats' => $seats,
            'terms' => $this->terms($seats),
            'marks' => $this->marks($seats),
            'grades' => $this->grades($seats),
            'modules' => $this->modules($seats),
            'papers' => TrainingDocument::whereIn('enrollment_id', $seats->pluck('id'))
                ->with(['enrollment:id,cohort_id', 'module:id,title'])
                ->orderByDesc('dated_on')->get(),
            'reading' => $this->reading($ctp),
            'canGrade' => TeachingDesk::approves($request->user()),
            'back' => $request->input('back') ?: route('admin.track.index'),
        ]);
    }

    /** The paper transcript, printed or saved from the record. */
    public function transcript(Request $request, CtpRegistration $ctp)
    {
        $seats = $this->seats($ctp);

        return Documents::page($request, view('admin.students.transcript', [
            'ctp' => $ctp,
            'seats' => $seats,
            'terms' => $this->terms($seats),
            'marks' => $this->marks($seats),
            'grades' => $this->grades($seats),
            'modules' => $this->modules($seats),
        ])->render(), 'Transcript '.$ctp->ctp_code.' '.$ctp->full_name);
    }

    /** Every seat the trainee has ever held, newest term first — an application withdrawn is still history. */
    private function seats(CtpRegistration $ctp)
    {
        return Enrollment::where('ctp_id', $ctp->id)
            ->with(['cohort:id,reference,training_id,venue_id,trainer_id,starts_on,ends_on,academic_year,term,status',
                'cohort.training:id,name,code', 'cohort.venue:id,name,type,county', 'cohort.units', 'cohort.trainings',
                'cohort.trainer.fundi:id,full_name', 'cohort.trainer.staff:id,name', 'cohort.trainer.applicant:id,name',
                'units', 'ctp:id,full_name,ctp_code,training_id',
                'entries', 'moduleMarks', 'certification'])
            ->get()
            ->sortByDesc(fn (Enrollment $e) => $e->cohort?->starts_on?->timestamp ?? 0)
            ->values();
    }

    /** The seats grouped as the trainee lived them: one block per year and term. */
    private function terms($seats)
    {
        return $seats->groupBy(fn (Enrollment $e) => $e->cohort?->periodLabel() ?: 'Period not set')
            ->map(fn ($block) => [
                'label' => $block->first()->cohort?->periodLabel() ?: 'Period not set',
                'seats' => $block,
            ])
            ->values();
    }

    /** The unit marks the Registrar approved for these seats, gathered under the seat each line belongs to. */
    private function marks($seats)
    {
        $sheets = UnitMarkSheet::whereIn('cohort_id', $seats->pluck('cohort_id')->filter())
            ->where('status', 'approved')
            ->with(['module:id,title', 'marks' => fn ($q) => $q->whereIn('enrollment_id', $seats->pluck('id'))])
            ->get();

        $bySeat = [];
        foreach ($sheets as $sheet) {
            foreach ($sheet->marks as $mark) {
                $bySeat[$mark->enrollment_id][] = [
                    'unit' => $sheet->module?->title ?: 'Unit not named',
                    'on' => $sheet->covered_on?->format('d M Y') ?: 'date not set',
                    'order' => $sheet->covered_on?->timestamp ?? 0,
                    'kind' => $mark->kindLabel(),
                    'score' => $mark->score,
                ];
            }
        }

        foreach ($bySeat as $id => $rows) {
            usort($bySeat[$id], fn (array $a, array $b) => $a['order'] <=> $b['order']);
        }

        return $bySeat;
    }

    /** Each seat's modules as the office set them, with the date this trainee's line was signed off. */
    private function modules($seats)
    {
        return $seats->mapWithKeys(fn (Enrollment $e) => [$e->id => $e->moduleBoard()])->all();
    }

    /**
     * Each seat's units weighed on the Registrar's grading, with the course overall beside them — the figure the
     * record, the printed transcript and the trainee's own screens all repeat.
     */
    private function grades($seats): array
    {
        $out = [];

        foreach ($seats as $e) {
            $units = Grading::forSeat($e);
            $out[$e->id] = ['units' => $units, 'overall' => Grading::overall($units)];
        }

        return $out;
    }

    /** The library titles on the courses this trainee has taken — the same shelf the student opens. */
    private function reading(CtpRegistration $ctp)
    {
        return Library::shelf(MemberPortal::readings($ctp)->pluck('id')->all())
            ->orderBy('title')->get(['id', 'title', 'kind', 'training_id', 'status']);
    }
}
