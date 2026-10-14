<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AccountInvite;
use App\Models\County;
use App\Models\FundiRegistration;
use App\Models\ProjectApplication;
use App\Models\Trainer;
use App\Support\ReviewActions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/**
 * Module 5 — E-Fundi Management (§8). The fundi record every other module reads: grade, trade skills,
 * the counties they serve, whether they are free for work, and the sites they train CTPs at. The teaching
 * role itself (and its approval) stays in Module 4; the money and the job assignment stay where they are.
 */
class FundiController extends Controller
{
    public function index(Request $request)
    {
        $status = $request->query('status', 'all');
        abort_unless(in_array($status, ['all', ...array_keys(FundiRegistration::STATUSES)], true), 404);

        $tier = $request->query('tier', 'all');
        abort_unless(in_array($tier, ['all', ...array_keys(FundiRegistration::TIERS)], true), 404);

        $availability = $request->query('availability', 'all');
        abort_unless(in_array($availability, ['all', ...array_keys(FundiRegistration::AVAILABILITY)], true), 404);

        $county = $request->query('county', 'all');
        $skill = $request->query('skill', 'all');
        abort_unless($skill === 'all' || in_array($skill, FundiRegistration::SPECIALISATIONS, true), 404);

        $search = trim((string) $request->query('q'));

        $fundis = FundiRegistration::query()
            ->when($status !== 'all', fn ($q) => $q->where('status', $status))
            ->when($tier !== 'all', fn ($q) => $q->where('tier', $tier))
            ->when($availability !== 'all', fn ($q) => $q->where('availability', $availability))
            ->when($county !== 'all', fn ($q) => $q->where('county', $county))
            ->when($skill !== 'all', fn ($q) => $q->whereJsonContains('specialisations', $skill))
            ->when($search !== '', fn ($q) => $q->where(fn ($w) => $w
                ->where('ef_code', 'like', "%{$search}%")
                ->orWhere('reference', 'like', "%{$search}%")
                ->orWhere('full_name', 'like', "%{$search}%")
                ->orWhere('phone', 'like', "%{$search}%")
                ->orWhere('business_name', 'like', "%{$search}%")))
            ->withCount('trainers')
            ->with('mobiliser:id,ctp_code,full_name')
            ->orderByDesc('created_at')
            ->paginate(20)
            ->withQueryString();

        return view('admin.fundi.index', [
            'fundis' => $fundis,
            'status' => $status,
            'tier' => $tier,
            'availability' => $availability,
            'county' => $county,
            'skill' => $skill,
            'search' => $search,
            'counts' => FundiRegistration::selectRaw('status, count(*) c')->groupBy('status')->pluck('c', 'status'),
            'availableNow' => FundiRegistration::where('status', 'active')->where('availability', 'available')->count(),
            'tiers' => FundiRegistration::TIERS,
            'availabilities' => FundiRegistration::AVAILABILITY,
            'counties' => County::orderBy('name')->pluck('name'),
            'specialisations' => FundiRegistration::SPECIALISATIONS,
        ]);
    }

    /**
     * The office opens an E-Fundi application itself — the same questions the person fills online, typed
     * in for someone who cannot reach the website. It enters the same lifecycle: the fee is recorded
     * here, then review issues the E-Fundi ID and the portal login.
     */
    public function create()
    {
        return view('admin.fundi.apply', [
            'tiers' => FundiRegistration::TIERS,
            'specialisations' => FundiRegistration::SPECIALISATIONS,
            'counties' => County::orderBy('name')->pluck('name'),
            'fee' => FundiRegistration::feeAmount(),
        ]);
    }

    public function store(Request $request)
    {
        $fundi = FundiRegistration::create(FundiRegistration::officeApplicationFrom($request));
        $fundi->storeFiles($request);

        return redirect()->route('admin.fundis.show', $fundi)->with('status',
            $fundi->full_name.' added from the office as '.$fundi->reference
            .'. Record the KSh '.number_format($fundi::feeAmount()).' membership fee, then approve the record — '
            .'that is what issues the E-Fundi ID and opens their portal login.');
    }

    public function show(FundiRegistration $fundi)
    {
        $fundi->load(['mobiliser:id,ctp_code,full_name,county', 'user:id,name,email,active']);

        return view('admin.fundi.show', [
            'fundi' => $fundi,
            'docs' => $fundi->documents(),
            'sites' => $fundi->trainingSites(),
            'applications' => $fundi->applications()->with('project:id,reference,title,status,category')
                ->latest()->limit(15)->get(),
            'invite' => AccountInvite::where('member_type', 'fundi')->where('member_id', $fundi->id)->first(),
            'tiers' => FundiRegistration::TIERS,
            'availabilities' => FundiRegistration::AVAILABILITY,
            'specialisations' => FundiRegistration::SPECIALISATIONS,
            'counties' => County::orderBy('name')->pluck('name'),
            'isExecutive' => auth()->user()->role === 'superadmin',
        ]);
    }

    /** Grade moves the whole record: what a fundi can be handed and whether they may train CTPs (§8.2). */
    public function setTier(Request $request, FundiRegistration $fundi)
    {
        $data = $request->validate([
            'tier' => 'required|in:'.implode(',', array_keys(FundiRegistration::TIERS)),
        ], [], ['tier' => 'Grade']);

        if ($data['tier'] === $fundi->tier) {
            return back()->with('error', $fundi->full_name.' is already registered as '
                .FundiRegistration::tierLabel($fundi->tier).'.');
        }

        $fundi->update(['tier' => $data['tier']]);

        return back()->with('status', $fundi->full_name.' is now a '
            .FundiRegistration::tierLabel($data['tier'])
            .(in_array($data['tier'], Trainer::TEACHING_TIERS, true)
                ? ' — they can now be put forward to train CTPs at a site.'
                : ' — a grade below junior fundi cannot train CTPs.'));
    }

