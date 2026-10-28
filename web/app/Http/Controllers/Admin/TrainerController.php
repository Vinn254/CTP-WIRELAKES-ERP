<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\County;
use App\Models\FundiRegistration;
use App\Models\JobApplication;
use App\Models\Trainer;
use App\Models\Training;
use App\Models\TrainingVenue;
use App\Models\User;
use Illuminate\Http\Request;

/**
 * The training register (§7.8). A trainer is whoever the programme licenses to deliver a training, and that is
 * three kinds of person: an E-Fundi from the fundi pool, a staff account, or an applicant taken on at Careers.
 * All three sit on the same list and work the same desk — only the record holding the person differs. An E-Fundi
 * must be senior to carry a cohort; the office licenses the other two by hand.
 */
class TrainerController extends Controller
{
    public function index(Request $request)
    {
        $status = $request->input('status', 'all');

        return view('admin.trainers.index', [
            'trainers' => Trainer::with(['fundi:id,full_name,ef_code,tier,county,phone', 'staff:id,name,email',
                'applicant:id,name,email,phone', 'trainings:id,name', 'venue:id,name,county,sub_county,ward',
                'cohorts.venue:id,name'])
                ->when($status !== 'all', fn ($q) => $q->where('status', $status))
                ->orderByRaw("case status when 'applied' then 0 when 'approved' then 1 else 2 end")
                ->orderByDesc('id')->get(),
            'status' => $status,
            'counts' => Trainer::selectRaw('status, count(*) n')->groupBy('status')->pluck('n', 'status'),
            'statuses' => Trainer::STATUSES,
            'holders' => Trainer::HOLDERS,
            'trainings' => Training::orderByRaw("case status when 'active' then 0 else 1 end")->orderBy('name')
                ->get(['id', 'name', 'code', 'status']),
            'venues' => TrainingVenue::where('status', 'active')->orderBy('name')->get(['id', 'name']),
            'counties' => County::orderBy('name')->pluck('name'),
            // Every working fundi of a teaching tier, with the sites they already hold marked on them.
            'fundis' => FundiRegistration::where('status', 'active')
                ->whereIn('tier', Trainer::TEACHING_TIERS)
                ->with(['trainers:id,fundi_id,venue_id,status', 'trainers.venue:id,name'])
                ->orderBy('full_name')->get(['id', 'full_name', 'ef_code', 'tier', 'county']),
            // A staff account already on the payroll, and an applicant the careers desk is still talking to.
            'staff' => User::query()->where('active', true)->whereIn('role', ['admin', 'superadmin'])
                ->orderBy('name')->get(['id', 'name', 'email', 'role']),
            'applicants' => JobApplication::whereIn('status', ['reviewing', 'shortlisted', 'contacted'])
                ->orderBy('name')->get(['id', 'name', 'email', 'phone', 'job_opening_id']),
        ]);
    }

    public function store(Request $request)
    {
        $data = Trainer::dataFrom($request);
        $column = Trainer::HOLDER_COLUMN[$data['holder_type']];
        $holderId = (int) $data[$column];

        if ($data['holder_type'] === 'fundi') {
            $this->guardTier($data['fundi_id']);
        }

        [$posting, $again] = $this->open((int) $data['venue_id'], $data['holder_type'], $holderId,
            $this->attributes($data), $data['trainings']);

        return back()->with('status', $this->nameOf($data['holder_type'], $holderId).' — '.($again
            ? 'application for '.$posting->venue->name.' is back in as '.$posting->statusLabel().'.'
            : 'applied to train at '.$posting->venue->name.' and sits as '.$posting->statusLabel().' until the office decides.'));
    }

    public function update(Request $request, Trainer $trainer)
    {
        $data = Trainer::dataFrom($request);

        // Moving a trainer to a different site is a different approval, so it goes through a fresh application.
        abort_if((int) $data['venue_id'] !== (int) $trainer->venue_id, 422,
            'That changes which site this approval covers. This record is for '.$trainer->venue->name.' — '
                .'take a separate application for '.(TrainingVenue::whereKey($data['venue_id'])->value('name') ?? 'the new site').'.');

        $trainer->update($this->attributes($data));
        $trainer->trainings()->sync($data['trainings']);

        return back()->with('status', 'Teaching record for '.$trainer->personName().' at '.$trainer->venue?->name.' updated.');
    }

    /** Approve, decline or suspend one SITE — a cohort only goes to a trainer approved for that venue. */
    public function decide(Request $request, Trainer $trainer)
    {
        $data = $request->validate([
            'decision' => 'required|in:approved,declined,suspended',
            'note' => 'nullable|string|max:500|required_if:decision,declined',
        ], [], ['note' => 'Reason', 'decision' => 'Decision']);

        $trainer->update([
            'status' => $data['decision'],
            'note' => $data['note'] ?? $trainer->note,
            'reviewed_by' => auth()->id(),
        ]);

        return back()->with('status', $trainer->personName().' — '.$trainer->statusLabel().' at '.$trainer->venue?->name.'.');
    }

    /** The pivot list is taken out of the column data before the record is saved. */
    private function attributes(array $data): array
    {
        unset($data['trainings']);

        return $data;
    }

    /** The person in their own words, from whichever record holds them. */
    private function nameOf(string $type, int $id): string
    {
        return match ($type) {
            'staff' => User::whereKey($id)->value('name') ?? 'A staff account',
            'applicant' => JobApplication::whereKey($id)->value('name') ?? 'An applicant',
            default => FundiRegistration::whereKey($id)->value('full_name') ?? 'An E-Fundi',
        };
    }

    private function guardTier(int $fundiId): void
    {
        $fundi = FundiRegistration::findOrFail($fundiId);

        abort_unless(in_array($fundi->tier, Trainer::TEACHING_TIERS, true), 422,
            $fundi->full_name.' is a '.FundiRegistration::tierLabel($fundi->tier).'. Only a senior fundi can train CTPs.');
    }

    /**
     * One application per person per site. A site they were declined at is reopened on a fresh application rather
     * than given a second row, so the office history stays in one place.
     *
     * @return array{0: Trainer, 1: bool} the row and whether it was a reopening
     */
    private function open(int $venueId, string $holderType, int $holderId, array $columns, array $trainings): array
    {
        $held = Trainer::forHolder($holderType, $holderId, $venueId);

        if ($held) {
            abort_unless(in_array($held->status, ['declined', 'suspended'], true), 422,
                $held->personName().' is already '.$held->statusLabel().' at '.$held->venue->name
                    .'. Nothing is added twice for one site.');

            $held->update($columns + ['status' => 'applied', 'note' => null, 'reviewed_by' => null]);
            $held->trainings()->sync($trainings);
            $held->load('venue:id,name');

            return [$held, true];
        }

        $trainer = Trainer::create($columns + ['status' => 'applied']);
        $trainer->trainings()->sync($trainings);
        $trainer->load('venue:id,name');

        return [$trainer, false];
    }
}
