<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Community;
use App\Models\FundiRegistration;
use App\Models\HirerRegistration;
use App\Models\Project;
use App\Models\TechnologyAsset;
use App\Models\TechnologyProject;
use App\Models\TechnologyTicket;
use App\Support\Technology;
use Illuminate\Http\Request;

/**
 * Module 9 — Technology Services. The office runs the three registers: the asset register, the project register
 * and the repair tickets. The fundi answers their own tickets on the member side.
 */
class TechnologyController extends Controller
{
    private const TABS = [
        'assets' => ['label' => 'Asset register', 'note' => 'Every device, licence and kit, cut by sub-area'],
        'projects' => ['label' => 'Technology projects', 'note' => 'Robotics, programming and AI work the programme delivers'],
        'tickets' => ['label' => 'Repair tickets', 'note' => 'Faults opened against an asset, assigned and closed'],
        'alerts' => ['label' => 'Warranty & licence alerts', 'note' => 'Cover running out inside '.Technology::ALERT_WINDOW_DAYS.' days'],
    ];

    public function index(Request $request)
    {
        $tab = array_key_exists((string) $request->query('tab'), self::TABS) ? $request->query('tab') : 'assets';
        $category = (string) $request->query('category');
        $search = trim((string) $request->query('q'));

        $byCategory = fn ($q) => $q->when(array_key_exists($category, Technology::CATEGORIES), fn ($x) => $x->where('category', $category));
        $like = fn ($q, array $cols) => $q->when($search !== '', fn ($x) => $x->where(
            fn ($w) => collect($cols)->reduce(fn ($c, $col) => $c->orWhere($col, 'like', "%{$search}"), $w->whereRaw('1=0'))
        ));

        $assets = TechnologyAsset::query()->where('status', '!=', 'retired')
            ->tap($byCategory)->tap(fn ($q) => $like($q, ['asset_code', 'name', 'kind', 'location', 'county']))
            ->orderByDesc('id')->get();

        $projects = TechnologyProject::with(['mentor:id,full_name', 'community:id,name', 'project:id,title,reference'])
            ->tap($byCategory)->tap(fn ($q) => $like($q, ['reference', 'title', 'technology']))
            ->orderByDesc('id')->get();

        $tickets = TechnologyTicket::with(['asset:id,asset_code,name,category', 'fundi:id,full_name'])
            ->whereIn('status', ['open', 'assigned', 'in_progress', 'resolved'])
            ->tap(fn ($q) => $q->when($category !== '', fn ($x) => $x->whereHas('asset', fn ($a) => $a->where('category', $category))))
            ->tap(fn ($q) => $like($q, ['reference', 'title']))
            ->orderByRaw("field(status,'open','assigned','in_progress','resolved')")->orderByDesc('id')->get();

        $rows = ['assets' => $assets, 'projects' => $projects, 'tickets' => $tickets, 'alerts' => Technology::expiringSoon()][$tab];

        return view('admin.technology.index', [
            'tab' => $tab,
            'tabs' => self::TABS,
            'rows' => $rows,
            'counts' => ['assets' => $assets->count(), 'projects' => $projects->count(), 'tickets' => $tickets->count(), 'alerts' => Technology::expiringSoon()->count()],
            'categories' => Technology::CATEGORIES,
            'category' => $category,
            'queues' => Technology::queues(),
            'search' => $search,
            'openTicketAssets' => $tab === 'tickets' ? TechnologyTicket::where('status', 'closed')->count() : null,
            // The register's popup sheets reuse the create forms, so they need the same lists create() passes.
            'newAsset' => new TechnologyAsset(['status' => 'operational', 'source' => 'purchase', 'category' => 'ict']),
            'assetCommunities' => Community::orderBy('name')->get(['id', 'name']),
            'assetOrganisations' => HirerRegistration::orderBy('organisation')->get(['id', 'organisation']),
            'newTechProject' => new TechnologyProject(['status' => 'identified', 'category' => 'ai']),
            'techProjectLists' => $this->projectLists(),
        ]);
    }

