<?php

namespace App\Observers;

use App\Models\CtpRegistration;
use App\Support\OfficeAlert;
use Illuminate\Database\Eloquent\Model;

/**
 * A new CTP or E-Fundi application is announced to the office. An observer, so a record opened from the public
 * form, the office screen or the phone app all raise the same notice.
 *
 * No constructor: Eloquent registers an observer as "Class@created" and builds it out of the container when the
 * event fires, so a required argument would fail every registration rather than none of them.
 */
class NewMemberObserver
{
    public function created(Model $subject): void
    {
        // Console runs fill the registers in bulk; a notice per row would be noise, not information.
        if (app()->runningInConsole()) {
            return;
        }

        OfficeAlert::newMember($subject, $subject instanceof CtpRegistration ? 'CTP' : 'E-Fundi');
    }
}
