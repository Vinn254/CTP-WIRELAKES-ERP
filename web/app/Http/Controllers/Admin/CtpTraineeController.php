<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CtpRegistration;
use App\Models\Project;
use App\Models\ProjectApplication;
use App\Support\MemberPortal;
use Illuminate\Http\Request;

/**
 * Staff-side window into the CTP trainee interface. It renders the member portal views with the
 * same data the CTP would see — read-only, and reachable only under the staff role guard.
 */
class CtpTraineeController extends Controller
{
    public function index(Request $request)
    {
        $ctps = CtpRegistration::query()
            ->with('user:id,name,active')
            ->withCount(['referrals as mobilised_count' => fn ($q) => $q->whereIn('status', CtpRegistration::WORKING)])
            ->when($request->query('q'), function ($q) use ($request) {
                $search = trim((string) $request->query('q'));
                $q->where(fn ($w) => $w->where('ctp_code', 'like', "%{$search}%")
                    ->orWhere('full_name', 'like', "%{$search}%"));
            })
            ->orderByRaw("field(status,'deployed','graduated','active','suspended','pending_review','pending_payment','rejected')")
            ->orderBy('full_name')
            ->paginate(25)
            ->withQueryString();

        return view('admin.ctp.trainees', [
            'ctps' => $ctps,
            'search' => trim((string) $request->query('q')),
            'accounts' => CtpRegistration::whereIn('status', CtpRegistration::WORKING)->whereNotNull('user_id')->count(),
        ]);
    }

    public function show(Request $request, CtpRegistration $ctp)
    {
        $tab = $request->query('tab', 'training');
        abort_unless(in_array($tab, CtpRegistration::PORTAL_TABS, true), 404);

        return view('admin.ctp.trainee', MemberPortal::board($ctp) + [
            'referrals' => $ctp->referrals()->latest()->get(),
            'tab' => $tab,
            'previewBase' => route('admin.trainee.show', $ctp),
            'previewAs' => $ctp,
            'previewBack' => route('admin.promoters.show', $ctp),
        ]);
    }

    public function project(CtpRegistration $ctp, Project $project)
    {
        // The preview shows only what this CTP is entitled to see, never more.
        abort_unless($project->accepts($ctp), 403, 'That project is outside this CTP’s level — they would never see it.');

        return view('admin.ctp.project', [
            'member' => $ctp,
            'project' => $project->load('hirer'),
            'application' => ProjectApplication::where('project_id', $project->id)
                ->where('applicant_type', 'ctp')->where('applicant_id', $ctp->id)->first(),
            'previewBase' => route('admin.trainee.show', $ctp),
            'previewAs' => $ctp,
            'previewBack' => route('admin.promoters.show', $ctp),
        ]);
    }
}
