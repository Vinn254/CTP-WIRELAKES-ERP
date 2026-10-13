<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Beneficiary;
use App\Models\Community;
use App\Models\CtpRegistration;
use App\Models\Need;
use App\Models\Project;
use App\Support\ReviewActions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Module 3 — Community Management. The community register: profile, the CTP who looks after it,
 * its needs, its beneficiaries and its timeline. Everything a staff member does to a community
 * is done here, inside the department.
 */
class CommunityController extends Controller
{
    public function index(Request $request)
    {
        $status = $request->query('status', 'all');
        abort_unless(in_array($status, ['all', ...array_keys(Community::STATUSES)], true), 404);
        $search = trim((string) $request->query('q'));

        $communities = Community::query()
            ->with('ctps:id,ctp_code,full_name')
            ->withCount(['needs', 'beneficiaries', 'projects'])
            ->when($status !== 'all', fn ($q) => $q->where('status', $status))
            ->when($search !== '', function ($q) use ($search) {
                $q->where(fn ($w) => $w
                    ->where('community_code', 'like', "%{$search}%")
                    ->orWhere('name', 'like', "%{$search}%")
                    ->orWhere('county', 'like', "%{$search}%")
                    ->orWhere('sub_county', 'like', "%{$search}%")
                    ->orWhere('ward', 'like', "%{$search}%"));
            })
            ->orderByRaw("field(status,'pending_review','active','rejected')")
            ->orderByDesc('created_at')
            ->paginate(20)
            ->withQueryString();

        return view('admin.communities.index', [
            'communities' => $communities,
            'status' => $status,
            'search' => $search,
            'counts' => Community::selectRaw('status, count(*) c')->groupBy('status')->pluck('c', 'status'),
            'proposedNeeds' => Need::where('status', 'proposed')->count(),
        ]);
    }

    public function show(Community $community)
    {
        $community->load([
            'ctps' => fn ($q) => $q->orderByRaw("field(role,'lead','support')"),
            'needs.project:id,reference,title,status',
            'needs.reviewer:id,name',
            'needs.assessor:id,ctp_code,full_name',
            'beneficiaries.project:id,reference,title',
            'projects' => fn ($q) => $q->with('community:id,name'),
            'creator:id,ctp_code,full_name,county',
            'approvedBy:id,name',
        ]);

        return view('admin.communities.show', [
            'community' => $community,
            'needs' => $community->needs,
            'projects' => $community->projects,
            'beneficiaries' => $community->beneficiaries,
            'candidates' => CtpRegistration::whereIn('status', CtpRegistration::WORKING)
                ->orderBy('full_name')->get(['id', 'ctp_code', 'full_name', 'county']),
            'openProjects' => Project::where('status', '!=', 'closed')
                ->orderByDesc('created_at')->get(['id', 'reference', 'title']),
        ]);
    }

    public function create()
    {
        return view('admin.communities.form', [
            'community' => new Community,
            'counties' => \App\Models\County::orderBy('name')->pluck('name'),
        ]);
    }

    public function edit(Community $community)
    {
        return view('admin.communities.form', [
            'community' => $community,
            'counties' => \App\Models\County::orderBy('name')->pluck('name'),
        ]);
    }

    /** Staff can open a community themselves — it does not wait for a CTP to propose it. */
    public function store(Request $request)
    {
        $community = Community::create([
            ...Community::placeData($request),
            'status' => 'active',
            'community_code' => Community::nextCode(),
            'created_by_user' => auth()->id(),
            'approved_by' => auth()->id(),
            'approved_at' => now(),
        ]);

        return redirect()
            ->route('admin.communities.show', $community)
            ->with('status', $community->community_code.' — '.$community->name.' added. Assign its CTP and log the needs.');
    }

    public function update(Request $request, Community $community)
    {
        $community->update(Community::placeData($request, $community->id));

        return back()->with('status', $community->name.' updated.');
    }

