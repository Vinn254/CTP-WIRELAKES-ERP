<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\County;
use App\Models\FundiRegistration;
use App\Models\Trainer;
use App\Models\Training;
use App\Models\TrainingVenue;
use Illuminate\Http\Request;

/**
 * Module 4 §7.8 — trainers come from the fundi pool. A senior fundi applies (or is put forward by the
 * office), is approved for the trainings they can teach, and is paid the rate recorded here.
 */
class TrainerController extends Controller
{
    public function index(Request $request)
    {
        $status = $request->input('status', 'all');

        return view('admin.trainers.index', [
            'trainers' => Trainer::with(['fundi:id,full_name,ef_code,tier,county,phone', 'trainings:id,name', 'venue:id,name,county,sub_county,ward', 'cohorts.venue:id,name'])
                ->when($status !== 'all', fn ($q) => $q->where('status', $status))
                ->orderByRaw("case status when 'applied' then 0 when 'approved' then 1 else 2 end")
                ->orderByDesc('id')->get(),
            'status' => $status,
            'counts' => Trainer::selectRaw('status, count(*) n')->groupBy('status')->pluck('n', 'status'),
            'statuses' => Trainer::STATUSES,
            'trainings' => Training::orderByRaw("case status when 'active' then 0 else 1 end")->orderBy('name')
                ->get(['id', 'name', 'code', 'status']),
            'venues' => TrainingVenue::where('status', 'active')->orderBy('name')->get(['id', 'name']),
            'counties' => County::orderBy('name')->pluck('name'),
            // A fundi is only offered up once, and only if they are working in the pool.
            'fundis' => FundiRegistration::where('status', 'active')
                ->whereIn('tier', Trainer::TEACHING_TIERS)
                ->whereDoesntHave('trainer')
                ->orderBy('full_name')->get(['id', 'full_name', 'ef_code', 'tier', 'county']),
        ]);
    }

    public function store(Request $request)
    {
        $data = Trainer::dataFrom($request);
        $fundi = FundiRegistration::findOrFail($data['fundi_id']);

        $this->guard($fundi, $data['fundi_id']);

        $trainer = Trainer::create($this->attributes($data) + ['status' => 'applied']);
        $trainer->trainings()->sync($data['trainings']);

        return back()->with('status', $fundi->full_name.' is on the trainer list as '.$trainer->statusLabel().'.');
    }

    public function update(Request $request, Trainer $trainer)
    {
        $data = Trainer::dataFrom($request);
        $trainer->update($this->attributes($data));
        $trainer->trainings()->sync($data['trainings']);

        return back()->with('status', 'Trainer record for '.$trainer->fundi->full_name.' updated.');
    }

    /** Approve, decline or suspend — an approved trainer is the only one a cohort can be handed to. */
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

        return back()->with('status', $trainer->fundi->full_name.' — '.$trainer->statusLabel().'.');
    }

    /** The pivot list is taken out of the column data before the record is saved. */
    private function attributes(array $data): array
    {
        unset($data['trainings']);

        return $data;
    }

    private function guard(FundiRegistration $fundi, int $fundiId): void
    {
        abort_if(Trainer::where('fundi_id', $fundiId)->exists(), 422,
            $fundi->full_name.' is already on the trainer list.');

        abort_unless(in_array($fundi->tier, Trainer::TEACHING_TIERS, true), 422,
            $fundi->full_name.' is a '.FundiRegistration::tierLabel($fundi->tier).'. Only a junior or senior fundi can train CTPs.');
    }
}
