<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Teaching\TeachingPapers;
use App\Models\Cohort;
use App\Support\TeachingDesk;
use Illuminate\Support\Collection;

/**
 * The same five papers an E-Fundi trainer fills inside their own member portal — the register, the marks, the
 * timetable, the lesson plan and the material, on the cohorts the programme handed to their sites.
 */
class PortalTrainerPaperController extends TeachingPapers
{
    protected string $layout = 'layouts.member';

    protected string $prefix = 'portal.trainer.';

    protected function cohorts(): Collection
    {
        return TeachingDesk::postings(auth()->user())
            ->flatMap(fn ($t) => $t->cohorts)
            ->where('status', '!=', 'draft')
            ->unique('id')
            ->sortByDesc('starts_on')
            ->values();
    }

    /** Only a cohort handed to one of this fundi's own sites, never the office's wider reach. */
    protected function gate(Cohort $cohort): void
    {
        abort_unless($this->cohorts()->contains('id', $cohort->id), 404);
    }
}
