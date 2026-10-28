<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BoqItem;
use App\Models\Project;
use App\Models\SiteAssessment;
use App\Models\SiteInspection;
use App\Models\SiteInspectionItem;
use App\Models\VariationOrder;
use App\Support\Construction;

/**
 * Module 8 — the construction file. One desk per buildable project: the site read, the priced design, the
 * labour counted, the inspections and the changes to what was agreed. Staff run all of it except the variation
 * decision, which a programme executive issues.
 */
class ConstructionController extends Controller
{
    private const TABS = [
        'live' => ['label' => 'Construction jobs', 'note' => 'Projects whose trade is buildable work, and any project a file has been opened on'],
        'assess' => ['label' => 'No site assessment yet', 'note' => 'Awarded construction work with nothing written about the site'],
        'flags' => ['label' => 'Open inspection failures', 'note' => 'Items that failed and have not been re-checked'],
        'variations' => ['label' => 'Variations', 'note' => 'Changes raised, waiting on or settled by a Director'],
    ];

    public function index(\Illuminate\Http\Request $request)
    {
        $tab = array_key_exists((string) $request->query('tab'), self::TABS) ? $request->query('tab') : 'live';
        $search = trim((string) $request->query('q'));

        $projects = Project::with(['hirer:id,organisation', 'assessments', 'boqItems', 'inspections.items', 'variations'])
            ->withCount('labourEntries')
            ->when($search !== '', fn ($q) => $q->where(fn ($w) => $w
                ->where('reference', 'like', "%{$search}%")
                ->orWhere('title', 'like', "%{$search}%")
                ->orWhere('trade', 'like', "%{$search}%")
                ->orWhere('county', 'like', "%{$search}%")))
            ->orderByDesc('id')
            ->get();

        $files = $projects->filter(fn (Project $p) => Construction::isConstruction($p))->values();

        $byTab = [
            'live' => $files,
            'assess' => $files->filter(fn (Project $p) => in_array($p->status, ['awarded', 'open'], true)
                && $p->assessments->isEmpty()),
            'flags' => $files->filter(fn (Project $p) => $p->inspections
                ->flatMap(fn (SiteInspection $i) => $i->items)->contains(fn ($item) => $item->result === 'fail'
                    && $item->rechecked_on === null)),
            'variations' => $files->filter(fn (Project $p) => $p->variations->isNotEmpty()),
        ];

        return view('admin.construction.index', [
            'tab' => $tab,
            'tabs' => self::TABS,
            'rows' => ($byTab[$tab] ?? $files)->values(),
            'counts' => collect($byTab)->map->count(),
            'search' => $search,
            'queues' => Construction::queues(),
            'trades' => Construction::SPECIALTIES,
        ]);
    }

    public function show(Project $project)
    {
        $project->load([
            'hirer:id,organisation', 'assessments.assessor', 'boqItems.variation', 'boqItems.withdrawer',
            'labourEntries.fundi', 'labourEntries.recorder', 'inspections.items.prover', 'inspections.inspector',
            'inspections.signer', 'variations.raiser', 'variations.decider',
        ]);

        $order = Construction::orderFor($project);

        return view('admin.construction.show', [
            'project' => $project,
            'order' => $order,
            'payout' => $order ? \App\Support\Execution::payoutFor($order) : null,
            'boqTotal' => Construction::boqTotal($project),
            'labourCost' => Construction::labourCost($project),
            'variations' => $project->variations,
            'openFailures' => $project->inspections->flatMap(fn (SiteInspection $i) => $i->items)
                ->where('result', 'fail')->whereNull('rechecked_on')->count(),
            'trades' => Construction::SPECIALTIES,
            'fundis' => \App\Models\FundiRegistration::where('status', 'approved')
                ->orderBy('full_name')->get(['id', 'full_name', 'ef_code']),
            'staff' => \App\Models\User::where('active', true)->orderBy('name')->get(['id', 'name']),
            'isExecutive' => \App\Support\Finance::isExecutive(),
        ]);
    }

    //  — the site visit, written up.
    public function assess(\Illuminate\Http\Request $request, Project $project)
    {
        Construction::recordAssessment($project, $request);

        return back()->with('status', 'Assessment of '.$project->reference.' filed. '
            .'Price the design on the BoQ once the site says it can go ahead.');
    }