    /** The office can correct availability when a fundi stops answering or finishes a job without saying so. */
    public function setAvailability(Request $request, FundiRegistration $fundi)
    {
        $data = FundiRegistration::availabilityFrom($request);

        $fundi->update([
            'availability' => $data['availability'],
            'availability_note' => $data['availability_note'],
        ]);

        return back()->with('status', $fundi->full_name.' is marked '.$fundi->availabilityLabel().'.');
    }

    /**
     * Suspend or blacklist — a blocked fundi drops out of matching and their login locks, but the record
     * is never deleted (§8.4). Only a programme executive lifts a block, and the reason stays on file.
     */
    public function block(Request $request, FundiRegistration $fundi)
    {
        abort_unless(auth()->user()->role === 'superadmin', 403,
            'Only a programme executive can suspend or blacklist an E-Fundi — staff prepare the record, the office signs it off.');

        $data = $request->validate([
            'status' => 'required|in:suspended,blacklisted',
            'note' => 'required|string|max:1000',
        ], [], ['note' => 'Reason', 'status' => 'Decision']);

        $fundi->update(['status' => $data['status'], 'rejection_reason' => $data['note']]);
        $fundi->user?->update(['active' => false]);

        return back()->with('status', $fundi->full_name.' — '.$fundi->statusLabel().'. Their portal login is locked and they leave the work pool. '
            .'The record stays; a reinstatement reopens it.');
    }

    /** Reopen a blocked or rejected fundi. Nothing is added twice — the same row carries the history. */
    public function reinstate(FundiRegistration $fundi)
    {
        abort_unless(auth()->user()->role === 'superadmin', 403,
            'Only a programme executive can reinstate an E-Fundi.');

        abort_unless(in_array($fundi->status, [...FundiRegistration::BLOCKING, 'rejected'], true), 422,
            $fundi->full_name.' is already '.$fundi->statusLabel().'.');

        $fundi->update(['status' => 'active', 'rejection_reason' => null]);
        $fundi->user?->update(['active' => true]);

        return back()->with('status', $fundi->full_name.' is active again — '.$fundi->availabilityLabel().' for work, with the whole record intact.');
    }

    /** Skills and the counties covered. The fundi can edit the same two things on their own portal. */
    public function updateSkills(Request $request, FundiRegistration $fundi)
    {
        $data = FundiRegistration::skillsFrom($request);

        $fundi->update($data);

        return back()->with('status', 'Skills of '.$fundi->full_name.' updated — they now match '.count($data['specialisations'])
            .' trade'.(count($data['specialisations']) === 1 ? '' : 's').' across '.count($data['service_counties'])
            .' count'.(count($data['service_counties']) === 1 ? 'y' : 'ies').'.');
    }

    /** Where the fundi works, and the business they trade under. */
    public function updatePlace(Request $request, FundiRegistration $fundi)
    {
        $data = $request->validate([
            'county' => 'required|string|max:100',
            'sub_county' => 'nullable|string|max:100',
            'ward' => 'nullable|string|max:120',
            'business_name' => 'nullable|string|max:160',
            'business_reg_no' => 'nullable|string|max:60',
            'years_in_business' => 'nullable|integer|min:0|max:80',
        ], [], [
            'county' => 'County', 'sub_county' => 'Sub-county', 'business_name' => 'Business name',
            'business_reg_no' => 'Business registration number', 'years_in_business' => 'Years trading',
        ]);

        $fundi->update($data);

        return back()->with('status', 'Record of '.$fundi->full_name.' updated.');
    }

    public function markFee(Request $request, FundiRegistration $fundi)
    {
        return back()->with('status', ReviewActions::recordFee($fundi, ReviewActions::feeFrom($request)));
    }

    /** Issue the E-Fundi ID and open the portal account — inside Module 5, not sent back to Module 1. */
    public function approve(FundiRegistration $fundi)
    {
        $this->guardReview($fundi);

        return back()->with('status', ReviewActions::approve($fundi, 'ef_code', true));
    }

    public function reject(Request $request, FundiRegistration $fundi)
    {
        $this->guardReview($fundi);

        $data = $request->validate(['rejection_reason' => 'required|string|max:2000']);
        ReviewActions::reject($fundi, $data['rejection_reason']);

        return back()->with('status', $fundi->full_name.' rejected — the reason is shared with the applicant.');
    }

    public function reissue(FundiRegistration $fundi)
    {
        return back()->with('status', ReviewActions::reissue($fundi));
    }

    /** Private uploads open only through this fundi's own file route. */
    public function file(FundiRegistration $fundi, string $slot, Request $request)
    {
        $docs = $fundi->documents();
        abort_unless(isset($docs[$slot]), 404, 'That document is not on this record.');

        if ($request->boolean('download')) {
            return Storage::disk('local')->response($docs[$slot]['path']);
        }

        return response()->file(Storage::disk('local')->path($docs[$slot]['path']));
    }

    private function guardReview(FundiRegistration $fundi): void
    {
        abort_unless(in_array($fundi->status, ['pending_payment', 'pending_review', 'rejected'], true), 422,
            $fundi->full_name.' is '.$fundi->statusLabel().'. Only an open application or a rejection is decided here.');
    }
}
