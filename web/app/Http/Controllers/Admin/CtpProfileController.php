<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AccountInvite;
use App\Models\CtpRegistration;
use App\Models\Project;
use App\Models\ProjectApplication;
use App\Support\ReviewActions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/**
 * Module 2 — CTP Promoter Management. The profile every other module reads:
 * identity, track, location, referral lineage, fee, mobilisation and deployment.
 */
class CtpProfileController extends Controller
{
    public function index(Request $request)
    {
        $status = $request->query('status', 'all');
        abort_unless(in_array($status, ['all', ...array_keys(CtpRegistration::STATUSES)], true), 404);
        $search = trim((string) $request->query('q'));

        $ctps = CtpRegistration::query()
            ->when($status !== 'all', fn ($q) => $q->where('status', $status))
            ->when($search !== '', function ($q) use ($search) {
                $q->where(fn ($w) => $w
                    ->where('ctp_code', 'like', "%{$search}%")
                    ->orWhere('full_name', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%")
                    ->orWhere('county', 'like', "%{$search}%"));
            })
            ->withCount(['referrals as mobilised_count' => fn ($q) => $q->whereIn('status', CtpRegistration::WORKING)])
            ->orderByDesc('created_at')
            ->paginate(20)
            ->withQueryString();

        return view('admin.ctp.index', [
            'ctps' => $ctps,
            'status' => $status,
            'search' => $search,
            'counts' => CtpRegistration::selectRaw('status, count(*) c')->groupBy('status')->pluck('c', 'status'),
            'counties' => CtpRegistration::whereNotNull('county')->distinct()->orderBy('county')->pluck('county'),
        ]);
    }

    public function show(CtpRegistration $ctp)
    {
        $ctp->load(['referrer:id,ctp_code,full_name,county', 'user:id,name,email,active']);

        return view('admin.ctp.show', [
            'ctp' => $ctp,
            'docs' => $this->documents($ctp),
            'referrals' => $ctp->referrals()->latest()->limit(25)->get(),
            'projects' => ProjectApplication::where('applicant_type', 'ctp')
                ->where('applicant_id', $ctp->id)
                ->with('project:id,reference,title,status')
                ->latest()->limit(10)->get(),
            'candidates' => CtpRegistration::whereIn('status', CtpRegistration::WORKING)
                ->where('id', '!=', $ctp->id)
                ->orderBy('full_name')->get(['id', 'ctp_code', 'full_name']),
            'tiers' => Project::CTP_POOL_TIERS,
            'invite' => AccountInvite::where('member_type', 'ctp')->where('member_id', $ctp->id)->first(),
        ]);
    }

    public function edit(CtpRegistration $ctp)
    {
        return view('admin.ctp.form', [
            'ctp' => $ctp,
            'counties' => \App\Models\County::orderBy('name')->pluck('name'),
        ]);
    }

    public function update(Request $request, CtpRegistration $ctp)
    {
        $data = $request->validate([
            'county' => 'required|string|max:100',
            'sub_county' => 'required|string|max:100',
            'ward' => 'required|string|max:100',
            'village' => 'nullable|string|max:100',
            'gps_lat' => 'nullable|numeric|between:-90,90',
            'gps_lng' => 'nullable|numeric|between:-180,180',
            'education_level' => 'required|in:'.implode(',', array_keys(CtpRegistration::EDUCATION_LEVELS)),
            'track' => 'required|in:'.implode(',', array_keys(CtpRegistration::TRACKS)),
            'year_left_school' => 'nullable|integer|between:1960,'.now()->year,
            'certificates' => 'nullable|array|max:8',
            'certificates.*' => 'string|max:120',
            'service_counties' => 'nullable|array|max:47',
            'service_counties.*' => 'string|max:100',
            'available_for_mobilisation' => 'nullable|boolean',
            'trainer_opt_in' => 'nullable|boolean',
        ], [], [
            'gps_lat' => 'GPS latitude', 'gps_lng' => 'GPS longitude',
            'year_left_school' => 'Year left school',
        ]);

        $ctp->update([
            ...collect($data)->except(['certificates', 'available_for_mobilisation', 'trainer_opt_in'])->all(),
            'certificates_held' => array_values(array_filter($data['certificates'] ?? [])),
            'service_counties' => array_values(array_unique($data['service_counties'] ?? [])),
            'available_for_mobilisation' => $request->boolean('available_for_mobilisation'),
            'trainer_opt_in' => $request->boolean('trainer_opt_in'),
        ]);

        return redirect()->route('admin.promoters.show', $ctp)->with('status', 'Profile of '.$ctp->full_name.' updated.');
    }

    /** The lifecycle moves in §2.6 — suspension also locks the member account. */
    public function setStatus(Request $request, CtpRegistration $ctp)
    {
        $data = $request->validate([
            'status' => 'required|in:suspended,active,graduated,deployed',
            'status_note' => 'nullable|string|max:500',
        ]);

        $to = $data['status'];
        $from = $ctp->status;

        if ($to === $from) {
            return back()->with('error', $ctp->full_name.' is already '.$ctp->statusLabel().'.');
        }

        $allowed = [
            'suspended' => CtpRegistration::WORKING,
            'active' => ['suspended', 'pending_review'],
            'graduated' => ['active'],
            'deployed' => ['active', 'graduated'],
        ];

        if (! in_array($from, $allowed[$to], true)) {
            return back()->with('error', $ctp->statusLabel().' cannot become '.CtpRegistration::STATUSES[$to].' — '
                .$ctp->full_name.' stays '.$ctp->statusLabel().'.');
        }

        $ctp->update(['status' => $to, 'status_note' => $data['status_note'] ?? null]);

        if ($ctp->user) {
            $ctp->user->update(['active' => $to !== 'suspended']);
        }

        return back()->with('status', match ($to) {
            'suspended' => $ctp->full_name.' suspended — their portal login is locked until you reinstate them.',
            'active' => $ctp->full_name.' reinstated as an active CTP.',
            'graduated' => $ctp->full_name.' has completed training and is marked graduated.',
            'deployed' => $ctp->full_name.' is deployed — they now carry projects and field work.',
        });
    }

    /**
     * Module 2 settles the fee on its own screen — staff working a CTP are never sent out of the
     * department to do it. Recording it moves the record into the review queue.
     */
    public function markFee(Request $request, CtpRegistration $ctp)
    {
        return back()->with('status', ReviewActions::recordFee($ctp, ReviewActions::feeFrom($request)));
    }

    /** Issue the CTP ID and open the portal account, without leaving Module 2. */
    public function approve(CtpRegistration $ctp)
    {
        return back()->with('status', ReviewActions::approve($ctp, 'ctp_code', true));
    }

    public function reject(Request $request, CtpRegistration $ctp)
    {
        $data = $request->validate(['rejection_reason' => 'required|string|max:2000']);
        ReviewActions::reject($ctp, $data['rejection_reason']);

        return back()->with('status', $ctp->full_name.' rejected — the reason is shared with the applicant.');
    }

    public function reissue(CtpRegistration $ctp)
    {
        return back()->with('status', ReviewActions::reissue($ctp));
    }

    /** Documents are read here too, so a CTP file never needs a Module 1 url. */
    public function file(CtpRegistration $ctp, string $slot, Request $request)
    {
        $docs = $this->documents($ctp);
        abort_unless(isset($docs[$slot]), 404, 'Document not uploaded.');

        if ($request->boolean('download')) {
            return Storage::disk('local')->response($docs[$slot]['path']);
        }

        return response()->file(Storage::disk('local')->path($docs[$slot]['path']));
    }

    /** Staff can fix the lineage when the applicant mistyped or the code did not match. */
    public function setReferrer(Request $request, CtpRegistration $ctp)
    {
        $data = $request->validate(['referrer_id' => 'nullable|exists:ctp_registrations,id']);
        $referrer = isset($data['referrer_id']) ? CtpRegistration::findOrFail($data['referrer_id']) : null;

        if ($referrer && $referrer->id === $ctp->id) {
            return back()->with('error', 'A CTP cannot mobilise themselves.');
        }

        $ctp->update([
            'referred_by' => $referrer?->id,
            'referred_at' => $referrer ? now() : null,
            'referral_code' => $referrer?->ctp_code ?? $ctp->referral_code,
        ]);

        return back()->with('status', $referrer
            ? $ctp->full_name.' is now counted as a referral of '.$referrer->ctp_code.' · '.$referrer->full_name.'.'
            : 'Mobiliser unlinked — '.$ctp->full_name.' no longer counts toward anyone.');
    }

    /** Which projects this CTP can see once they enter the fundi pool. */
    public function setTier(Request $request, CtpRegistration $ctp)
    {
        $data = $request->validate(['fundi_tier' => 'nullable|in:'.implode(',', array_keys(Project::CTP_POOL_TIERS))]);
        ReviewActions::setPoolTier($ctp, $data['fundi_tier'] ?? null);

        return back()->with('status', $ctp->full_name.' now sees '.$ctp->poolLabel().' projects.');
    }

    private function documents(CtpRegistration $ctp): array
    {
        $slots = [
            'id_scan' => ['id_scan_path', 'National ID scan'],
            'certificate' => ['certificate_path', 'School / college certificate'],
            'passport' => ['passport_photo_path', 'Passport photo'],
        ];

        $out = [];
        foreach ($slots as $key => [$column, $label]) {
            $path = $ctp->{$column};
            if ($path && Storage::disk('local')->exists($path)) {
                $out[$key] = ['label' => $label, 'path' => $path];
            }
        }

        return $out;
    }
}