    public function boq(\Illuminate\Http\Request $request, Project $project)
    {
        Construction::saveBoq($project, $request);

        $project->load('boqItems');

        return back()->with('status', 'Bill of quantities saved — '.\App\Support\Finance::ksh(Construction::boqTotal($project))
            .' across '.$project->boqItems->where('status', 'live')->count().' line(s). The total is the sum of the lines, never a typed figure.');
    }

    public function withdrawLine(\Illuminate\Http\Request $request, BoqItem $line)
    {
        Construction::removeBoqLine($line, $request);

        return back()->with('status', 'Line withdrawn. It stays on the BoQ with the reason, and is off the total.');
    }

    public function labour(\Illuminate\Http\Request $request, Project $project)
    {
        $day = Construction::recordLabour($project, $request);

        return back()->with('status', $day->worker_name.' counted for '
            .\Illuminate\Support\Carbon::parse($day->worked_on)->format('d M Y').' — '.$day->hours.' hours.');
    }

    // .
    public function schedule(\Illuminate\Http\Request $request, Project $project)
    {
        $inspection = Construction::scheduleInspection($project, $request);

        return back()->with('status', 'Inspection scheduled for '
            .$inspection->scheduled_on->format('d M Y').' with '.$inspection->items()->count().' item(s) to check.');
    }

    public function report(\Illuminate\Http\Request $request, SiteInspection $inspection)
    {
        $this->inspectionGate($inspection);
        Construction::reportInspection($inspection, $request);
        $failures = $inspection->openFailures();

        return back()->with('status', 'Report filed for the visit of '
            .$inspection->inspected_on->format('d M Y').($failures
                ? ' — '.$failures.' item(s) flagged failed. The worker answers each one with proof before it is re-checked.'
                : ' — every item passed or was not applicable.'));
    }

    public function recheck(\Illuminate\Http\Request $request, SiteInspectionItem $item)
    {
        $this->inspectionGate($item->inspection);
        Construction::recheckItem($item, $request);

        return back()->with('status', 'Re-check recorded on "'.$item->item.'": '.$item->resultLabel().'.');
    }

    public function signOff(\Illuminate\Http\Request $request, SiteInspection $inspection)
    {
        $this->inspectionGate($inspection);
        Construction::signOffInspection($inspection, $request);

        return back()->with('status', 'Inspection report signed off. The report, its items and the failures it cleared stay on the file.');
    }

    //  — staff raise the change with its cost.
    public function variation(\Illuminate\Http\Request $request, Project $project)
    {
        $variation = Construction::raiseVariation($project, $request);

        return back()->with('status', $variation->reference.' raised on '.($variation->workOrder->reference ?? 'the order')
            .' for '.\App\Support\Finance::ksh((float) $variation->extra_cost_ksh).'. A programme executive approves or rejects it.');
    }

    /**  — the decision, and everything an approval moves. */
    public function decide(\Illuminate\Http\Request $request, VariationOrder $variation)
    {
        $variation = Construction::decideVariation($variation, $request);

        return back()->with('status', $variation->reference.' '.$variation->status
            .' by '.(\App\Support\Finance::actorLabel()).'. '
            .($variation->status === 'approved'
                ? 'The work order, its stages and the BoQ have moved with it.'
                : 'The design stands as it was agreed.'));
    }

    public function assessmentFile(SiteAssessment $assessment)
    {
        return Construction::serve($assessment->photo_path, (string) $assessment->photo_name,
            $assessment->project->reference.'-assessment-'.$assessment->id);
    }

    public function inspectionPhoto(SiteInspection $inspection)
    {
        return Construction::serve($inspection->photo_path, (string) $inspection->photo_name,
            $inspection->project->reference.'-inspection-'.$inspection->id);
    }

    public function itemProof(SiteInspectionItem $item)
    {
        return Construction::serve($item->proof_path, (string) $item->proof_name,
            $item->inspection->project->reference.'-fix-'.$item->id);
    }

    /**
     * The inspection is reached through the project it belongs to. A staff account is not scoped by project, but the
     * url is never trusted on its own: the file must be the one the inspection sits on.
     */
    private function inspectionGate(SiteInspection $inspection): void
    {
        abort_unless($inspection->project()->exists(), 404, 'That inspection is not on a project file.');
    }
}
