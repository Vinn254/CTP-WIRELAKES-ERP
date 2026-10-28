<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\User;
use App\Support\Audit;
use App\Support\Csv;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Module 12  — the trail. Read-only: an entry says what happened, and a mistake in the record behind it is
 * answered by changing that record (which writes its own next line), never by editing history.
 *
 * The account side sits in the same table: every sign-in, refused sign-in, emailed code and reset link is a line
 * here too, so the executive can see all activity in the system — what was decided, and who came in to decide it.
 */
class ActivityController extends Controller
{
    /** The actions that belong to an account rather than to a record. */
    private const ACCOUNT_ACTIONS = [
        'account_created', 'signed_in', 'signed_out', 'failed_sign_in',
        'otp_sent', 'otp_verified', 'reset_sent', 'reset_used', 'reset_refused',
    ];

    public function index(Request $request)
    {
        [$filters, $entries] = $this->query($request, 50);

        return view('admin.me-gis.activity', $filters + [
            'entries' => $entries,
            'actions' => ActivityLog::ACTIONS,
            'records' => array_combine(
                Audit::WATCHED,
                array_map(fn (string $c) => ActivityLog::recordName($c), Audit::WATCHED)
            ),
            'people' => User::whereIn('role', ['admin', 'superadmin'])->orderBy('name')->get(['id', 'name', 'role']),
            'total' => ActivityLog::count(),
            'accountLines' => ActivityLog::whereIn('action', self::ACCOUNT_ACTIONS)->count(),
        ]);
    }

    /** The same filter, handed to the executive as a file for the board pack. */
    public function csv(Request $request): StreamedResponse
    {
        $handle = fopen('php://output', 'w');

        return response()->streamDownload(function () use ($request, $handle) {
            fputcsv($handle, Csv::row(['When', 'Who', 'Action', 'Record', 'What changed', 'IP']));

            $query = ActivityLog::with('actor:id,name');
            $this->apply($query, $request);

            $query->chunk(500, function ($rows) use ($handle) {
                foreach ($rows as $a) {
                    fputcsv($handle, Csv::row([
                        $a->whenLabel(), $a->whoLabel(), $a->actionLabel(),
                        $a->subjectName(), (string) $a->detail, (string) $a->ip,
                    ]));
                }
            });

            fclose($handle);
        }, 'activity-trail-'.now()->format('Y-m-d-His').'.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    private function query(Request $request, ?int $paginate = null): array
    {
        $build = ActivityLog::with('actor:id,name');
        $this->apply($build, $request);

        $entries = $paginate ? $build->paginate($paginate)->withQueryString() : $build->get();

        return [[
            'action' => $request->query('action', 'all'),
            'user' => (int) $request->query('user'),
            'subject' => $request->query('record', 'all'),
            'scope' => $request->query('scope', 'all'),
            'search' => trim((string) $request->query('q')),
        ], $entries];
    }

    private function apply($query, Request $request): void
    {
        $action = $request->query('action', 'all');
        $user = (int) $request->query('user');
        $subject = $request->query('record', 'all');
        $scope = $request->query('scope', 'all');
        $search = trim((string) $request->query('q'));

        $query
            ->when($action !== 'all', fn ($q) => $q->where('action', $action))
            ->when($user, fn ($q) => $q->where('user_id', $user))
            ->when($subject !== 'all', fn ($q) => $q->where('subject_type', $subject))
            ->when($scope === 'account', fn ($q) => $q->whereIn('action', self::ACCOUNT_ACTIONS))
            ->when($scope === 'programme', fn ($q) => $q->whereNotIn('action', self::ACCOUNT_ACTIONS))
            ->when($search !== '', function ($q) use ($search) {
                $q->where(fn ($w) => $w->where('subject_label', 'like', "%{$search}%")
                    ->orWhere('detail', 'like', "%{$search}%")
                    ->orWhere('actor_name', 'like', "%{$search}%")
                    ->orWhere('ip', 'like', "%{$search}%"));
            })
            ->orderByDesc('id');
    }
}
