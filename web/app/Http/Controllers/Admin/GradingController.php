<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\TrainingModule;
use App\Models\UnitGrading;
use App\Support\Grading;
use App\Support\TeachingDesk;
use Illuminate\Http\Request;

/**
 * The Academic Registrar's grading: what each mark is worth on a unit, and the mark a student has to reach.
 *
 * There is no page of its own — the weights are set on the cohort's <b>Results</b>, beside the marks they weigh, and
 * on a student's own record. A lecturer or trainer fills in the marks; this decides how they are added up, so the
 * grade on a transcript is the Registrar's own arithmetic. The weights sit on the unit, so every cohort running the
 * same course is graded the same way.
 */
class GradingController extends Controller
{
    /** Keep the weights for one unit. A correction overwrites the row — the unit never carries two gradings. */
    public function update(Request $request, TrainingModule $module)
    {
        $this->guard();

        $line = Grading::save($module, UnitGrading::dataFrom($request), $request->user());

        return back()->with('status', $line);
    }

    private function guard(): void
    {
        abort_unless(TeachingDesk::approves(auth()->user()), 403,
            'The Academic Registrar sets how a unit is graded, on the cohort’s results.');
    }
}
