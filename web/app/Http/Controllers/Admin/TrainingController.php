<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Training;
use Illuminate\Http\Request;

/**
 * Module 4 — the trainings the programme itself runs. This list is the only thing a cohort can be an intake
 * of and the only thing a fundi can be registered to teach, so anything new starts here.
 */
class TrainingController extends Controller
{
    public function index(Request $request)
    {
        return view('admin.trainings.index', [
            'trainings' => Training::withCount(['cohorts', 'trainers'])->orderBy('status')->orderBy('name')->get(),
            'statuses' => Training::STATUSES,
            'search' => trim((string) $request->input('q')),
        ]);
    }

    public function store(Request $request)
    {
        $data = Training::dataFrom($request);
        $data['code'] = trim((string) ($data['code'] ?? '')) ?: Training::codeFromName($data['name']);

        $training = Training::create($data);

        return back()->with('status', $training->name.' is on the programme list — a cohort can be posted for it and a fundi can be registered to teach it.');
    }

    public function update(Request $request, Training $training)
    {
        $data = Training::dataFrom($request, $training);
        $data['code'] = $data['code'] ?: $training->code;

        $training->update($data);

        return back()->with('status', $training->name.' updated.');
    }

    /** An inactive training cannot be picked for a new cohort; cohorts already running keep their record. */
    public function toggle(Training $training)
    {
        $training->update(['status' => $training->status === 'active' ? 'inactive' : 'active']);

        return back()->with('status', $training->name.' is now '.($training->status === 'active' ? 'on offer.' : 'off the list for new cohorts.'));
    }

    public function destroy(Training $training)
    {
        abort_if($training->cohorts()->exists() || $training->trainers()->exists(), 422,
            $training->name.' already has a cohort or a trainer against it. Switch it off instead — the records must keep their training.');

        $training->delete();

        return back()->with('status', 'Training removed from the list.');
    }

    /** The trainings a cohort or trainer form may offer: active ones, plus any already chosen. */
    public static function options(?int $chosen = null)
    {
        return Training::where('status', 'active')
            ->when($chosen, fn ($q) => $q->orWhere('id', $chosen))
            ->orderBy('name')->get(['id', 'name', 'code', 'summary', 'status']);
    }
}
