<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Training;
use App\Models\TrainingModule;
use Illuminate\Http\Request;

/**
 * Module 4 — the trainings the programme itself runs. This list is the only thing a cohort can be an intake
 * of and the only thing a fundi can be registered to teach, so anything new starts here. The modules that
 * have to be got through under each training are set on the same screen.
 */
class TrainingController extends Controller
{
    public function index(Request $request)
    {
        return view('admin.trainings.index', [
            'trainings' => Training::withCount(['cohorts', 'trainers', 'modules'])
                ->with(['modules', 'cohorts' => fn ($q) => $q->select('id', 'training_id', 'reference', 'academic_year', 'term', 'status', 'starts_on', 'ends_on')
                    ->orderByDesc('academic_year')->orderByDesc('starts_on')])
                ->orderBy('status')->orderBy('name')->get(),
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

        return back()->with('status', $training->name.' is now '.($training->status === 'active' ? 'listed — a cohort can be posted for it.' : 'off the list for new cohorts.'));
    }

    public function destroy(Training $training)
    {
        abort_if($training->cohorts()->exists() || $training->trainers()->exists(), 422,
            $training->name.' already has a cohort or a trainer against it. Switch it off instead — the records must keep their training.');

        $training->delete();

        return back()->with('status', 'Training removed from the list.');
    }

    /** As many modules as the office typed in one go, put under the training in that order. */
    public function storeModule(Request $request, Training $training)
    {
        $rows = TrainingModule::rowsFrom($request);

        if ($rows->isEmpty()) {
            return redirect()->route('admin.trainings.index')
                ->withErrors(['modules.0.title' => 'Type the name of the module to add, or close the rows you did not mean to fill.'])
                ->withInput();
        }

        $position = TrainingModule::nextPosition($training->id);
        $titles = [];

        foreach ($rows as $row) {
            $module = TrainingModule::create([
                ...$row,
                'training_id' => $training->id,
                'position' => $position++,
            ]);
            $titles[] = $module->title;
        }

        $listed = collect($titles);
        $list = $listed->take(3)->join(', ').($listed->count() > 3 ? ' +'.($listed->count() - 3).' more' : '');

        return back()->with('status', $list.' now expected under '.$training->name
            .'. The trainer sees them as what they deliver and the trainee sees them on their record.');
    }

    public function updateModule(Request $request, Training $training, TrainingModule $module)
    {
        abort_unless($module->training_id === $training->id, 404);

        $module->update(TrainingModule::dataFrom($request));

        return back()->with('status', $module->title.' updated — every roll for '.$training->name.' shows the change.');
    }

    /** A module nobody has been marked against can come off; one that is on a trainee's record stays. */
    public function destroyModule(Training $training, TrainingModule $module)
    {
        abort_unless($module->training_id === $training->id, 404);

        abort_if($module->completions()->exists(), 422,
            $module->title.' has been marked on '.$module->completions()->count()
            .' trainee record(s). Take those marks off first — a mark that is on someone’s file cannot lose the module it was made against.');

        $module->delete();

        return back()->with('status', $module->title.' removed from '.$training->name.'.');
    }

    /** The trainings a cohort or trainer form may offer: active ones, plus any already chosen. */
    public static function options(?int $chosen = null)
    {
        return Training::where('status', 'active')
            ->when($chosen, fn ($q) => $q->orWhere('id', $chosen))
            ->orderBy('name')->get(['id', 'name', 'code', 'summary', 'status']);
    }
}
