<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Support\DirectorDesk;

/**
 * The director's desk — the one office page that is not a module. It reads the whole system at once: every
 * module's queue, the figures behind a decision, what the staff have done, and the approvals that need a
 * signature from this account. Read-only by design; each card hands the work to the desk that carries it out.
 */
class DirectorController extends Controller
{
    public function index()
    {
        abort_unless(auth()->user()->isSuperAdmin(), 403,
            'The director\'s desk is open to the programme director only.');

        return view('admin.director.index', [
            'modules' => DirectorDesk::modules(),
            'approvals' => DirectorDesk::approvals(),
            'waiting' => DirectorDesk::waiting(),
            'pages' => DirectorDesk::pages(),
            'numbers' => DirectorDesk::numbers(),
            'money' => DirectorDesk::money(),
            'performed' => DirectorDesk::performed(),
            'movement' => DirectorDesk::movement(),
        ]);
    }
}
