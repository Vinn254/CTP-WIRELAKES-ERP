<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Community;
use App\Models\CtpRegistration;
use App\Models\EWasteCollection;
use App\Models\FundiRegistration;
use App\Models\Project;
use App\Models\TrainingVenue;
use App\Support\Departments;
use App\Support\Impact;
use App\Support\KenyaMap;
use Illuminate\Http\Request;

/**
 * Module 12 — monitoring and evaluation (§15.2–§15.4). The dashboard and the map read the other modules' tables
 * at request time and store nothing, so an indicator can never be edited to a nicer number.
 */
class MeGisController extends Controller
{
    public function index(Request $request)
    {
        $county = $this->county($request);

        return view('admin.me-gis.dashboard', [
            'county' => $county,
            'counties' => Impact::counties(),
            'headline' => Impact::headline($county),
            'groups' => Impact::indicators($county),
            'sectors' => Impact::sectors($county),
            'waiting' => Departments::waiting('me_gis'),
        ]);
    }

    /**
     * §15.4 as a county tile map: every record in the programme carries a county name, so the map is drawn from
     * the counties themselves. One layer at a time is shaded, and a tile opens that county's records on the layer.
     */
    public function map(Request $request)
    {
        $layer = $request->query('layer', 'communities');
        $layers = Impact::layers();
        $layer = array_key_exists($layer, $layers) ? $layer : 'communities';
        $selected = trim((string) $request->query('county'));
        $counts = $layers[$layer]['counts'];
        $board = KenyaMap::tiles($counts);
        $peak = max(1, $counts ? max($counts) : 1);

        return view('admin.me-gis.map', [
            'layer' => $layer,
            'layers' => $layers,
            'selected' => $selected !== '' ? $selected : null,
            'tiles' => $board['board'],
            'offBoard' => $board['unmatched'],
            'peak' => $peak,
            'total' => array_sum($counts),
            'records' => $selected !== '' ? $this->records($layer, $selected) : collect(),
        ]);
    }

    private function county(Request $request): ?string
    {
        $county = trim((string) $request->query('county'));

        return $county !== '' ? $county : null;
    }

    /** The records behind one tile, with the staff screen each one is worked on. */
    private function records(string $layer, string $county)
    {
        return match ($layer) {
            'communities' => Community::where('county', $county)->orderBy('name')->get(['id', 'name', 'community_code', 'ward', 'status'])
                ->map(fn ($r) => ['code' => $r->community_code, 'title' => $r->name, 'meta' => $r->ward.' · '.ucwords(str_replace('_', ' ', $r->status)), 'url' => route('admin.communities.show', $r)]),
            'ctps' => CtpRegistration::where('county', $county)->orderBy('full_name')->get(['id', 'full_name', 'ctp_code', 'status', 'track'])
                ->map(fn ($r) => ['code' => $r->ctp_code, 'title' => $r->full_name, 'meta' => ($r->trackLabel()).' · '.($r->statusLabel()), 'url' => route('admin.promoters.show', $r)]),
            'fundis' => FundiRegistration::where('county', $county)->orderBy('full_name')->get(['id', 'full_name', 'ef_code', 'tier', 'status'])
                ->map(fn ($r) => ['code' => $r->ef_code, 'title' => $r->full_name, 'meta' => FundiRegistration::tierLabel($r->tier).' · '.$r->statusLabel(), 'url' => route('admin.fundis.show', $r)]),
            'venues' => TrainingVenue::where('county', $county)->orderBy('name')->get(['id', 'name', 'ward', 'capacity'])
                ->map(fn ($r) => ['code' => null, 'title' => $r->name, 'meta' => trim($r->ward.', ','').'seats '.$r->capacity, 'url' => route('admin.venues.index')]),
            'projects' => Project::where('county', $county)->latest('id')->get(['id', 'reference', 'title', 'trade', 'status'])
                ->map(fn ($r) => ['code' => $r->reference, 'title' => $r->title, 'meta' => ($r->trade ?: 'trade not typed').' · '.(Project::STATUSES[$r->status] ?? $r->status), 'url' => route('admin.projects.show', $r)]),
            'ewaste' => EWasteCollection::where('county', $county)->latest('id')->get(['id', 'reference', 'item_type', 'status', 'weighed_weight_kg', 'estimated_weight_kg'])
                ->map(fn ($r) => ['code' => $r->reference, 'title' => $r->itemTypeLabel(), 'meta' => $r->statusLabel().' · '.round((float) ($r->weighed_weight_kg ?? $r->estimated_weight_kg), 1).' kg', 'url' => route('admin.ewaste.index', ['q' => $r->reference])]),
            default => collect(),
        };
    }
}
