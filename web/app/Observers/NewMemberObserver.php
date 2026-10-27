<?php

namespace App\Observers;

use App\Support\OfficeAlert;
use Illuminate\Database\Eloquent\Model;

/**
 * A new CTP or E-Fundi application is announced to the office. An observer, so a record opened from the public
 * form, the office screen or the phone app all raise the same notice.
 */
class NewMemberObserver
{
    public function __construct(private string $what)
    {
    }

    public function created(Model $subject): void
    {
        // Console runs fill the registers in bulk; a notice per row would be noise, not information.
        if (app()->runningInConsole()) {
            return;
        }

        OfficeAlert::newMember($subject, $this->what);
    }
}
