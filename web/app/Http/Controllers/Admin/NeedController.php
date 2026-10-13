<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Need;
use App\Models\Project;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Module 3 — the needs pipeline (blueprint 6.4). CTPs flag needs in the field; the programme office
 * prioritises them here and turns the funded ones into a project without leaving the department.
 */
class NeedController extends Controller
{
    public function index(Request $request)
    {
        $status = $request->query('status', 'proposed');
        abort_unless(in_array($status, ['all', ...array_keys(Need::STATUSES)], true), 404);
        $type = $request->query('type', 'all');
        abort_unless($type === 'all' || isset(Need::TYPES[$type]), 404);
        $search = trim((string) $request->query('q'));

        $needs = Need::with([
            'community:id,name,county,sub_county,ward,community_code,status',
            'project:id,reference,title,status',
            'assessor:id,ctp_code,full_name',
            'reviewer:id,name',
        ])
            ->when($status !== 'all', fn ($q) => $q->where('status', $status))
            ->when($type !== 'all', fn ($q) => $q->where('type', $type))
            ->when($search !== '', function ($q) use ($search) {
                $q->where(fn ($w) => $w
                    ->where('title', 'like', "%{$search}%")
                    ->orWhere('detail', 'like', "%{$search}%")
                    ->orWhereHas('community', function ($c) use ($search) {
                        $c->where('name', 'like', "%{$search}%")->orWhere('county', 'like', "%{$search}%");
                    }));
            })
            ->orderByRaw("field(priority,'urgent','high','standard','low')")
            ->latest('id')
            ->paginate(25)
            ->withQueryString();

        return view('admin.needs.index', [
            'needs' => $needs,
            'status' => $status,
            'type' => $type,
            'search' => $search,
            'types' => Need::TYPES,
            'counts' => Need::selectRaw('status, count(*) c')->groupBy('status')->pluck('c', 'status'),
        ]);
    }

    public function setPriority(Request $request, Need $need)
    {
        $data = $request->validate([
            'priority' => 'required|in:'.implode(',', array_keys(Need::PRIORITIES)),
        ]);

        $need->update(['priority' => $data['priority'], 'reviewed_by' => auth()->id(), 'reviewed_at' => now()]);

        return back()->with('status', '"'.$need->title.'" is now '.$need->priorityLabel().' priority.');
    }

    /** Stand behind a need, send it back for more detail, or close it. Converted needs follow their project. */
    public function setStatus(Request $request, Need $need)
    {
        $data = $request->validate(['status' => 'required|in:prioritised,proposed,closed']);
        abort_unless($need->status !== 'converted', 422,
            'This need already became a project — change it from the project instead.');

        $need->update(['status' => $data['status'], 'reviewed_by' => auth()->id(), 'reviewed_at' => now()]);

        return back()->with('status', '"'.$need->title.'" is now '.$need->statusLabel().'.');
    }

    /** One step that stays in Module 3 and leaves a draft for Module 1 to publish. */
    public function convert(Request $request, Need $need)
    {
        abort_unless($need->isLive(), 422, 'Only an open need can become a project.');

        $data = $request->validate([
            'title' => 'required|string|max:160',
            'description' => 'required|string|max:8000',
            'category' => 'required|in:'.implode(',', array_keys(Project::CATEGORIES)),
            'trade' => 'nullable|string|max:80',
            'budget' => 'nullable|numeric|min:0|max:999999999',
            'duration' => 'nullable|string|max:60',
            'deadline' => 'nullable|date',
        ], [], ['category' => 'Project type']);

        $project = DB::transaction(function () use ($need, $data) {
            $project = Project::create([
                ...$data,
                'reference' => Project::nextCode(),
                'community_id' => $need->community_id,
                'county' => $need->community->county,
                'ward' => $need->community->ward,
                'status' => 'draft',
                'posted_by' => auth()->id(),
            ]);

            $need->update([
                'status' => 'converted',
                'project_id' => $project->id,
                'converted_at' => now(),
                'reviewed_by' => auth()->id(),
                'reviewed_at' => now(),
            ]);

            return $project;
        });

        return redirect()
            ->route('admin.communities.show', $need->community_id)
            ->with('status', $project->reference.' drafted for '.$need->community->name
                .' — open Projects to add the brief and publish it.');
    }

    public function photo(Need $need)
    {
        $path = $need->photo_path;
        abort_unless($path && Storage::disk('local')->exists($path), 404, 'No photo on this need.');

        return response()->file(Storage::disk('local')->path($path));
    }
}
