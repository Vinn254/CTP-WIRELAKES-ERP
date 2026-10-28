<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Community;
use App\Models\County;
use App\Models\CtpRegistration;
use App\Models\FundiRegistration;
use App\Models\HirerRegistration;
use App\Models\InventoryItem;
use App\Models\Project;
use App\Models\ProjectApplication;
use App\Models\ProjectMaterial;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ProjectController extends Controller
{
    private function rules(): array
    {
        return [
            'title' => 'required|string|max:160',
            'description' => 'required|string|max:8000',
            'materials' => 'nullable|string|max:4000',
            'category' => 'required|in:'.implode(',', array_keys(Project::CATEGORIES)),
            'hirer_id' => 'nullable|exists:hirer_registrations,id',
            'trade' => 'nullable|string|max:80',
            'county' => 'nullable|string|max:80',
            'ward' => 'nullable|string|max:80',
            'community_id' => 'nullable|exists:communities,id',
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
            'candidates' => Project::assignableMembers(),
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
            'communities' => $this->communities(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate($this->rules());

        $project = Project::create($data + [
            'reference' => Project::nextCode(),
            'posted_by' => auth()->id(),
        ]);

        $rows = $this->materialRows($request);
        foreach ($rows as $row) {
            $project->providedMaterials()->create($row);
        }

        return redirect()
            ->route('admin.projects.show', $project)
            ->with('status', $project->reference.' posted with '.count($rows)
                .' material line'.(count($rows) === 1 ? '' : 's').'. Members whose level matches can now see it.');
    }

    /**
     * The materials block on the posting form. A row with no name is left blank and ignored; a row that gives
     * a quantity without naming the material is a mistake, so it is reported rather than silently dropped.
     */
    private function materialRows(Request $request): array
    {
        $clean = collect($request->input('material', []))
            ->map(fn ($row) => array_intersect_key((array) $row, array_flip(['name', 'quantity', 'unit', 'cost'])))
            ->filter(fn ($row): bool => trim((string) ($row['name'] ?? '')) !== '')
            ->values();

        $stray = collect($request->input('material', []))
            ->contains(fn ($row) => trim((string) (($row['name'] ?? ''))) === '' && ! empty($row['quantity']));

        if ($stray && $clean->isEmpty()) {
            abort(422, 'A material line needs its name — quantity alone cannot be shown to the fundi.');
        }

        return $clean->map(fn ($row) => [
            'name' => Str::limit(trim($row['name']), 190, ''),
            'quantity' => max(0.01, (float) ($row['quantity'] ?? 1)),
            'unit' => array_key_exists($row['unit'] ?? '', InventoryItem::UNITS) ? $row['unit'] : 'pcs',
            'unit_cost_ksh' => max(0, (float) ($row['cost'] ?? 0)),
        ])->all();
    }

    /** Add a material line after the project is posted — from a stock card, or one the market is supplying. */
    public function storeMaterial(Request $request, Project $project)
    {
        $data = ProjectMaterial::dataFrom($request);

        if (! empty($data['inventory_item_id'])) {
            $card = InventoryItem::findOrFail($data['inventory_item_id']);
            $data['name'] = $card->name;
            $data['unit'] = $card->unit;
            $data['unit_cost_ksh'] = $data['unit_cost_ksh'] ?? $card->unit_cost_ksh;
        }

        abort_if(blank($data['name'] ?? null), 422, 'Name the material, or pick the stock card it comes from — the fundi has to see exactly what they are given.');

        $material = $project->providedMaterials()->create([
            'name' => $data['name'],
            'inventory_item_id' => $data['inventory_item_id'] ?? null,
            'quantity' => $data['quantity'],
            'unit' => $data['unit'] ?? 'pcs',
            'unit_cost_ksh' => $data['unit_cost_ksh'] ?? 0,
            'note' => $data['note'] ?? null,
        ]);

        return back()->with('status', $material->name.' added to what '.$project->reference
            .' provides — '.$material->quantityLabel().' at '.$material->unitCostLabel().' each = '
            .$material->totalLabel().'. The fundi sees this line on the project.');
    }

    /** The store or the site hands it over: the line becomes part of what the payout deducts. */
    public function supplyMaterial(Request $request, ProjectMaterial $material)
    {
        $data = $request->validate([
            'supplied_on' => 'required|date',
            'note' => 'nullable|string|max:1000',
        ], [], ['supplied_on' => 'Date handed over']);

        abort_if($material->isSupplied(), 422, $material->name.' was already recorded as handed over on '
            .($material->supplied_on?->format('d M Y') ?? 'a date already on the line').'.');

        $material->update([
            'supplied_on' => $data['supplied_on'],
            'supplied_by_user' => auth()->id(),
            'note' => $data['note'] ?: $material->note,
        ]);

        return back()->with('status', $material->name.' recorded as handed over for '
            .($material->project->reference ?? 'the project').' — '.$material->totalLabel()
            .' now sits in the payout deduction. Goods from a stock card are also booked out on the card.');
    }

    /** Correct the quantity or the cost on a line that has not been handed over yet. */
    public function updateMaterial(Request $request, ProjectMaterial $material)
    {
        $data = $request->validate([
            'quantity' => 'required|numeric|min:0.01|max:1000000',
            'unit_cost_ksh' => 'required|numeric|min:0|max:100000000',
            'note' => 'nullable|string|max:1000',
        ], [], ['quantity' => 'Quantity', 'unit_cost_ksh' => 'Cost each']);

        abort_if($material->isSupplied(), 422, $material->name.' was handed over on '
            .($material->supplied_on?->format('d M Y') ?? 'a date already on the line')
            .', so its line stays as it was given. Add a second line for anything extra, and the payout takes both.');

        $material->update($data);

        return back()->with('status', $material->name.' corrected to '.$material->fresh()->quantityLabel()
            .' at '.$material->fresh()->unitCostLabel().' each = '.$material->fresh()->totalLabel().'.');
    }

    public function show(Project $project)
    {
        return view('admin.projects.show', [
            'project' => $project->load(['hirer', 'applications', 'award', 'poster', 'providedMaterials.item', 'providedMaterials.provider']),
            'candidates' => Project::assignableMembers(),
            'cards' => InventoryItem::where('status', 'active')->orderBy('name')->get(['id', 'sku', 'name', 'unit', 'unit_cost_ksh']),
            'units' => InventoryItem::UNITS,
        ]);
    }

    public function edit(Project $project)
    {
        return view('admin.projects.form', [
            'project' => $project,
            'counties' => County::orderBy('name')->pluck('name'),
            'hirers' => $this->hirers(),
            'communities' => $this->communities(),
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

        $previous = $project->award;
        $reassigning = $previous && $previous->id !== $application->id;

        $project->awardTo($application);

        return back()->with('status', $project->reference.' awarded to '.$application->full_name.'.'
            .($reassigning ? ' '.$previous->full_name.' has been told they were not selected.' : ''));
    }

    public function decline(Request $request, Project $project, ProjectApplication $application)
    {
        abort_unless($application->project_id === $project->id, 404);
        abort_if($application->status === 'approved', 422, 'This is the awarded applicant — release the award instead.');

        $data = $request->validate(['rejection_reason' => 'nullable|string|max:2000']);
        $application->update(['status' => 'rejected'] + $data + ['reviewed_by' => auth()->id()]);

        return back()->with('status', $application->full_name.' declined.');
    }

    /** Hand the job to any approved member — they do not have to have applied. */
    public function assignMember(Request $request, Project $project)
    {
        $data = $request->validate([
            'applicant_type' => 'required|in:ctp,fundi',
            'applicant_id' => 'required|integer',
        ]);

        $member = $data['applicant_type'] === 'fundi'
            ? FundiRegistration::where('status', 'active')->find($data['applicant_id'])
            : CtpRegistration::where('status', 'active')->find($data['applicant_id']);

        if (! $member) {
            return back()->with('error', 'That member is not an approved, active account.');
        }

        $application = ProjectApplication::firstOrNew([
            'project_id' => $project->id,
            'applicant_type' => $data['applicant_type'],
            'applicant_id' => $member->id,
        ], [
            'full_name' => $member->full_name,
            'phone' => $member->phone,
            'email' => $member->email,
            'note' => 'Hand-picked for this job by '.auth()->user()->name.', '.$project->reference.'.',
            'status' => 'pending_review',
        ]);
        $application->save();

        return $this->award($project, $application);
    }

    public function releaseAward(Project $project)    {
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

    private function communities()
    {
        return Community::where('status', 'active')
            ->orderBy('county')->orderBy('name')
            ->get(['id', 'name', 'county', 'ward', 'community_code']);
    }
}
