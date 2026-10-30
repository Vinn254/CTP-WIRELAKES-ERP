<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Cohort;
use App\Models\County;
use App\Models\CtpRegistration;
use App\Models\Enrollment;
use App\Models\FundiRegistration;
use App\Models\ProjectMatch;
use App\Models\Trainer;
use App\Support\Marketplace;
use App\Support\MemberPortal;
use App\Support\UploadsFromJson;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/**
 * The three signature member screens, served as JSON for the app — the exact web portal screens. The member is
 * resolved from the token, the same fundi()/ctp() kind guards and the same Marketplace/MemberPortal logic decide
 * what they see and what they may write. No rule is re-invented here; nothing that needs a file upload is exposed
 * yet (that is a later slice).
 */
class MemberPortalApiController extends Controller
{
    use UploadsFromJson;

    private function member()
    {
        $member = MemberPortal::resolve(auth()->user());
        abort_if(! $member, 403, 'Your member record could not be found. Contact the programme office.');

        return $member;
    }

    private function fundi(): FundiRegistration
    {
        $member = $this->member();
        abort_unless($member instanceof FundiRegistration, 403, 'That part of the portal is for E-Fundis.');

        return $member;
    }

    private function ctp(): CtpRegistration
    {
        $member = $this->member();
        abort_unless($member instanceof CtpRegistration, 403, 'That part of the portal is for CTPs.');

        return $member;
    }

    // ---- My E-Fundi record ------------------------------------------------

    /** GET /api/efundi — the fundi's own record: grade, availability, skills, sites and paperwork. */
    public function efundi(): JsonResponse
    {
        $fundi = $this->fundi();
        $docs = collect($fundi->documents())
            ->map(fn (array $d, string $slot) => ['slot' => $slot, 'label' => $d['label']])
            ->values();

        // The grade card the web portal builds from capabilities() — what this grade opens and what stays shut.
        $open = $fundi->capabilities();
        $card = fn (string $ability) => [
            'key' => $ability,
            'title' => FundiRegistration::CAPABILITY_LABELS[$ability][0],
            'blurb' => FundiRegistration::CAPABILITY_LABELS[$ability][1],
        ];

        return response()->json([
            'level' => FundiRegistration::tierLabel($fundi->tier),
            'tier' => $fundi->tier,
            'status' => $fundi->status,
            'status_label' => $fundi->statusLabel(),
            'place' => $fundi->placeLabel(),
            'can_train' => $fundi->canTrain(),
            'capabilities' => array_map($card, $open),
            'locked' => array_map($card, array_values(array_diff(array_keys(FundiRegistration::CAPABILITY_LABELS), $open))),
            'availability' => $fundi->availability,
            'availability_label' => $fundi->availabilityLabel(),
            'availability_note' => $fundi->availability_note,
            'availability_options' => FundiRegistration::AVAILABILITY,
            'specialisations' => array_values((array) $fundi->specialisations),
            'specialisation_options' => FundiRegistration::SPECIALISATIONS,
            'service_counties' => array_values((array) $fundi->service_counties),
            'county_options' => County::orderBy('name')->pluck('name'),
            'sites' => $fundi->trainingSites()->map(fn (Trainer $t) => [
                'venue' => $t->venue?->name,
                'county' => $t->venue?->county,
                'status' => $t->status,
                'status_label' => Trainer::STATUSES[$t->status] ?? $t->status,
                'trainings' => $t->trainings->pluck('name')->values(),
            ])->values(),
            'documents' => $docs,
        ]);
    }

    /** POST /api/efundi/availability */
    public function saveAvailability(Request $request): JsonResponse
    {
        $fundi = $this->fundi();
        $fundi->update(FundiRegistration::availabilityFrom($request));

        return response()->json([
            'message' => 'You are showing as '.$fundi->availabilityLabel().'.'
                .($fundi->availability === 'available' ? ' The office and matching now see you in the work pool.' : ''),
        ]);
    }

