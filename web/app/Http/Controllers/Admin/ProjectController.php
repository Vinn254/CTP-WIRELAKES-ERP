<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\County;
use App\Models\HirerRegistration;
use App\Models\Project;
use App\Models\ProjectApplication;
use Illuminate\Http\Request;

class ProjectController extends Controller
{
    private function rules(): array
    {
        return [
            'title' => 'required|string|max:160',
            'description' => 'required|string|max:8000',
            'category' => 'required|in:'.implode(',', array_keys(Project::CATEGORIES)),
            'hirer_id' => 'nullable|exists:hirer_registrations,id',
            'trade' => 'nullable|string|max:80',
            'county' => 'nullable|string|max:80',
            'ward' => 'nullable|string|max:80',
            'budget' => 'nullable|numeric|min:0|max:999999999',
            'duration' => 'nullable|string|max:60',
            'deadline' => 'nullable|date',
            'status' => 'required|in:draft,open',
        ];
    }

    public function index(Request $request)
    {
        $status = $request->query('status', 'all');
        abort_unless(in_array($status, ['all', 'open', 'draft', 'awarded', 'closed'], true), 404);

        $projects = Project::with(['hirer', 'award', 'applications'])
            ->when($status !== 'all', fn ($q) => $q->where('status', $status))
            ->latest()
            ->paginate(15)
            ->withQueryString();

        return view('admin.projects.index', [
            'projects' => $projects,
            'status' => $status,
            'counts' => Project::selectRaw('status, count(*) c')->groupBy('status')->pluck('c', 'status'),
            'pendingApplications' => ProjectApplication::where('status', 'pending_review')->count(),
        ]);
    }

    public function create()
    {
        return view('admin.projects.form', [
            'project' => new Project(['status' => 'open']),
            'counties' => County::orderBy('name')->pluck('name'),
            'hirers' => $this->hirers(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate($this->rules());

        $project = Project::create($data + [
            'reference' => Project::nextCode(),
            'posted_by' => auth()->id(),
        ]);

        return redirect()
            ->route('admin.projects.show', $project)
            ->with('status', $project->reference.' posted. Members whose level matches can now see it.');
    }

    public function show(Project $project)
    {
        return view('admin.projects.show', [
            'project' => $project->load(['hirer', 'applications', 'award', 'poster']),
        ]);
    }

    public function edit(Project $project)
    {
        return view('admin.projects.form', [
            'project' => $project,
            'counties' => County::orderBy('name')->pluck('name'),
            'hirers' => $this->hirers(),
        ]);
    }

    public function update(Request $request, Project $project)
    {
        $data = $request->validate($this->rules());

        // Never silently un-award a project from the edit form.
        if ($project->status === 'awarded') {
            $data['status'] = 'awarded';
        }

        $project->update($data);

        return back()->with('status', $project->reference.' updated.');
    }

    public function setStatus(Request $request, Project $project)
    {
        $data = $request->validate(['status' => 'required|in:open,closed,draft']);

        if ($project->status === 'awarded') {
            return back()->with('error', 'This project is already awarded — release the award first.');
        }

        $project->update(['status' => $data['status']]);

        return back()->with('status', $project->reference.' is now '.Project::STATUSES[$project->status].'.');
    }

    /** Award the project to one applicant; every other application is declined. */
    public function award(Project $project, ProjectApplication $application)
    {
        abort_unless($application->project_id === $project->id, 404);

        $project->load('award');
        if ($project->award && $project->award->id !== $application->id) {
            return back()->with('error', 'Already awarded to '.$project->award->full_name.' — release the award first.');
        }

        $project->applications()->where('id', '!=', $application->id)->where('status', 'pending_review')
            ->update(['status' => 'rejected', 'rejection_reason' => 'Another applicant was awarded this project.', 'reviewed_by' => auth()->id()]);

        $application->update(['status' => 'approved', 'reviewed_by' => auth()->id(), 'rejection_reason' => null]);
        $project->update(['status' => 'awarded', 'awarded_at' => now()]);

        return back()->with('status', $project->reference.' awarded to '.$application->full_name.'.');
    }

    public function decline(Request $request, Project $project, ProjectApplication $application)
    {
        abort_unless($application->project_id === $project->id, 404);
        abort_if($application->status === 'approved', 422, 'This is the awarded applicant — release the award instead.');

        $data = $request->validate(['rejection_reason' => 'nullable|string|max:2000']);
        $application->update(['status' => 'rejected'] + $data + ['reviewed_by' => auth()->id()]);

        return back()->with('status', $application->full_name.' declined.');
    }

    public function releaseAward(Project $project)
    {
        if (! $project->award) {
            return back()->with('error', 'Nothing to release.');
        }

        $project->award->update(['status' => 'pending_review', 'reviewed_by' => auth()->id()]);
        $project->update(['status' => 'open', 'awarded_at' => null]);

        return back()->with('status', 'Award released — the project is open for applications again.');
    }

    public function destroy(Project $project)
    {
        $reference = $project->reference;
        $project->delete();

        return redirect()->route('admin.projects.index')->with('status', $reference.' deleted.');
    }

    private function hirers()
    {
        return HirerRegistration::orderBy('organisation')->get(['id', 'organisation', 'county', 'hirer_code']);
    }
}
