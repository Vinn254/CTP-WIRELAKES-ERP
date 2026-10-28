<?php

namespace App\Support;

use App\Models\FundiRegistration;
use App\Models\LeaveRequest;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * The seven leave types the programme grants, and what one person has left of each.
 *
 * Balances are never stored — they are counted from the leave rows against the entitlement every year, so approving
 * a week of leave changes the figures on the person's own page without a second table to keep in step. Annual leave
 * is the one type that builds up: 1.75 days for every month worked, 21 days when the year is complete.
 */
class Leave
{
    public const TYPES = [
        'adoption' => ['name' => 'Adoption Leave', 'days' => 30.0, 'note' => 'Taking a child into the family'],
        'annual' => ['name' => 'Annual Leave', 'days' => 21.0, 'note' => 'Rest days, built up a month at a time'],
        'bereavement' => ['name' => 'Bereavement Leave', 'days' => 5.0, 'note' => 'Immediate family'],
        'compassionate' => ['name' => 'Compassionate Leave', 'days' => 5.0, 'note' => 'A family emergency that needs you there'],
        'maternity' => ['name' => 'Maternity Leave', 'days' => 90.0, 'note' => 'Before and after birth'],
        'paternity' => ['name' => 'Paternity Leave', 'days' => 14.0, 'note' => 'A birth in the family'],
        'sick' => ['name' => 'Sick Leave', 'days' => 14.0, 'note' => 'Illness, with the clinic card attached when it runs long'],
    ];

    /** Days Annual Leave builds in a month, and the most it can hold in a year. */
    public const ANNUAL_ACCRUAL = 1.75;

    public const STATUSES = [
        'pending' => 'Waiting on Human Resources',
        'approved' => 'Approved',
        'rejected' => 'Declined',
        'cancelled' => 'Withdrawn',
    ];

    public static function name(string $type): string
    {
        return self::TYPES[$type]['name'] ?? Str::headline($type);
    }

    /** A number that reads as a person counts it: 21, 5.5, 1.75 — never 5.5000000001. */
    public static function format(float $days): string
    {
        $rounded = round($days, 2);

        return rtrim(rtrim(number_format($rounded, 2, '.', ''), '0'), '.');
    }

    /** Whole months worked in the leave year — the only thing Annual Leave is counted from. */
    public static function monthsWorked(User $user, int $year): int
    {
        $from = static::joinedOn($user)->max(Carbon::parse($year.'-01-01')->startOfDay());
        $to = $year >= Carbon::now()->year
            ? Carbon::today()
            : Carbon::parse($year.'-12-31')->startOfDay();

        if ($from->greaterThan($to)) {
            return 0;
        }

        $months = ($to->year - $from->year) * 12 + $to->month - $from->month;

        return max(0, min(12, $to->day < $from->day ? $months - 1 : $months));
    }

    /**
     * What one type is worth to this person this year. Every other type is given in full from the day they join;
     * Annual Leave is only what they have accrued, so a person three months in cannot book 21 days.
     */
    public static function entitled(User $user, string $type, int $year): float
    {
        $cap = (float) (self::TYPES[$type]['days'] ?? 0);

        if ($type !== 'annual') {
            return $cap;
        }

        return min($cap, round(self::monthsWorked($user, $year) * self::ANNUAL_ACCRUAL, 2));
    }

    /** Days already approved, still waited on, and the balance left to ask for — read off the leave rows. */
    public static function balance(User $user, string $type, ?int $year = null): array
    {
        $year ??= Carbon::now()->year;

        $counts = fn (array $statuses) => LeaveRequest::query()
            ->where('user_id', $user->id)
            ->where('type', $type)
            ->whereYear('starts_on', $year)
            ->whereIn('status', $statuses)
            ->sum('days');

        $entitled = static::entitled($user, $type, $year);
        $taken = (float) $counts(['approved']);
        $pending = (float) $counts(['pending']);

        return [
            'type' => $type,
            'name' => static::name($type),
            'year' => $year,
            'entitled' => $entitled,
            'taken' => $taken,
            'pending' => $pending,
            'left' => round(max(0, $entitled - $taken - $pending), 2),
            'accrual' => $type === 'annual' ? self::ANNUAL_ACCRUAL : null,
            'months' => $type === 'annual' ? static::monthsWorked($user, $year) : null,
        ];
    }

    /** The seven tiles, in the order the office writes them. */
    public static function balances(User $user, ?int $year = null): array
    {
        return array_map(fn (string $type) => static::balance($user, $type, $year), array_keys(self::TYPES));
    }

    /**
     * Everyone who works inside the system may book leave: an office account and an E-Fundi who is engaged to work
     * a site or train a cohort. A CTP is a trainee being taught, not a worker on a payroll, so no balance opens.
     */
    public static function worker(User $user): bool
    {
        return in_array($user->role, ['admin', 'superadmin', 'fundi'], true);
    }

    /** Only the Human Resources desk answers a leave request; a super admin holds every department already. */
    public static function decides(User $user): bool
    {
        return $user->isSuperAdmin() || in_array('hr', $user->departmentSlugs(), true);
    }

    /** The day their counting starts from — the day the office took them on, not the day a login was made. */
    public static function joinedOn(User $user): Carbon
    {
        $fundi = static::fundiOf($user);

        return ($fundi?->created_at ?? $user->created_at ?? Carbon::now())->copy()->startOfDay();
    }

