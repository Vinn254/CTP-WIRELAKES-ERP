<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CtpRegistration;
use App\Models\FundiRegistration;
use App\Models\Project;
use App\Models\ProjectApplication;
use App\Support\MemberPortal;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/**
 * The member's own portal, served as JSON for the mobile app. Every screen here is the exact web portal screen:
 * the same member is resolved from the token (never a url), the same MemberPortal board and the same Project
 * accepts()/viewsTo() gates decide what they see and where they may apply. No logic is re-invented here.
 */
class MemberApiController extends Controller
{
    /** The signed-in member record, or a clean 403 — the app never passes an id. */
    private function member()
    {
        $member = MemberPortal::resolve(auth()->user());

        abort_if(! $member, 403, 'Your member record could not be found. Contact the programme office.');

        return $member;
    }

    /** GET /api/me/board — profile summary plus the dashboard counts the web portal shows. */
    public function board(): JsonResponse
    {
        $member = $this->member();
        $board = MemberPortal::board($member);

        return response()->json([
            'member' => $this->memberSummary($member),
            'counts' => [
                'projects' => $board['projects']->count(),
                'applications' => $board['applications']->count(),
                'communities' => $board['communities']->count(),
                'invites' => $board['inviteCount'],
                'work_orders' => $board['workOrderCount'],
                'fixes' => $board['fixCount'],
                'repair_jobs' => $board['techJobCount'],
            ],
        ]);
    }

    /** GET /api/projects — the projects this member is shown on the web board. */
    public function projects(): JsonResponse
    {
        $member = $this->member();

        return response()->json([
            'projects' => MemberPortal::projects($member)
                ->map(fn (Project $p) => $this->projectSummary($p, $member))
                ->values(),
        ]);
    }

    /** GET /api/projects/{project} — one project, refused if the member's level cannot open it. */
    public function show(Project $project): JsonResponse
    {
        $member = $this->member();
        abort_if($project->status === 'draft', 404);
        abort_unless($project->viewsTo($member), 403, 'This project is for a different member level.');

        $application = ProjectApplication::where('project_id', $project->id)
            ->where('applicant_type', MemberPortal::applicantType($member))
            ->where('applicant_id', $member->id)
            ->first();

        return response()->json([
            'project' => $this->projectDetail($project, $member),
            'application' => $application ? $this->application($application) : null,
        ]);
    }

    /** POST /api/projects/{project}/apply — the same guard and the same record the web portal writes. */
    public function apply(Request $request, Project $project): JsonResponse
    {
        $member = $this->member();
        abort_unless($project->accepts($member), 403, 'This project is for a different member level.');
        abort_if($project->status !== 'open', 422, 'This project is no longer open for applications.');

        $type = MemberPortal::applicantType($member);
        $already = ProjectApplication::where('project_id', $project->id)
            ->where('applicant_type', $type)
            ->where('applicant_id', $member->id)
            ->exists();
        abort_if($already, 422, 'You have already applied for this project.');

        $data = $request->validate(['note' => 'nullable|string|max:2000']);

        $application = ProjectApplication::create($data + [
            'project_id' => $project->id,
            'applicant_type' => $type,
            'applicant_id' => $member->id,
            'full_name' => $member->full_name,
            'phone' => $member->phone,
            'email' => $member->email,
        ]);

        return response()->json([
            'message' => 'Applied — the programme office will review and assign the project.',
            'application' => $this->application($application->fresh()),
        ], 201);
    }

    /** GET /api/applications — this member's own applications, newest first. */
    public function applications(): JsonResponse
    {
        $member = $this->member();

        return response()->json([
            'applications' => MemberPortal::applications($member)
                ->map(fn (ProjectApplication $a) => $this->application($a))
                ->values(),
        ]);
    }

    /** POST /api/applications/{application}/withdraw — a pending application, this member's own. */
    public function withdraw(ProjectApplication $application): JsonResponse
    {
        $member = $this->member();
        abort_unless(
            $application->applicant_type === MemberPortal::applicantType($member)
                && (int) $application->applicant_id === (int) $member->id,
            403,
            'That application is not yours.'
        );
        abort_if($application->status !== 'pending_review', 422,
            'Only an application that has not been decided yet can be withdrawn.');

        $application->delete();

        return response()->json(['message' => 'Withdrawn.']);
    }

    /** GET /api/me/photo — the face on the member's own record, served like the portal. */
    public function photo(): JsonResponse
    {
        $member = $this->member();
        $path = $member->photoPath();

        abort_unless($path && Storage::disk('local')->exists($path), 404, 'No profile photo on record yet.');

        return response()->json(['photo' => 'data:image/jpeg;base64,'.base64_encode(Storage::disk('local')->get($path))]);
    }

    // ---- serialisers ----------------------------------------------------

    private function memberSummary($member): array
    {
        $isFundi = $member instanceof FundiRegistration;

        return [
            'type' => $isFundi ? 'fundi' : 'ctp',
            'id' => $member->id,
            'code' => $isFundi ? ($member->ef_code ?? $member->reference) : ($member->ctp_code ?? $member->reference),
            'name' => $member->full_name,
            'phone' => $member->phone,
            'email' => $member->email,
            'county' => $member->county,
            'status' => $member->status,
            'level' => $isFundi
                ? FundiRegistration::tierLabel($member->tier)
                : ($member->fundi_tier ? $member->poolLabel() : 'CTP · '.str_replace('_', ' ', $member->track ?? 'member')),
            // The web sidebar shows "Training CTPs" off this same flag, so the app's nav must see it too.
            'can_train' => $isFundi && $member->canTrain(),
        ];
    }

    private function projectSummary(Project $p, $member): array
    {
        return [
            'id' => $p->id,
            'reference' => $p->reference,
            'title' => $p->title,
            'category' => $p->category,
            'category_label' => Project::CATEGORIES[$p->category]['label'] ?? $p->category,
            'trade' => $p->trade,
            'county' => $p->county,
            'ward' => $p->ward,
            'budget' => $p->budget,
            'deadline' => $p->deadline?->toDateString(),
            'status' => $p->status,
            'can_apply' => $p->accepts($member) && $p->status === 'open',
        ];
    }

    private function projectDetail(Project $p, $member): array
    {
        return $this->projectSummary($p, $member) + [
            'description' => $p->description,
            'materials' => $p->materials,
            'duration' => $p->duration,
            'hirer' => $p->hirer->name ?? null,
        ];
    }

    private function application(ProjectApplication $a): array
    {
        return [
            'id' => $a->id,
            'project_id' => $a->project_id,
            'project_title' => $a->project->title ?? null,
            'project_reference' => $a->project->reference ?? null,
            'status' => $a->status,
            'status_label' => ProjectApplication::STATUSES[$a->status] ?? $a->status,
            'note' => $a->note,
            'applied_on' => $a->created_at?->toDateString(),
            'can_withdraw' => $a->status === 'pending_review',
        ];
    }
}
