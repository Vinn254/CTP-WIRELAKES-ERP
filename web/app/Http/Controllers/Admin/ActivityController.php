<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\User;
use App\Support\Audit;
use Illuminate\Http\Request;

/**
 * Module 12 §15.6 — the trail. Read-only: an entry says what happened, and a mistake in the record behind it is
 * answered by changing that record (which writes its own next line), never by editing history.
 */
class ActivityController extends Controller
{
    public function index(Request $request)
    {
        $action = $request->query('action', 'all');
        $user = (int) $request->query('user');
        $subject = $request->query('record', 'all');
        $search = trim((string) $request->query('q'));

        $entries = ActivityLog::with('actor:id,name')
            ->when($action !== 'all', fn ($q) => $q->where('action', $action))
            ->when($user, fn ($q) => $q->where('user_id', $user))
            ->when($subject !== 'all', fn ($q) => $q->where('subject_type', $subject))
            ->when($search !== '', function ($q) use ($search) {
                $q->where(fn ($w) => $w->where('subject_label', 'like', "%{$search}%")
                    ->orWhere('detail', 'like', "%{$search}%")
                    ->orWhere('actor_name', 'like', "%{$search}%"));
            })
            ->orderByDesc('id')
            ->paginate(50)->withQueryString();

        return view('admin.me-gis.activity', [
            'entries' => $entries,
            'action' => $action,
            'user' => $user,
            'subject' => $subject,
            'search' => $search,
            'actions' => ActivityLog::ACTIONS,
            'records' => array_combine(
                Audit::WATCHED,
                array_map(fn (string $c) => ActivityLog::recordName($c), Audit::WATCHED)
            ),
            'people' => User::whereIn('role', ['admin', 'superadmin'])->orderBy('name')->get(['id', 'name', 'role']),
            'total' => ActivityLog::count(),
        ]);
    }
}
