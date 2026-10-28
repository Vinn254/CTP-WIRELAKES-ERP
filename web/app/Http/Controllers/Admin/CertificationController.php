<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Certification;
use App\Models\Enrollment;
use Illuminate\Http\Request;

/**
 * Module 4  and  — the office puts a trainee forward from their record, and a programme
 * executive approves the certificate. Only approval issues the code; a revocation keeps the record.
 */
class CertificationController extends Controller
{
    public function index(Request $request)
    {
        $status = $request->input('status', 'all');

        return view('admin.certification.index', [
            'certifications' => Certification::with([
                'enrollment.ctp:id,full_name,ctp_code', 'enrollment.cohort:id,reference,training_id', 'enrollment.cohort.training:id,name,code',
                'recommender:id,name', 'approver:id,name',
            ])
                ->when($status !== 'all', fn ($q) => $q->where('status', $status))
                ->orderByDesc('id')->get(),
            'ready' => Enrollment::with([
                'ctp:id,full_name,ctp_code,status', 'cohort:id,reference,training_id,status,venue_id',
                'cohort.training:id,name,code', 'cohort.venue:id,name,type', 'moduleMarks', 'documents',
            ])
                ->where('state', 'approved')->whereDoesntHave('certification')->orderBy('id')->get()
                ->filter(fn (Enrollment $e) => $e->trackPct() >= 100)->values(),
            'status' => $status,
            'statuses' => Certification::STATUSES,
            'counts' => Certification::selectRaw('status, count(*) n')->groupBy('status')->pluck('n', 'status'),
            'isExecutive' => auth()->user()->role === 'superadmin',
        ]);
    }

    /** Put a trainee forward on the strength of what has been recorded about them. */
    public function recommend(Enrollment $enrollment)
    {
        abort_unless($enrollment->state === 'approved', 422, 'A seat must be held before anyone is put forward.');

        $missing = $enrollment->trackMissing();
        abort_if($missing->isNotEmpty(), 422,
            $enrollment->ctp->full_name.'’s track is at '.$enrollment->trackPct().'%. Still short: '
            .$missing->map(fn ($p) => $p['label'].' ('.$p['detail'].')')->join(', ').'.');

        abort_unless(! $enrollment->certification, 422,
            $enrollment->ctp->full_name.' is already in the certification queue — '.$enrollment->certification->statusLabel().'.');

        $attendance = $enrollment->attendancePct();

        abort_if($attendance !== null && $attendance < Enrollment::ATTENDANCE_MINIMUM, 422,
            $enrollment->ctp->full_name.' is at '.$attendance.'% attendance — below the '
            .Enrollment::ATTENDANCE_MINIMUM.'% needed to certify. Record the sessions they missed or mark the days they came.');

        $certification = Certification::create([
            'enrollment_id' => $enrollment->id,
            'theory_score' => $enrollment->avgScore('theory'),
            'practical_score' => $enrollment->avgScore('practical'),
            'attendance_pct' => $attendance,
            'status' => 'recommended',
            'recommended_by' => auth()->id(),
        ]);

        return back()->with('status', $enrollment->ctp->full_name.' put forward for certification — an executive approves it.');
    }

    /** The executive signs the certificate off, which is the only moment a code exists. */
    public function approve(Certification $certification)
    {
        $this->guardExecutive();
        abort_unless($certification->status === 'recommended', 422,
            'Only a recommendation can be approved — this one is '.$certification->statusLabel().'.');

        $enrollment = $certification->enrollment()->with(['ctp', 'cohort'])->firstOrFail();

        $certification->update([
            'certificate_code' => $certification->certificate_code
                ?: Certification::nextCode($enrollment->ctp, $enrollment->cohort->training),
            'status' => 'approved',
            'approved_by' => auth()->id(),
            'approved_on' => today(),
        ]);

        $enrollment->update(['stage' => 'completed']);

        return back()->with('status', $certification->certificate_code.' issued to '.$enrollment->ctp->full_name
           .'. Their next step is E-Fundi registration — the office confirms it in Membership.');
    }

    public function decline(Request $request, Certification $certification)
    {
        $data = $request->validate([
            'reason' => 'required|string|max:1000',
        ], [], ['reason' => 'Reason']);

        abort_unless($certification->status === 'recommended', 422, 'This certificate is already '.$certification->statusLabel().'.');

        $certification->update(['status' => 'declined', 'reason' => $data['reason'], 'approved_by' => auth()->id()]);

        return back()->with('status', 'Recommendation declined — the reason is on the trainee record.');
    }

    /** A certificate already in someone's hand is revoked, never deleted. */
    public function revoke(Request $request, Certification $certification)
    {
        $this->guardExecutive();

        $data = $request->validate([
            'reason' => 'required|string|max:1000',
        ], [], ['reason' => 'Reason for revoking']);

        abort_unless($certification->status === 'approved', 422, 'Only an issued certificate can be revoked.');

        $certification->update(['status' => 'revoked', 'reason' => $data['reason']]);

        return back()->with('status', $certification->certificate_code.' is revoked and marked as such wherever it is shown.');
    }

    /** Who may sign a certificate off, or take one back. */
    private function guardExecutive(): void
    {
        abort_unless(auth()->user()->role === 'superadmin', 403,
            'Only a programme executive can approve or revoke a certificate.');
    }
}
