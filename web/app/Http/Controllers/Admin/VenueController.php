<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Cohort;
use App\Models\Community;
use App\Models\County;
use App\Models\Partner;
use App\Models\TrainingVenue;
use Illuminate\Http\Request;

/** Module 4  — the places training is delivered: partner schools, community sites, programme centres. */
class VenueController extends Controller
{
    public function index(Request $request)
    {
        $search = trim((string) $request->input('q'));

        $venues = TrainingVenue::with(['partner:id,name', 'community:id,name,community_code', 'cohorts'])
            ->when($search !== '', fn ($q) => $q->where(fn ($s) => $s
                ->where('name', 'like', "%{$search}%")
                ->orWhere('county', 'like', "%{$search}%")
                ->orWhere('ward', 'like', "%{$search}%")))
            ->orderBy('county')->orderBy('name')->get();

        return view('admin.venues.index', [
            'venues' => $venues,
            'search' => $search,
            'counties' => County::orderBy('name')->pluck('name'),
            'partners' => Partner::orderBy('name')->get(['id', 'name']),
            'communities' => Community::where('status', '!=', 'rejected')
                ->orderBy('county')->orderBy('name')->get(['id', 'name', 'county']),
            'types' => TrainingVenue::TYPES,
        ]);
    }

    public function store(Request $request)
    {
        $venue = TrainingVenue::create([...TrainingVenue::dataFrom($request), 'status' => 'active']);

        return back()->with('status', $venue->name.' is on the venue list — cohorts can now be posted there.');
    }

    public function update(Request $request, TrainingVenue $venue)
    {
        $venue->update(TrainingVenue::dataFrom($request));

        return back()->with('status', $venue->name.' updated.');
    }

    /** Inactive venues cannot take a new cohort, but the cohorts already held there keep the record. */
    public function toggle(TrainingVenue $venue)
    {
        $venue->update(['status' => $venue->status === 'active' ? 'inactive' : 'active']);

        return back()->with('status', $venue->name.' is now '.($venue->status === 'active' ? 'open for cohorts.' : 'closed to new cohorts.'));
    }

    public function destroy(TrainingVenue $venue)
    {
        abort_if($venue->cohorts()->whereIn('status', ['draft', 'open', 'in_progress', 'completed'])->exists(), 422,
            $venue->name.' has held training. Deactivate it instead — the cohorts must keep their location.');

        $venue->delete();

        return back()->with('status', 'Venue removed.');
    }

    /** The venues a cohort form may offer: active ones, plus the one already chosen on an edit. */
    public static function options(?int $chosen = null)
    {
        return TrainingVenue::where('status', 'active')
            ->when($chosen, fn ($q) => $q->orWhere('id', $chosen))
            ->orderBy('county')->orderBy('name')->get(['id', 'name', 'county', 'sub_county', 'ward', 'capacity', 'status']);
    }
}
