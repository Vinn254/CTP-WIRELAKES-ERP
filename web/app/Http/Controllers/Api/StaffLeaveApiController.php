<?php

namespace App\Http\Controllers\Api;

use App\Models\User;
use App\Support\Leave;

/**
 * My leave for an office account — the same ruled paper /admin/my-leave serves a trainer who signs in with their
 * staff login. Nothing is re-written here: the balances, the seven types, the writer and every answer sentence come
 * from MemberAccountApiController, which is what the E-Fundi's own phone screens already run. Only the gate differs:
 * on the website a leave book opens for anyone who works in the programme, Leave::worker(), not for a trainee.
 */
class StaffLeaveApiController extends MemberAccountApiController
{
    protected function worker(): User
    {
        $user = auth()->user();
        abort_unless($user && Leave::worker($user), 403,
            'Leave is booked by the people who work in the programme.');

        return $user;
    }
}
