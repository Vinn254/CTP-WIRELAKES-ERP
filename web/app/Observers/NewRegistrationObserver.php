<?php

namespace App\Observers;

use App\Support\OfficeAlert;
use Illuminate\Database\Eloquent\Model;

/**
 * Something new is on the register that the office has to act on and that is not a member record — a trainer
 * posted at a site, a hirer, a community, a person who answered a careers posting. An observer, so the notice is
 * raised whether the row came from a public form, the office screen, the member portal or the phone app.
 *
 * No constructor: Eloquent registers an observer as "Class@created" and builds it out of the container when the
 * event fires, so a required argument would fail every registration rather than none of them.
 */
class NewRegistrationObserver
{
    public function created(Model $subject): void
    {
        // Console runs fill the registers in bulk; a notice per row would be noise, not information.
        if (app()->runningInConsole()) {
            return;
        }

        OfficeAlert::newRegistration($subject);
    }
}