    /** The E-Fundi record behind a login, when the person holds one. Matched on the account's own email. */
    public static function fundiOf(User $user): ?FundiRegistration
    {
        if ($user->role !== 'fundi' || ! $user->email) {
            return null;
        }

        return FundiRegistration::query()
            ->whereRaw('LOWER(email) = ?', [Str::lower($user->email)])
            ->orderBy('id')
            ->first();
    }

    /** Inclusive day count of the dates a person applied for, which is what comes off their balance. */
    public static function days(string $from, string $to): float
    {
        return (float) Carbon::parse($from)->startOfDay()->diffInDays(Carbon::parse($to)->startOfDay()) + 1;
    }

    /**
     * Take one application onto the register. The day count is worked from the dates rather than trusted from the
     * form, so a balance can never be argued with a typed number.
     */
    public static function record(User $user, Request $request): LeaveRequest
    {
        $data = $request->validate([
            'type' => 'required|in:'.implode(',', array_keys(self::TYPES)),
            'starts_on' => 'required|date',
            'ends_on' => 'required|date|after_or_equal:starts_on',
            'cover_for' => 'nullable|string|max:160',
            'phone' => 'nullable|string|max:30',
            'reason' => 'nullable|string|max:1000',
        ], [
            'ends_on.after_or_equal' => 'The last day cannot fall before the first.',
        ], [
            'type' => 'Leave type', 'starts_on' => 'First day', 'ends_on' => 'Last day',
            'cover_for' => 'Who covers your work', 'phone' => 'Reachable on', 'reason' => 'Reason',
        ]);

        $from = Carbon::parse($data['starts_on'])->toDateString();
        $to = Carbon::parse($data['ends_on'])->toDateString();
        $clash = static::block($user, $data['type'], $from, $to);

        if ($clash) {
            throw ValidationException::withMessages(['starts_on' => $clash]);
        }

        $leave = LeaveRequest::create([
            'user_id' => $user->id,
            'type' => $data['type'],
            'starts_on' => $from,
            'ends_on' => $to,
            'days' => static::days($from, $to),
            'cover_for' => $data['cover_for'] ?? null,
            'phone' => $data['phone'] ?? null,
            'reason' => $data['reason'] ?? null,
        ]);

        Audit::record($leave, 'created', $user->name.' asked for '.static::name($leave->type)
            .' from '.$from.' to '.$to.' ('.static::format($leave->days).' days).');

        return $leave;
    }

    /**
     * The desk's answer. Nothing is deleted: a declined week stays on the register with the note that went with it,
     * and the days fall back into the balance because only approved days are counted.
     */
    public static function decide(LeaveRequest $leave, string $status, ?string $note, User $decider): string
    {
        abort_unless(in_array($status, ['approved', 'rejected'], true), 422, 'Answer the application or decline it.');

        // Approving is where two weeks can collide: a person may hold two applications that only clash once the
        // first one goes through, and a balance counted with both of them pending says nothing about that.
        if ($status === 'approved' && static::overlaps($leave->user, $leave->type,
            $leave->starts_on->toDateString(), $leave->ends_on->toDateString(), $leave->id)) {
            throw ValidationException::withMessages(['decision' => 'Another week of '.static::name($leave->type)
                .' already covers those dates — decline one of them.']);
        }

        // These columns decide a person's leave, so they are written directly, never filled from a request array.
        $leave->forceFill([
            'status' => $status,
            'decision_note' => $note ? trim($note) : null,
            'decided_by_user' => $decider->id,
            'decided_at' => now(),
        ])->save();

        Audit::record($leave, 'updated', $decider->name.' '.$status.' '.static::name($leave->type)
            .' for '.$leave->workerLabel().' ('.static::format($leave->days).' days)'
            .($note ? ': '.trim($note) : '.'));

        return $leave->workerLabel().'’s '.static::name($leave->type).' is '.self::STATUSES[$status].'.';
    }

    /**
     * Why an application cannot go in, in the words the person needs — or null when the dates and the balance are
     * both sound. Overlapping an approved week is refused here rather than silently booking the same day twice.
     */
    public static function block(User $user, string $type, string $from, string $to): ?string
    {
        if (Carbon::parse($to)->lessThan(Carbon::parse($from))) {
            return 'The last day cannot fall before the first.';
        }

        $days = static::days($from, $to);
        $left = static::balance($user, $type)['left'];

        if ($days > $left) {
            return 'Only '.static::format($left).' day'.($left == 1 ? '' : 's').' of '
                .static::name($type).' is left for '.Carbon::parse($from)->year.'.';
        }

        return static::overlaps($user, $type, $from, $to)
            ? 'Those dates fall inside a '.static::name($type).' week already on the register.'
            : null;
    }

    /** Is this person's leave already booked across those dates? A cancelled or declined week never blocks. */
    public static function overlaps(User $user, string $type, string $from, string $to, ?int $except = null): bool
    {
        return LeaveRequest::query()
            ->where('user_id', $user->id)
            ->where('type', $type)
            ->whereIn('status', ['pending', 'approved'])
            ->when($except, fn ($q) => $q->where('id', '!=', $except))
            ->where('starts_on', '<=', $to)
            ->where('ends_on', '>=', $from)
            ->exists();
    }
}
