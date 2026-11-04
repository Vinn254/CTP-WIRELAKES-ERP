<?php

namespace App\Http\Controllers\Admin;

use App\Models\Cohort;
use App\Http\Controllers\Teaching\TeachingPapers;
use App\Support\Departments;
use App\Support\TeachingDesk;
use Illuminate\Support\Collection;

/**
 * The teaching papers on the office side: a trainer's own desk at My teaching, and the Training office's desk,
 * which fills the same papers in for a CTP learning at a partner institution — the paperwork a partner school
 * sends is not always enough to carry a trainee's track.
 */
class TeachingPaperController extends TeachingPapers
{
    protected string $prefix = 'admin.teaching.';

    /** A trainer sees the cohorts handed to them; the Training office sees every run it can record on. */
    protected function cohorts(): Collection
    {
        $mine = TeachingDesk::cohortsFor(auth()->user());

        if (Departments::canSee(auth()->user(), 'training') || auth()->user()->isSuperAdmin()) {
            return Cohort::where('status', '!=', 'draft')
                ->with('training:id,name', 'venue:id,name')
                ->orderByDesc('starts_on')->get();
        }

        // A class the office has only drafted is still the trainer's own class: they timetable and plan ahead of it,
        // and the five sidebar links have to open something. The office never lists a draft run here.
        return $mine->unique('id')->sortByDesc('starts_on')->values();
    }

    protected function gate(Cohort $cohort): void
    {
        TeachingDesk::theirCohort($cohort, auth()->user());
    }
}
