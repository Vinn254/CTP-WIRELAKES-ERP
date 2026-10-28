<?php

namespace App\Support;

use App\Models\FinanceEntry;
use App\Models\PayrollLine;
use App\Models\PayrollRun;
use App\Models\StaffRecord;
use App\Models\TimeLog;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

/**
 * The month's payroll, from the staff file to the money register. Human Resources runs it — every person on the
 * directory is pulled onto the sheet at the pay they are on, allowances are added and every deduction taken off —
 * then sends the week's work to Finance, which sees the staff to be paid and settles them one by one. Each
 * settlement posts its own line to the register, so pay is never paid twice and never paid without a trace.
 */
class Payroll
{
    public static function hr(?User $user = null): bool
    {
        $user ??= auth()->user();

        return (bool) $user && ($user->isSuperAdmin() || in_array('hr', Departments::visibleSlugs($user), true));
    }

    public static function finance(?User $user = null): bool
    {
        $user ??= auth()->user();

        return (bool) $user && ($user->isSuperAdmin() || in_array('finance', Departments::visibleSlugs($user), true));
    }

    /** The months a run can be drawn for, newest first — a month is paid once. */
    public static function periods(int $back = 24): array
    {
        return collect(range(0, $back - 1))
            ->map(fn ($i) => Carbon::today()->startOfMonth()->subMonths($i))
            ->mapWithKeys(fn (Carbon $m) => [$m->toDateString() => $m->translatedFormat('F Y')])
            ->all();
    }

    /** Everyone the programme pays: a person on the staff file with an account, or one already on a salary. */
    public static function people(): Collection
    {
        return StaffRecord::query()
            ->where(fn ($q) => $q->whereNotNull('user_id')->orWhereNotNull('salary'))
            ->orderBy('name')
            ->get(['id', 'name', 'email', 'job_title', 'salary', 'bank_account', 'phone', 'user_id']);
    }

    /**
     * Draw the month's sheet. The run is built from the directory as it stands, so nobody working in the
     * programme is missing from pay; the figures are then worked line by line.
     */
    public static function open(string $period, ?string $note, User $actor): PayrollRun
    {
        $on = Carbon::parse($period)->startOfMonth();

        abort_if(PayrollRun::where('period', $on->toDateString())->exists(), 422,
            'Payroll for '.$on->translatedFormat('F Y').' has already been run. Correct it on that sheet — '
            .'a month is never run twice.');

        $run = PayrollRun::create([
            'reference' => PayrollRun::nextReference($on->toDateString()),
            'period' => $on->toDateString(),
            'status' => 'prepared',
            'note' => $note,
            'prepared_by_user' => $actor->id,
        ]);

        foreach (static::people() as $record) {
            $run->lines()->make(static::fromRecord($record))->save();
        }

        Audit::record($run, 'created', $actor->name.' opened the payroll for '.$run->periodLabel().' with '
            .$run->lines()->count().' staff on the sheet.');

        return $run->load('lines');
    }

    private static function fromRecord(StaffRecord $record): array
    {
        return [
            'staff_record_id' => $record->id,
            'name' => $record->name,
            'email' => $record->email,
            'job_title' => $record->job_title,
            'department' => static::departmentName($record->user_id),
            'basic_ksh' => (float) ($record->salary ?? 0),
        ];
    }

    /** The desk a person is known by on the sheet — the first department their account holds. */
    private static function departmentName(?int $userId): ?string
    {
        foreach ((array) (User::find($userId)?->departments ?? []) as $slug) {
            if ($dept = Departments::find($slug)) {
                return $dept['name'];
            }
        }

        return null;
    }

    /** A person onboarded after the sheet was drawn is added to it, at the pay on their file. */
    public static function add(PayrollRun $run, int $recordId, Request $request): string
    {
        static::editing($run);

        abort_if($run->lines()->where('staff_record_id', $recordId)->exists(), 422,
            'That person is already on the sheet for '.$run->periodLabel().'.');

        $record = StaffRecord::findOrFail($recordId);
        $line = $run->lines()->create(static::fromRecord($record));
        $line->recompute()->save();

        Audit::record($run, 'updated', $record->name.' was added to the payroll for '.$run->periodLabel().'.');

        return $record->name.' is on the sheet for '.$run->periodLabel()
            .($line->net_ksh > 0 ? ' at '.$line->netLabel().' net.' : ' — set the allowances and deductions now.');
    }

