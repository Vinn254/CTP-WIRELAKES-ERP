<?php

namespace App\Http\Controllers;

use App\Models\CtpRegistration;
use App\Models\Project;
use App\Models\ProjectApplication;
use App\Support\MemberPortal;
use Illuminate\Http\Request;

class PortalController extends Controller
{
    /** The member record (CTP or E-Fundi) behind the signed-in account. */
    private function member()
    {
        return MemberPortal::resolve(auth()->user());
    }

    /** Sections only a CTP has — an E-Fundi is refused here, and their sidebar never offers them. */
    private function ctp(): CtpRegistration
    {
        $member = $this->member();
        abort_if(! $member, 403, 'Your member record could not be found. Contact the programme office.');
        abort_unless($member instanceof CtpRegistration, 403, 'That part of the portal is for CTPs.');

        return $member;
    }

    public function dashboard()
    {
        $member = $this->member();
        abort_if(! $member, 403, 'Your member record could not be found. Contact the programme office.');

        return view('portal.dashboard', MemberPortal::board($member) + [
            'referrals' => $member instanceof CtpRegistration ? $member->referrals()->latest()->limit(3)->get() : collect(),
        ]);
    }

    public function training()
    {
        return view('portal.training', MemberPortal::board($this->ctp()));
    }

    public function mobilisation()
    {
        $ctp = $this->ctp();

        return view('portal.mobilisation', MemberPortal::board($ctp) + [
            'referrals' => $ctp->referrals()->latest()->get(),
        ]);
    }

    public function communities()
    {
        return view('portal.communities', MemberPortal::board($this->ctp()));
    }

    public function projects()
    {
        return view('portal.projects', MemberPortal::board($this->member()));
    }

    public function applications()
    {
        return view('portal.applications', MemberPortal::board($this->member()));
    }

    public function project(Request $request, Project $project)
    {
        $member = $this->member();
        abort_if(! $member, 403, 'Your member record could not be found.');
        abort_unless($project->accepts($member), 403, 'This project is for a different member level.');

        return view('portal.project', [
            'member' => $member,
            'project' => $project->load('hirer'),
            'application' => ProjectApplication::where('project_id', $project->id)
                ->where('applicant_type', MemberPortal::applicantType($member))
                ->where('applicant_id', $member->id)
                ->first(),
        ]);
    }

    public function apply(Request $request, Project $project)
    {
        $member = $this->member();
        abort_if(! $member, 403, 'Your member record could not be found.');
        abort_unless($project->accepts($member), 403, 'This project is for a different member level.');
        if ($project->status !== 'open') {
            return back()->with('error', 'This project is no longer open for applications.');
        }

        $type = MemberPortal::applicantType($member);
        if (ProjectApplication::where('project_id', $project->id)->where('applicant_type', $type)->where('applicant_id', $member->id)->exists()) {
            return back()->with('error', 'You have already applied for this project.');
        }

        $data = $request->validate(['note' => 'nullable|string|max:2000']);

        ProjectApplication::create($data + [
            'project_id' => $project->id,
            'applicant_type' => $type,
            'applicant_id' => $member->id,
            'full_name' => $member->full_name,
            'phone' => $member->phone,
            'email' => $member->email,
        ]);

        return back()->with('status', 'Applied — the programme office will review and assign the project.');
    }

    public function withdraw(ProjectApplication $application)
    {
        $member = $this->member();
        abort_if(! $member, 403, 'Your member record could not be found.');

        abort_unless($application->applicant_type === MemberPortal::applicantType($member) && $application->applicant_id === $member->id, 403);
        abort_unless($application->status === 'pending_review', 422, 'This application has already been reviewed.');

        $application->delete();

        return back()->with('status', 'Application withdrawn.');
    }
}
