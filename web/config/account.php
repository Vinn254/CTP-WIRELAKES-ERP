<?php

/*
| One place for the account-security windows, so a founder can change them without reading code.
| Everything here is in minutes or plain counts — no re-deploy needed to adjust a number.
*/

return [

    // The emailed six-figure code. It proves the address belongs to the person holding the password.
    'otp' => [
        'digits' => 6,
        'minutes' => 10,
        // A wrong code this many times burns the code; they must ask for a new one.
        'max_attempts' => 5,
    ],

    // The forgot-password link. It dies on its own, and one address may only be sent this many per day.
    'reset' => [
        'minutes' => 56,
        'per_day' => 3,
    ],

    // Nobody left signed in while the desk is unattended: five minutes without a click ends the session.
    'idle_minutes' => 5,

    // The tables whose stored identities are brought to one form, so NAME@Example and name@example are read as the
    // same person. Column => how to normalise it (email, phone or id_number).
    'identity_tables' => [
        'users' => ['email' => 'email'],
        'ctp_registrations' => ['email' => 'email', 'phone' => 'phone', 'national_id' => 'id_number'],
        'fundi_registrations' => ['email' => 'email', 'phone' => 'phone', 'national_id' => 'id_number'],
        'hirer_registrations' => ['email' => 'email', 'phone' => 'phone'],
        'staff_invites' => ['email' => 'email'],
        'account_invites' => ['email' => 'email'],
    ],

];