    /**
     * The figures on one person's line. Whatever was there is replaced, never kept beside it, so the sheet always
     * shows the pay the office last worked out.
     */
    public static function figures(PayrollLine $line, Request $request): string
    {
        static::editing($line->run);

        $line->fill(PayrollLine::figuresFrom($request));
        $line->recompute()->save();

        Audit::record($line, 'updated');

        return $line->name.' — '.$line->run->periodLabel().' settles at '.$line->netLabel()
            .' ('.Finance::ksh((float) $line->gross_ksh).' gross, '.Finance::ksh((float) $line->deductions_ksh)
            .' off).';
    }

    /** A person is held back with a reason — their line stays on the sheet and is not paid this month. */
    public static function hold(PayrollLine $line, string $reason): string
    {
        static::editing($line->run);

        $line->update(['status' => 'held', 'hold_reason' => $reason]);
        Audit::record($line, 'updated', $line->name.' was held back from the payroll for '.$line->run->periodLabel());

        return $line->name.' is held back from '.$line->run->periodLabel().' and stays on the sheet with its reason.';
    }

    public static function releaseHold(PayrollLine $line): string
    {
        static::editing($line->run);

        $line->update(['status' => 'unpaid', 'hold_reason' => null]);
        Audit::record($line, 'updated', $line->name.' was put back on the payroll for '.$line->run->periodLabel());

        return $line->name.' is back on the list to be paid for '.$line->run->periodLabel().'.';
    }

    /** Human Resources sends the sheet to Finance. Every line to be paid must carry a figure first. */
    public static function send(PayrollRun $run, User $actor): string
    {
        static::editing($run);

        $blank = $run->payableLines()->filter(fn (PayrollLine $l) => (float) $l->gross_ksh <= 0)->pluck('name');
        abort_if($run->payableLines()->isEmpty(), 422,
            'There is nobody left to pay on '.$run->periodLabel().'.');
        abort_if($blank->isNotEmpty(), 422,
            'Set the pay on '.implode(', ', $blank->all()).' before sending '
            .$run->periodLabel().' to Finance — a line cannot go for payment on zero.');

        $run->update(['status' => 'sent', 'sent_by_user' => $actor->id, 'sent_at' => now(), 'return_reason' => null]);
        Audit::record($run, 'updated', $actor->name.' sent the payroll for '.$run->periodLabel().' to Finance: '
            .Finance::ksh($run->totals()['unpaid']).' across '.$run->totals()['unpaid_count'].' staff.');

        return $run->periodLabel().' payroll is with Finance — '
            .Finance::ksh((float) $run->refresh()->totals()['unpaid']).' to settle.';
    }

    /** Finance sends a sheet back rather than paying wrong figures. The work stays on the same run. */
    public static function returnTo(PayrollRun $run, string $reason, User $actor): string
    {
        abort_unless($run->status === 'sent', 422,
            $run->reference.' is '.$run->statusLabel().', so it cannot be sent back.');

        $paid = $run->lines()->where('status', 'paid')->count();
        abort_if($paid > 0, 422,
            $run->periodLabel().' has already paid '.$paid.' of its staff, so it cannot be returned. '
            .'Finish the month or hold the lines that are wrong.');

        $run->update(['status' => 'returned', 'return_reason' => $reason]);
        Audit::record($run, 'updated', $actor->name.' returned the payroll for '.$run->periodLabel()
            .' to Human Resources: '.$reason);

        return $run->periodLabel().' payroll is back with Human Resources: '.$reason;
    }

