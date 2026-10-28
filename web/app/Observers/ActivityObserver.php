<?php

namespace App\Observers;

use App\Support\Audit;
use Illuminate\Database\Eloquent\Model;

/**
 * Module 12  — every watched record writes its own trail line when it is opened, changed or closed off.
 * Observers rather than a call in each controller, so a change made anywhere in the app is recorded, not only
 * the changes someone remembered to report.
 */
class ActivityObserver
{
    public function created(Model $subject): void
    {
        Audit::record($subject, 'created');
    }

    public function updated(Model $subject): void
    {
        Audit::record($subject, 'updated');
    }

    public function deleted(Model $subject): void
    {
        Audit::record($subject, 'deleted');
    }
}
