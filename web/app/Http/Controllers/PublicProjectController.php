<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Models\WorkOrder;
use Illuminate\Support\Collection;

class PublicProjectController extends Controller
{
    public function index()
    {
        return view('pages.projects', [
            'open' => Project::with(['hirer', 'community'])
                ->where('status', 'open')
                ->orderByRaw('deadline is null')
                ->orderBy('deadline')
                ->orderByDesc('id')
                ->get(),
            'delivered' => $this->delivered(),
        ]);
    }

    public function show(Project $project)
    {
        abort_if($project->status === 'draft', 404);

        return view('pages.project', [
            'project' => $project->load(['hirer', 'community']),
            'order' => WorkOrder::where('project_id', $project->id)
                ->whereIn('status', ['confirmed', 'closed'])
                ->latest('id')
                ->first(),
        ]);
    }

    /** Work the programme finished and the hirer signed off — the board's proof that projects get done. */
    private function delivered(): Collection
    {
        return WorkOrder::with(['project.hirer', 'project.community'])
            ->whereIn('status', ['confirmed', 'closed'])
            ->whereHas('project')
            ->orderByDesc('confirmed_on')
            ->orderByDesc('id')
            ->limit(12)
            ->get();
    }
}