    /** POST /api/efundi/skills */
    public function saveSkills(Request $request): JsonResponse
    {
        $fundi = $this->fundi();
        $fundi->update(FundiRegistration::skillsFrom($request));

        return response()->json(['message' => 'Your skills are updated. The office reviews the record when a job is matched to you.']);
    }

    /** GET /api/efundi/file/{slot} — a document on their own record; the slot is read from the record, never trusted. */
    public function efundiFile(string $slot): JsonResponse
    {
        $fundi = $this->fundi();
        $docs = $fundi->documents();
        abort_unless(isset($docs[$slot]), 404, 'That file is not on your record.');

        $path = $docs[$slot]['path'];
        abort_unless(Storage::disk('local')->exists($path), 404);

        return response()->json($this->inline($path, $docs[$slot]['label']));
    }

    // ---- Invited to you (fundi marketplace) ------------------------------

    /** GET /api/invitations — the projects the desk put to this fundi and the 48 hours to answer. */
    public function invitations(): JsonResponse
    {
        $fundi = $this->fundi();
        Marketplace::closeExpiredFor($fundi);

        $invites = ProjectMatch::for($fundi)->with(['project.hirer', 'project.providedMaterials'])
            ->orderByRaw("case status when 'invited' then 0 when 'accepted' then 1 when 'awarded' then 2 else 3 end")
            ->orderByDesc('id')->get();

        return response()->json([
            'window' => ProjectMatch::WINDOW_HOURS,
            'invites' => $invites->map(fn (ProjectMatch $m) => $this->invite($m))->values(),
            'counts' => [
                'outstanding' => $invites->where('status', 'invited')->count(),
                'accepted' => $invites->where('status', 'accepted')->count(),
                'awarded' => $invites->where('status', 'awarded')->count(),
                'closed' => $invites->whereIn('status', ['declined', 'no_reply'])->count(),
            ],
        ]);
    }

    /** POST /api/invitations/{match}/respond */
    public function respondInvite(Request $request, ProjectMatch $match): JsonResponse
    {
        $fundi = $this->fundi();
        abort_unless((int) $match->fundi_id === (int) $fundi->id, 403, 'That invite was not put to you.');

        $data = $request->validate(
            ['answer' => 'required|in:accept,decline', 'note' => 'nullable|string|max:200'],
            [],
            ['answer' => 'Your answer'],
        );

        $match = Marketplace::respond($match, $data['answer'], $data['note'] ?? null);

        return response()->json([
            'message' => $data['answer'] === 'accept'
                ? 'Taken. Watch My projects for the award — the office assigns it.'
                : 'Declined, and the office can see it was your answer.',
            'invite' => $this->invite($match->fresh()),
        ]);
    }

    // ---- My training (CTP) ------------------------------------------------

    /** GET /api/training — open intakes, the CTP's own seats and their history. */
    public function training(): JsonResponse
    {
        $ctp = $this->ctp();
        $t = MemberPortal::training($ctp);
        $applied = $t['appliedTo']->all();

        return response()->json([
            'status' => $ctp->status,
            'can_apply' => in_array($ctp->status, CtpRegistration::WORKING, true),
            'nearby' => $t['nearby']->map(fn (Cohort $c) => $this->cohort($c, $applied))->values(),
            'elsewhere' => $t['elsewhere']->map(fn (Cohort $c) => $this->cohort($c, $applied))->values(),
            'mine' => $t['mine']->map(fn (Enrollment $e) => $this->enrollment($e))->values(),
            'history' => $t['history']->map(fn (Enrollment $e) => $this->enrollment($e))->values(),
        ]);
    }

