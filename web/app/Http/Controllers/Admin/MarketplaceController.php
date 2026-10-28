<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\FundiRegistration;
use App\Models\Project;
use App\Models\ProjectMatch;
use App\Support\Finance;
use App\Support\Marketplace;
use Illuminate\Http\Request;

/**
 * Module 6 — the marketplace. This is where demand meets supply: a project the office has posted, the fundis the
 * system can put it to, their answers, and the award. Posting, applications, materials and the work order stay where
 * they are (Modules 1, 7 and 11) — this desk only does the matching and records what was asked and answered.
 */
class MarketplaceController extends Controller
{
    /** The desk: every open project and how far its search has got. */
    public function index(Request $request)
    {
        $tab = $request->query('tab', 'all');
        $county = $request->query('county') ?: null;
        abort_unless(in_array($tab, ['all', 'unmatched', 'waiting', 'answered'], true), 404);

        $projects = Project::withCount(['matches', 'applications',
            'matches as invited_count' => fn ($q) => $q->where('status', 'invited'),
            'matches as accepted_count' => fn ($q) => $q->where('status', 'accepted'),
            'matches as quiet_count' => fn ($q) => $q->where('status', 'no_reply'),
        ])
            ->where('status', 'open')
            ->when($county, fn ($q) => $q->where('county', $county))
            ->when($tab === 'unmatched', fn ($q) => $q->doesntHave('matches'))
            ->when($tab === 'waiting', fn ($q) => $q->whereHas('matches', fn ($m) => $m->where('status', 'invited')))
            ->when($tab === 'answered', fn ($q) => $q->whereHas('matches', fn ($m) => $m->where('status', 'accepted')))
            ->orderByRaw('deadline is null, deadline')
            ->orderBy('id', 'desc')
            ->get();

        return view('admin.marketplace.index', [
            'projects' => $projects,
            'tab' => $tab,
            'county' => $county,
            'counties' => Project::where('status', 'open')->whereNotNull('county')->distinct()->orderBy('county')->pluck('county'),
            'queues' => Marketplace::queues(),
            'bar' => Marketplace::BAR,
        ]);
    }

    /** One project's match sheet — the rounds, the shortlist and the actions on them. */
    public function show(Project $project)
    {
        Marketplace::closeExpired($project->id);

        $rounds = $project->matches()->with('fundi')->orderByDesc('round')->orderByDesc('score')->get()
            ->groupBy('round');

        return view('admin.marketplace.show', [
            'project' => $project->load(['hirer', 'community', 'providedMaterials']),
            'rounds' => $rounds,
            'latest' => $rounds->keys()->max(),
            'needs' => Marketplace::needs($project),
            'applicants' => $project->applications()->with('project')->get(),
            'pool' => $this->pool($project),
            'bar' => Marketplace::BAR,
            'isExecutive' => Finance::isExecutive(),
        ]);
    }

    /**  /  — run the search again. Each run is a new round that keeps the old one underneath. */
    public function run(Request $request, Project $project)
    {
        $data = $request->validate([
            'widen' => 'nullable|boolean',
            'bar' => 'nullable|integer|min:'.Marketplace::MIN_BAR.'|max:100',
        ]);

        $bar = (int) ($data['bar'] ?? Marketplace::BAR);
        $opts = ['widen' => (bool) ($data['widen'] ?? false), 'bar' => $bar];

        abort_unless($bar < Marketplace::BAR ? Finance::isExecutive() : true, 403,
            'Lowering the standing bar below '.Marketplace::BAR.'% is an executive decision. The search can be run again without it.');

        $result = Marketplace::run($project, $opts);

        return back()->with('status', 'Round '.$result['round'].' matched '.$result['kept']
            .' fundi '.str('fundi')->plural($result['kept']).' for '.$project->reference.' — by '
            .Marketplace::ruleLabel($opts).'. '.($result['kept']
                ? 'Ask the top of the list and the 48 hours start when you do.'
                : 'Nobody cleared the bar. Widen the search or bring a fundi forward yourself.'));
    }

    /**  — put the project to the top of the shortlist. */
    public function invite(Request $request, Project $project)
    {
        $data = $request->validate([
            'round' => 'required|integer|min:1',
            'top' => 'required|integer|min:1|max:20',
        ]);

        $n = Marketplace::invite($project, (int) $data['round'], (int) $data['top']);

        return $n
            ? back()->with('status', $project->reference.' put to '.$n.' fundi '.str('fundi')->plural($n)
                .' — each has '.ProjectMatch::WINDOW_HOURS.' hours to answer.')
            : back()->with('error', 'Nothing on round '.$data['round'].' is waiting to be asked.');
    }

    /**  — a Director brings a fundi forward, including one the search passed over. */
    public function handPick(Request $request, Project $project)
    {
        abort_unless(Finance::isExecutive(), 403,
            'A hand-pick is a Director\'s decision. Staff can run the search, widen it and ask the shortlist.');

        $data = $request->validate([
            'fundi_id' => 'required|exists:fundi_registrations,id',
            'reason' => 'required|string|max:190',
        ], [], ['fundi_id' => 'E-Fundi', 'reason' => 'Reason']);

        $fundi = FundiRegistration::find($data['fundi_id']);
        $round = ((int) ProjectMatch::where('project_id', $project->id)->max('round')) ?: 1;

        $match = ProjectMatch::firstOrNew([
            'project_id' => $project->id, 'fundi_id' => $fundi->id, 'round' => $round,
        ], ['matched' => ['standing' => 'Brought forward by a Director, not by the search']]);

        $match->fill([
            'status' => 'invited',
            'invited_on' => now(),
            'note' => 'Hand-picked by '.auth()->user()->name.' — '.$data['reason'],
        ])->save();

        return back()->with('status', $fundi->full_name.' was brought forward on round '.$round.' and asked. The '
            .ProjectMatch::WINDOW_HOURS.'-hour window applies to them the same way.');
    }

    /**  — the project goes to a fundi who took it. */
    public function award(Project $project, ProjectMatch $match)
    {
        abort_unless($match->project_id === $project->id, 404);

        Marketplace::award($match);

        return back()->with('status', $project->reference.' assigned to '.$match->fundi->full_name
            .'. They are now on a job, and the work order is opened on the delivery desk.');
    }

    /**  — or, when nobody is chosen, the system takes the best of those who accepted. */
    public function autoAward(Project $project)
    {
        $top = ProjectMatch::where('project_id', $project->id)->where('status', 'accepted')
            ->orderByDesc('score')->first();

        return $top
            ? $this->award($project, $top)
            : back()->with('error', 'No fundi has accepted this project yet, so there is nothing to auto-pick.');
    }

    /** Everyone the office could put the project to, with what the search made of them. */
    private function pool(Project $project): array
    {
        $shortlisted = ProjectMatch::where('project_id', $project->id)->pluck('fundi_id')->all();

        return collect(Project::assignableMembers())
            ->filter(fn (array $m) => $m['type'] === 'fundi')
            ->map(fn (array $m) => $m + [
                'fits' => Project::fits($m, $project->category),
                'asked' => in_array($m['id'], $shortlisted, true),
            ])
            ->sortBy(fn (array $m) => [$m['asked'], $m['name']])
            ->values()->all();
    }
}
