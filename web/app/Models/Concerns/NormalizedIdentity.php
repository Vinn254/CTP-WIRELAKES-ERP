<?php

namespace App\Models\Concerns;

use App\Support\Accounts;

/**
 * Identity is stored the way it is compared: an email in lower case, a phone without the spaces a person typed,
 * a National ID in one case. Without this, NAME@Example.com reads as a new person on every form.
 */
trait NormalizedIdentity
{
    public function setEmailAttribute($value): void
    {
        $this->attributes['email'] = Accounts::email($value);
    }

    public function setPhoneAttribute($value): void
    {
        $this->attributes['phone'] = ($value === null || $value === '') ? $value : Accounts::phone($value);
    }

    public function setNationalIdAttribute($value): void
    {
        $this->attributes['national_id'] = ($value === null || $value === '') ? $value : Accounts::idNumber($value);
    }
}