    /**
     * One person paid. The settlement posts its own line to the money register, so the register and the sheet can
     * never disagree, and the line can never be paid twice.
     */
    public static function pay(PayrollLine $line, Request $request): string
    {
        $run = $line->run;
        abort_unless(static::finance(), 403, 'Payroll is settled by the Finance desk.');
        abort_unless($run->status === 'sent', 422,
            $run->periodLabel().' payroll is '.$run->statusLabel().', so Finance cannot pay it yet.');
        abort_if($line->status === 'paid', 422,
            $line->name.' has already been paid for '.$run->periodLabel().' ('
            .($line->provider_ref ?: $line->paid_on?->format('d M Y')).').');
        abort_if($line->status === 'held', 422,
            $line->name.' is held back from '.$run->periodLabel().' — put the line back on the list first.');
        abort_if((float) $line->net_ksh <= 0, 422,
            $line->name.' settles at nothing for '.$run->periodLabel().'. Hold the line with its reason instead.');

        $data = $request->validate([
            'method' => 'required|in:'.implode(',', array_keys(PayrollLine::METHODS)),
            'payee_account' => 'nullable|string|max:120',
            'provider_ref' => 'nullable|string|max:60',
            'paid_on' => 'required|date|before_or_equal:today',
        ], [], [
            'method' => 'How they are paid',
            'payee_account' => 'Account or phone number',
            'provider_ref' => 'Payment reference',
            'paid_on' => 'Date paid',
        ]);

        $line->update($data + ['status' => 'paid', 'paid_by_user' => auth()->id()]);

        $entry = FinanceEntry::post('payroll', 'Payroll — '.$line->name.' — '.$run->periodLabel(),
            (float) $line->net_ksh, [
                'method' => $data['method'],
                'happened_on' => $data['paid_on'],
                'budget_category' => 'operations',
                'note' => $run->reference.' · '.$line->netLabel().' net of '.Finance::ksh((float) $line->deductions_ksh)
                    .' deductions'.($data['provider_ref'] ? ' · ref '.$data['provider_ref'] : ''),
                'source_type' => PayrollLine::class,
                'source_id' => $line->id,
            ]);

        Audit::record($line, 'updated', $line->name.' was paid '.$line->netLabel().' for '
            .$run->periodLabel().' ('.$line->methodLabel().', '.$entry->reference.').');

        static::closeIfSettled($run);

        return $line->name.' was paid '.$line->netLabel().' for '.$run->periodLabel().' by '
            .$line->methodLabel().' — posted to the register as '.$entry->reference.'.';
    }

    /** The month is closed once nobody on it is left unpaid. */
    private static function closeIfSettled(PayrollRun $run): void
    {
        $run->refresh();

        if ($run->status !== 'sent' || $run->lines()->where('status', 'unpaid')->exists()) {
            return;
        }

        $run->update(['status' => 'paid', 'paid_by_user' => auth()->id(), 'paid_at' => now()]);
        Audit::record($run, 'updated', 'Every staff on the payroll for '.$run->periodLabel()
            .' is settled — '.Finance::ksh($run->totals()['paid']).' paid out.');
    }

    private static function editing(PayrollRun $run): void
    {
        abort_unless(static::hr(), 403, 'Payroll is prepared by the Human Resources desk.');
        $run->mustBe('prepared', 'returned');
    }

    /** What the sheet needs to render: the lines, their totals, the money register's lines and who is missing. */
    public static function desk(PayrollRun $run): array
    {
        $run->load('lines', 'preparer', 'sender', 'payer');
        $onSheet = $run->lines->pluck('staff_record_id')->filter()->all();

        return [
            'run' => $run,
            'lines' => $run->lines,
            'totals' => $run->totals(),
            'people' => static::people()->reject(fn (StaffRecord $r) => in_array($r->id, $onSheet, true))->values(),
            'columns' => PayrollLine::COLUMNS,
            'methods' => PayrollLine::METHODS,
            'ledger' => FinanceEntry::where('source_type', PayrollLine::class)
                ->whereIn('source_id', $run->lines->pluck('id'))
                ->orderBy('id')->get(),
            'approvedWeeks' => TimeLog::where('status', 'approved')
                ->whereBetween('week_start', [
                    $run->period->copy()->startOfMonth()->toDateString(),
                    $run->period->copy()->endOfMonth()->toDateString(),
                ])->count(),
        ];
    }

    /** The lines behind one person's pay — what a slip and the register both print. */
    public static function ledger(PayrollLine $line): Collection
    {
        return FinanceEntry::where('source_type', PayrollLine::class)->where('source_id', $line->id)
            ->orderByDesc('id')->get();
    }

    /** What each month has cost in pay, for the reports and the overview tiles. */
    public static function paidThrough(?Carbon $month = null): float
    {
        return (float) PayrollLine::where('status', 'paid')
            ->whereHas('run', fn ($q) => $q->where('period', '<=', ($month ?? Carbon::today())->endOfMonth()->toDateString()))
            ->sum('net_ksh');
    }
}