    public function createAsset()
    {
        return view('admin.technology.asset-form', [
            'asset' => new TechnologyAsset(['status' => 'operational', 'source' => 'purchase', 'category' => 'ict']),
            'isNew' => true,
            'communities' => Community::orderBy('name')->get(['id', 'name']),
            'organisations' => HirerRegistration::orderBy('organisation')->get(['id', 'organisation']),
            'categories' => Technology::CATEGORIES,
        ]);
    }

    public function storeAsset(Request $request)
    {
        $asset = Technology::saveAsset(new TechnologyAsset, $request, true);

        return redirect()->route('admin.technology.asset', $asset)
            ->with('status', $asset->asset_code.' added to the '.$asset->categoryLabel().' register.');
    }

    public function showAsset(TechnologyAsset $asset)
    {
        $asset->load(['tickets.fundi:id,full_name', 'ewasteCollection']);

        return view('admin.technology.asset', [
            'asset' => $asset,
            'communities' => Community::orderBy('name')->get(['id', 'name']),
            'organisations' => HirerRegistration::orderBy('organisation')->get(['id', 'organisation']),
            'fundis' => FundiRegistration::where('status', 'active')->orderBy('full_name')->get(['id', 'full_name', 'ef_code']),
            'categories' => Technology::CATEGORIES,
            'edit' => true,
        ]);
    }

    public function updateAsset(Request $request, TechnologyAsset $asset)
    {
        $asset = Technology::saveAsset($asset, $request, false);

        return redirect()->route('admin.technology.asset', $asset)
            ->with('status', $asset->asset_code.' updated.');
    }

    public function decommission(Request $request, TechnologyAsset $asset)
    {
        $asset = Technology::decommission($asset, $request);

        return back()->with('status', $asset->asset_code.' retired'.($asset->ewaste_collection_id
            ? ' and routed to E-Waste ('.$asset->ewasteCollection->reference.'), feeding the E-Waste register.'
            : '. It stays on the register with the reason.'));
    }

    public function openTicket(Request $request, TechnologyAsset $asset)
    {
        $ticket = Technology::openTicket($asset, $request);

        return back()->with('status', $ticket->reference.' opened on '.$asset->asset_code.'. Assign it to a fundi to fix.');
    }

    public function assignTicket(Request $request, TechnologyTicket $ticket)
    {
        $ticket = Technology::assignTicket($ticket, $request);

        return back()->with('status', $ticket->reference.' assigned to '.($ticket->fundi->full_name ?? 'a fundi').'.');
    }

    public function resolveTicket(Request $request, TechnologyTicket $ticket)
    {
        $ticket = Technology::resolveTicket($ticket, $request);

        return back()->with('status', $ticket->reference.' recorded as fixed. Confirm and close it.');
    }

    public function closeTicket(TechnologyTicket $ticket)
    {
        $ticket = Technology::closeTicket($ticket);

        return back()->with('status', $ticket->reference.' closed. It stays on the asset\'s repair history.');
    }

    public function createProject()
    {
        return view('admin.technology.project-form', [
            'project' => new TechnologyProject(['status' => 'identified', 'category' => 'ai']),
            'isNew' => true,
            'categories' => Technology::CATEGORIES,
        ] + $this->projectLists());
    }

    public function storeProject(Request $request)
    {
        $project = Technology::saveProject(new TechnologyProject, $request, true);

        return redirect()->route('admin.technology.project', $project)
            ->with('status', $project->reference.' — '.$project->title.' recorded under '.$project->categoryLabel().'.');
    }

    public function showProject(TechnologyProject $project)
    {
        $project->load(['mentor:id,full_name,ef_code', 'community', 'project']);

        return view('admin.technology.project-form', [
            'project' => $project,
            'isNew' => false,
            'categories' => Technology::CATEGORIES,
        ] + $this->projectLists());
    }

    public function updateProject(Request $request, TechnologyProject $project)
    {
        $project = Technology::saveProject($project, $request, false);

        return redirect()->route('admin.technology.project', $project)
            ->with('status', $project->reference.' updated: '.$project->statusLabel().'.');
    }

    private function projectLists(): array
    {
        return [
            'fundis' => FundiRegistration::where('status', 'active')->orderBy('full_name')->get(['id', 'full_name', 'ef_code']),
            'communities' => Community::orderBy('name')->get(['id', 'name', 'county']),
            'projects' => Project::orderByDesc('id')->get(['id', 'reference', 'title']),
        ];
    }
}
