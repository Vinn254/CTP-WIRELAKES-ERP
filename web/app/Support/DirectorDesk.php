<?php

namespace App\Support;

use App\Models\ActivityLog;
use App\Models\ContactMessage;
use App\Models\FinanceEntry;
use App\Models\Payout;
use App\Models\Setting;
use App\Models\User;
use App\Models\VariationOrder;
use App\Models\WorkOrder;
use Illuminate\Support\Collection;

/**
 * The director's own desk. A department shows one module; this shows the whole office at once — what every
 * module is holding, the figures a decision is made on, what the staff have actually done, and the short list of
 * things that cannot be finished without a signature from this account.
 */
class DirectorDesk
{
    /** The settings the letters and the Paybill instructions cannot be written without. */
    private const ESSENTIALS = ['paybill_number', 'paybill_account', 'programme_email'];

    /** The account-side lines are not work, so they stay out of "what the office did". */
    private const NOT_WORK = [
        'signed_in', 'signed_out', 'failed_sign_in', 'otp_sent', 'otp_verified',
        'reset_sent', 'reset_used', 'reset_refused', 'account_created',
    ];

    /** Every module with its own queue, the busiest first. */
    public static function modules(): array
    {
        $rows = array_map(fn (array $d) => [
            'slug' => $d['slug'],
            'name' => $d['name'],
            'desc' => $d['desc'],
            'icon' => Departments::icon($d['slug']),
            'href' => route('admin.department', $d['slug']),
            'pages' => count(Departments::nav($d['slug'])),
            'waiting' => Departments::waiting($d['slug']),
        ], Departments::live());

        usort($rows, fn (array $a, array $b) => [$b['waiting'], $a['name']] <=> [$a['waiting'], $b['name']]);

        return $rows;
    }

    /**
     * What only a director can close. Each row goes straight to the desk where the signature is given, so
     * nothing here is a summary of a decision someone else has already taken.
     *
     * @return array<int, array{label: string, note: string, icon: string, route: array, count: int, aside?: string}>
     */
    public static function approvals(): array
    {
        $owed = Payout::whereIn('stage', ['queued', 'approved']);

        $rows = [
            ['Certificates to issue', 'Recommended by the Registrar, waiting to be signed out.',
                'award', ['admin.certification.index'], Departments::badge('certificates')],
            ['Payouts to release', 'Prepared by Finance. Release is a director\'s act.',
                'check', ['admin.payouts.index'], Departments::badge('payouts'),
                Finance::ksh((float) $owed->sum('net_ksh'))],
            ['Purchase orders to pay', 'Goods received and matched. The supplier is paid next.',
                'briefcase', ['admin.procurement.index'], Departments::badge('orders')],
            ['Payroll waiting to be paid', 'A sheet HR has sent that Finance has not settled.',
                'clipboard', ['admin.payroll.index'], Departments::badge('payroll')],
            ['CTP profiles waiting', 'Papers are in. Approve the profile or send it back.',
                'users', ['admin.registrations.index', 'ctp'], Departments::badge('promoters')],
            ['E-Fundi profiles waiting', 'Checked against trade and county before the ID.',
                'hardhat', ['admin.registrations.index', 'fundi'], Departments::badge('fundis')],
            ['Work in dispute', 'A hirer and an E-Fundi disagree on a job.',
                'wrench', ['admin.execution.index'], WorkOrder::where('status', 'disputed')->count()],
            ['Construction variations', 'A change to agreed works. Nothing builds until approved.',
                'cap', ['admin.construction.index'], VariationOrder::where('status', 'proposed')->count()],
            ['Staff accounts with no job title', 'They sign in but open no desk.',
                'key', ['admin.staff.index'], static::accountsWithoutRole()],
            ['Programme settings still blank', 'Paybill and office address the letters use.',
                'sliders', ['admin.programme.index'], collect(self::ESSENTIALS)
                    ->filter(fn (string $key) => blank(Setting::get($key)))->count()],
            ['Website messages this week', 'Enquiries sent through the public Contact page.',
                'mail', ['admin.messages.index'], ContactMessage::where('created_at', '>=', now()->subWeek())->count()],
        ];

        return array_map(fn (array $r) => array_filter([
            'label' => $r[0], 'note' => $r[1], 'icon' => $r[2], 'route' => $r[3], 'count' => $r[4], 'aside' => $r[5] ?? null,
        ], fn ($v) => $v !== null), $rows);
    }

    /** The screens the office actually opens, counted once each however many modules list them. */
    public static function pages(): int
    {
        return collect(Departments::live())
            ->flatMap(fn (array $d) => array_column(Departments::nav($d['slug']), 'route'))
            ->pluck(0)->unique()->count();
    }

    /** The total sitting in front of this desk right now. */
    public static function waiting(): int
    {
        return array_sum(array_column(static::approvals(), 'count'));
    }

    /** The figures a decision is made on — the same totals the impact dashboard reads. */
    public static function numbers(): array
    {
        return Impact::headline();
    }

    /** The money in one line, so a director never has to open Finance to know where the programme stands. */
    public static function money(): array
    {
        $in = (float) FinanceEntry::where('direction', 'income')->sum('amount_ksh');
        $out = (float) FinanceEntry::where('direction', 'expense')->sum('amount_ksh');

        return [
            ['label' => 'Money in', 'value' => $in, 'unit' => 'money', 'note' => 'Fees, donations and partner money on the register'],
            ['label' => 'Money out', 'value' => $out, 'unit' => 'money', 'note' => 'Charged against the budget lines'],
            ['label' => 'Left in the register', 'value' => $in - $out, 'unit' => 'money', 'note' => 'What the programme can spend today'],
            ['label' => 'Owed and waiting', 'value' => (float) Payout::whereIn('stage', ['queued', 'approved'])->sum('net_ksh'),
                'unit' => 'money', 'note' => 'Approved by the desk, not yet released'],
        ];
    }

    /** What the office has been doing, newest first. */
    public static function movement(int $limit = 12): Collection
    {
        return ActivityLog::query()->whereNotIn('action', self::NOT_WORK)
            ->latest()->limit($limit)->get();
    }

    /** The work lines of the last 30 days, counted by what they were. */
    public static function performed(): array
    {
        $since = now()->subDays(30);
        $lines = ActivityLog::whereNotIn('action', self::NOT_WORK)->where('created_at', '>=', $since);

        return [
            ['label' => 'Records opened', 'value' => (clone $lines)->where('action', 'created')->count()],
            ['label' => 'Records changed', 'value' => (clone $lines)->where('action', 'updated')->count()],
            ['label' => 'Decisions taken', 'value' => (clone $lines)->whereIn('action',
                ['approved', 'rejected', 'released', 'revoked'])->count()],
            ['label' => 'People who worked', 'value' => (clone $lines)->whereNotNull('user_id')->distinct('user_id')->count('user_id')],
            ['label' => 'Signed in today', 'value' => ActivityLog::where('action', 'signed_in')
                ->whereDate('created_at', today())->count()],
        ];
    }

    private static function accountsWithoutRole(): int
    {
        return User::whereIn('role', ['admin', 'superadmin'])->where('role', '!=', 'superadmin')
            ->where(fn ($q) => $q->whereNull('roles')->orWhere('roles', '[]'))->count();
    }
}