    /** POST /api/training/apply */
    public function applyCohort(Request $request): JsonResponse
    {
        $ctp = $this->ctp();
        $data = $request->validate([
            'cohort_id' => 'required|integer|exists:cohorts,id',
            'note' => 'nullable|string|max:1000',
        ], [], ['cohort_id' => 'Training']);

        $cohort = Cohort::findOrFail($data['cohort_id']);

        abort_unless(in_array($ctp->status, CtpRegistration::WORKING, true), 422,
            'Training seats go to a CTP once they are active — your status is '.$ctp->statusLabel().'.');
        abort_unless($cohort->isOpenForEnrol(), 422,
            $cohort->reference.' is not open for applications: '.$cohort->enrolClosedReason().'.');

        $existing = $cohort->enrollments()->where('ctp_id', $ctp->id)->first();
        abort_if($existing && in_array($existing->state, ['pending', 'approved'], true), 422,
            'You have already put yourself forward for '.$cohort->reference.'.');

        $message = $existing
            ? 'Your application for '.$cohort->reference.' is back with the programme office.'
            : 'Applied for '.$cohort->reference.' — the office confirms the seat and your placement.';

        if ($existing) {
            $existing->update(['state' => 'pending', 'source' => 'self', 'note' => $data['note'] ?? $existing->note,
                'reviewed_by' => null, 'approved_on' => null]);
        } else {
            Enrollment::create([
                'cohort_id' => $cohort->id, 'ctp_id' => $ctp->id, 'state' => 'pending', 'source' => 'self',
                'note' => $data['note'] ?? null,
            ]);
        }

        return response()->json(['message' => $message], 201);
    }

    /** POST /api/training/{enrollment}/withdraw */
    public function withdrawSeat(Enrollment $enrollment): JsonResponse
    {
        $ctp = $this->ctp();
        abort_unless($enrollment->ctp_id === $ctp->id, 404);
        abort_unless(in_array($enrollment->state, ['pending', 'approved'], true), 422,
            'That seat is already '.$enrollment->stateLabel().'.');
        abort_unless(! in_array($enrollment->stage, ['completed', 'failed'], true), 422,
            'You have already completed '.$enrollment->cohort->reference.' — the record stays on your file.');

        $enrollment->update(['state' => 'withdrawn']);

        return response()->json(['message' => 'Withdrawn from '.$enrollment->cohort->reference.'.']);
    }

    // ---- serialisers ------------------------------------------------------

    private function invite(ProjectMatch $m): array
    {
        return [
            'id' => $m->id,
            'status' => $m->status,
            'status_label' => $m->statusLabel(),
            'project_title' => $m->project?->title,
            'project_reference' => $m->project?->reference,
            'hirer' => $m->project?->hirer?->name,
            'score' => (int) round($m->score),
            'matched' => $m->matchedLines(),
            'round' => $m->round,
            'time_label' => $m->timeLabel(),
            'can_respond' => $m->status === 'invited' && ! $m->isOverdue(),
        ];
    }

    private function cohort(Cohort $c, array $applied): array
    {
        return [
            'id' => $c->id,
            'reference' => $c->reference,
            'course' => $c->courseLabel(),
            'place' => $c->placeLabel(),
            'county' => $c->county,
            'period' => $c->periodLabel(),
            'dates' => $c->dateLabel(),
            'seats_left' => $c->seatsLeft(),
            'applied' => in_array($c->id, $applied, false),
            'is_open' => $c->isOpenForEnrol(),
        ];
    }

    private function enrollment(Enrollment $e): array
    {
        return [
            'id' => $e->id,
            'cohort' => $e->cohort?->reference,
            'course' => $e->cohort?->courseLabel(),
            'period' => $e->cohort?->periodLabel(),
            'state' => $e->state,
            'state_label' => $e->stateLabel(),
            'stage' => $e->stage,
            'stage_label' => $e->stageLabel(),
            'track_pct' => $e->trackPct(),
            'attendance_pct' => $e->attendancePct(),
            'can_withdraw' => in_array($e->state, ['pending', 'approved'], true)
                && ! in_array($e->stage, ['completed', 'failed'], true),
        ];
    }
}
