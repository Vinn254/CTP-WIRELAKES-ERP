<?php

namespace App\Http\Controllers;

use App\Models\Beneficiary;
use App\Models\Community;
use App\Models\County;
use App\Models\CtpRegistration;
use App\Models\Need;
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
        $ctp = $this->ctp();
        $board = MemberPortal::board($ctp);

        return view('portal.communities', $board + [
            'types' => Need::TYPES,
            'myNeeds' => $ctp->needsRecorded()->with('community:id,name,community_code,status')->latest('id')->limit(15)->get(),
            'projectOptions' => Project::whereIn('community_id', $board['communities']->pluck('id'))
                ->orderByDesc('created_at')->get(['id', 'reference', 'title']),
            'counties' => County::orderBy('name')->pluck('name'),
        ]);
    }

    /** A CTP puts a community forward — it lands on the register as a proposal for the programme office. */
    public function storeCommunity(Request $request)
    {
        $ctp = $this->ctp();
        abort_unless(in_array($ctp->status, CtpRegistration::WORKING, true), 422,
            'Only an active, graduated or deployed CTP can put a community forward. Your status is '.$ctp->statusLabel().'.');

        $community = Community::create([
            ...Community::placeData($request),
            'status' => 'pending_review',
            'created_by_ctp' => $ctp->id,
        ]);

        return back()->with('status', $community->name.' sent to the programme office — they confirm it and issue its COM ID. '
            .'You can already log its needs.');
    }

    /** Step 1-4 of the assessment workflow: the CTP records what they saw, with a photo and a pin. */
    public function storeNeed(Request $request)
    {
        $ctp = $this->ctp();
        $community = $this->theirCommunity($request, $ctp);

        $data = $request->validate([
            'type' => 'required|in:'.implode(',', array_keys(Need::TYPES)),
            'title' => 'required|string|max:160',
            'detail' => 'nullable|string|max:2000',
            'gps_lat' => 'nullable|numeric|between:-90,90',
            'gps_lng' => 'nullable|numeric|between:-180,180',
            'photo' => 'nullable|image|mimes:jpg,jpeg,png|max:3072',
        ], [], [
            'gps_lat' => 'GPS latitude', 'gps_lng' => 'GPS longitude', 'title' => 'What is needed',
        ]);

        $need = Need::create([
            ...collect($data)->except('photo')->all(),
            'community_id' => $community->id,
            'assessed_by_ctp' => $ctp->id,
            'status' => 'proposed',
        ]);

        if ($request->hasFile('photo')) {
            $need->update(['photo_path' => $request->file('photo')->store('needs/'.$community->id, 'local')]);
        }

        return back()->with('status', '"'.$need->title.'" logged for '.$community->name.' — the programme office reviews it next.');
    }

    /** Beneficiaries are only counted once the community is confirmed. */
    public function storeBeneficiary(Request $request)
    {
        $ctp = $this->ctp();
        $community = $this->theirCommunity($request, $ctp);

        abort_unless($community->status === 'active', 422,
            $community->name.' is not confirmed yet — beneficiaries are counted once the programme office confirms the community.');

        $beneficiary = Beneficiary::create([
            ...Beneficiary::dataFrom($request),
            'community_id' => $community->id,
            'created_by_ctp' => $ctp->id,
        ]);

        return back()->with('status', $beneficiary->name.' recorded against '.$community->name.'.');
    }

    /**
     * Communities this CTP is allowed to work on — the roster or their own proposals. The id comes
     * from the form, so it is always resolved through their own record: another CTP's community 404s.
     */
    private function theirCommunity(Request $request, CtpRegistration $ctp): Community
    {
        $request->validate(['community_id' => 'required|integer']);

        return Community::forCtp($ctp)->findOrFail($request->input('community_id'));
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