    /** Confirm a proposed community: issue COM-… once and put its proposer in charge of it. */
    public function approve(Community $community)
    {
        abort_unless(in_array($community->status, ['pending_review', 'rejected'], true), 422,
            'Only proposals waiting for review (or already rejected) can be confirmed.');

        $madeLead = false;

        DB::transaction(function () use ($community, &$madeLead) {
            $community->update([
                'status' => 'active',
                'community_code' => $community->community_code ?? Community::nextCode(),
                'rejection_reason' => null,
                'approved_by' => auth()->id(),
                'approved_at' => now(),
            ]);

            if (! $community->ctps()->wherePivot('role', 'lead')->exists() && $community->created_by_ctp) {
                $community->ctps()->syncWithoutDetaching([
                    $community->created_by_ctp => [
                        'role' => 'lead',
                        'assigned_by' => auth()->id(),
                        'assigned_at' => now(),
                    ],
                ]);
                $madeLead = true;
            }
        });

        return back()->with('status', 'Confirmed — '.$community->community_code.' issued.'
            .($madeLead ? ' '.$community->creator->full_name.' is now its lead CTP.' : ''));
    }

    public function reject(Request $request, Community $community)
    {
        $data = $request->validate(['rejection_reason' => 'required|string|max:2000']);
        ReviewActions::reject($community, $data['rejection_reason']);

        return back()->with('status', $community->name.' rejected as a duplicate or wrong place — the CTP who proposed it is told why.');
    }

    /** One lead looks after a community; the rest support it. */
    public function assignCtp(Request $request, Community $community)
    {
        $data = $request->validate([
            'ctp_id' => 'required|exists:ctp_registrations,id',
            'role' => 'required|in:lead,support',
        ], [], ['ctp_id' => 'CTP']);

        $ctp = CtpRegistration::findOrFail($data['ctp_id']);
        abort_unless(in_array($ctp->status, CtpRegistration::WORKING, true), 422,
            $ctp->full_name.' is '.$ctp->statusLabel().' — only an active, graduated or deployed CTP can look after a community.');

        if ($data['role'] === 'lead') {
            $community->ctps()->wherePivot('role', 'lead')->where('ctp_id', '!=', $ctp->id)
                ->get()->each(fn (CtpRegistration $other) => $community->ctps()->updateExistingPivot($other->id, ['role' => 'support']));
        }

        $community->ctps()->syncWithoutDetaching([
            $ctp->id => ['role' => $data['role'], 'assigned_by' => auth()->id(), 'assigned_at' => now()],
        ]);

        return back()->with('status', $ctp->full_name.' is now '
            .($data['role'] === 'lead' ? 'the lead CTP for' : 'supporting').' '.$community->name.'.');
    }

    public function removeCtp(Community $community, CtpRegistration $ctp)
    {
        abort_unless($community->ctps()->where('ctp_id', $ctp->id)->exists(), 404);
        $community->ctps()->detach($ctp->id);

        return back()->with('status', $ctp->full_name.' removed from the '.$community->name.' roster.');
    }

    public function storeBeneficiary(Request $request, Community $community)
    {
        $data = Beneficiary::dataFrom($request);

        $beneficiary = Beneficiary::create([
            ...$data,
            'community_id' => $community->id,
            'created_by_user' => auth()->id(),
        ]);

        return back()->with('status', $beneficiary->name.' recorded as a beneficiary of '.$community->name.'.');
    }

    /** The register of every beneficiary, filterable by community and type. */
    public function beneficiaries(Request $request)
    {
        $type = $request->query('type', 'all');
        abort_unless(in_array($type, ['all', ...array_keys(Beneficiary::TYPES)], true), 404);
        $search = trim((string) $request->query('q'));
        $communityId = $request->query('community');

        $beneficiaries = Beneficiary::with([
                'community:id,name,county,community_code',
                'project:id,reference,title',
                'creator:id,ctp_code,full_name',
                'staffUser:id,name',
            ])
            ->when($type !== 'all', fn ($q) => $q->where('type', $type))
            ->when($communityId, fn ($q) => $q->where('community_id', $communityId))
            ->when($search !== '', fn ($q) => $q->where('name', 'like', "%{$search}%"))
            ->latest('id')
            ->paginate(25)
            ->withQueryString();

        return view('admin.communities.beneficiaries', [
            'beneficiaries' => $beneficiaries,
            'community' => $communityId ? Community::find($communityId) : null,
            'type' => $type,
            'search' => $search,
            'counts' => Beneficiary::selectRaw('type, count(*) c')->groupBy('type')->pluck('c', 'type'),
        ]);
    }

    public function destroyBeneficiary(Beneficiary $beneficiary)
    {
        $beneficiary->delete();

        return back()->with('status', $beneficiary->name.' removed from the beneficiary count.');
    }
}
