<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Cohort;
use App\Models\Timetable;
use App\Models\TimetableSlot;
use App\Models\Training;
use App\Models\TrainingModule;
use App\Support\TeachingDesk;
use Illuminate\Http\Request;

/**
 * The Academic Registrar's week for a programme.
 *
 * The office decides how a course runs through the week — which unit is taught on which day, at what time, in which
 * room — and every trainer teaching that programme reads the same week. It does not replace the trainer's own paper:
 * a trainer still lays out the week of their cohort and forwards it here to be answered. What the office week carries
 * is the shape the programme is expected to keep to.
 */
class ProgrammeTimetableController extends Controller
{
    public function index()
    {
        $this->guard();

        $rows = Training::where('status', 'active')->orderBy('name')->get()->map(function (Training $training) {
            $week = Timetable::forProgramme($training->id);

            return [
                'training' => $training,
                'week' => $week,
                'classes' => $week?->slots->count() ?? 0,
                'hours' => $week?->slots->sum(fn (TimetableSlot $slot) => $slot->hours()) ?? 0,
                'cohorts' => Cohort::where('training_id', $training->id)->where('status', '!=', 'draft')->count(),
                'units' => TrainingModule::where('training_id', $training->id)->count(),
            ];
        });

        return view('admin.programme-timetables.index', [
            'rows' => $rows,
            'url' => fn (string $name, array $params = []) => route($name, $params),
        ]);
    }

    public function edit(Training $training)
    {
        $this->guard();

        return view('admin.programme-timetables.edit', $this->formData($training));
    }

    public function update(Request $request, Training $training)
    {
        $this->guard();

        $rows = $this->guardUnits($training, TimetableSlot::rowsFrom($request));

        $week = Timetable::firstOrNew(['training_id' => $training->id, 'cohort_id' => null]);
        $week->fill([
            'status' => 'approved',
            'note' => $request->input('note') ?: $week->note,
            'taken_by_user' => auth()->id(),
            'reviewed_by' => auth()->id(),
            'reviewed_at' => now(),
        ])->save();

        // The week is replaced whole, so a class the Registrar takes off it does not stay behind the rest.
        $week->slots()->delete();

        foreach ($rows as $row) {
            $week->slots()->create($row);
        }

        return redirect()->route('admin.programme_timetables.edit', $training)
            ->with('status', $training->name.'’s week is saved — '
                .count($rows).' '.(\count($rows) === 1 ? 'class' : 'classes')
                .'. Every trainer on this programme reads it on their timetable.');
    }

    /** The programme, its own units and the week on file. */
    private function formData(Training $training): array
    {
        $week = Timetable::where('training_id', $training->id)->whereNull('cohort_id')->first();

        $grid = old('slots', ($week?->load('slots')?->slots ?? collect())
            ->map(fn (TimetableSlot $slot) => [
                'day' => $slot->day,
                'starts_at' => substr((string) $slot->starts_at, 0, 5),
                'ends_at' => substr((string) $slot->ends_at, 0, 5),
                'training_module_id' => $slot->training_module_id,
                'kind' => $slot->kind,
                'room' => $slot->room,
            ])->values()->all());

        $grid = collect($grid)
            ->map(fn (array $row) => array_intersect_key($row, array_flip(
                ['day', 'starts_at', 'ends_at', 'training_module_id', 'kind', 'room'])))
            ->values()->all();

        if ($grid === []) {
            $grid = [['day' => 'monday', 'starts_at' => '', 'ends_at' => '', 'training_module_id' => null,
                'kind' => 'theory', 'room' => '']];
        }

        return [
            'training' => $training,
            'week' => $week,
            'grid' => $grid,
            'units' => TrainingModule::where('training_id', $training->id)->ordered()->get(),
            'days' => Timetable::DAYS,
            'kinds' => TimetableSlot::KINDS,
            'cohorts' => Cohort::where('training_id', $training->id)->orderBy('reference')->get(['id', 'reference', 'status']),
        ];
    }

    /** A class on the programme's week is a class of this programme — a unit of another course is refused. */
    private function guardUnits(Training $training, array $rows): array
    {
        $own = TrainingModule::where('training_id', $training->id)->pluck('id')->all();

        foreach ($rows as $row) {
            if (! empty($row['training_module_id']) && ! in_array((int) $row['training_module_id'], $own, true)) {
                $title = TrainingModule::whereKey($row['training_module_id'])->value('title') ?: 'That unit';

                abort(422, $title.' is not a unit of '.$training->name.'. A programme week is built from the units '
                    .'listed under that programme on the Training screen.');
            }
        }

        return $rows;
    }

    private function guard(): void
    {
        abort_unless(TeachingDesk::approves(auth()->user()), 403,
            'The Academic Registrar lays out the week a programme runs on.');
    }
}
