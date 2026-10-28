<?php

namespace App\Models;

use App\Support\Finance;
use Illuminate\Database\Eloquent\Model;

/**
 * A month of payroll — one run, one line per person on the staff file. Human Resources prepares it and sends it;
 * Finance settles it. A month is run once: an error is corrected on the same run, never a second run for the month.
 */
class PayrollRun extends Model
{
    public const STATUSES = [
        'prepared' => 'Being prepared by Human Resources',
        'sent' => 'With Finance for payment',
        'returned' => 'Returned to Human Resources',
        'paid' => 'Paid',
    ];

    protected $fillable = [
        'reference', 'period', 'status', 'note', 'return_reason',
        'prepared_by_user', 'sent_by_user', 'sent_at', 'paid_by_user', 'paid_at',
    ];

    protected $casts = ['period' => 'date', 'sent_at' => 'datetime', 'paid_at' => 'datetime'];

    public function lines()
    {
        return $this->hasMany(PayrollLine::class)->orderBy('name');
    }

    public function preparer()
    {
        return $this->belongsTo(User::class, 'prepared_by_user')->withDefault();
    }

    public function sender()
    {
        return $this->belongsTo(User::class, 'sent_by_user')->withDefault();
    }

    public function payer()
    {
        return $this->belongsTo(User::class, 'paid_by_user')->withDefault();
    }

    public function statusLabel(): string
    {
        return self::STATUSES[$this->status] ?? ucfirst($this->status);
    }

    /** October 2026 — the way the office names a month's pay. */
    public function periodLabel(): string
    {
        return $this->period->translatedFormat('F Y');
    }

    /** Money already settled and money still owed, straight off the lines. */
    public function totals(): array
    {
        $lines = $this->lines;

        return [
            'people' => $lines->count(),
            'gross' => (float) $lines->sum('gross_ksh'),
            'allowances' => (float) $lines->sum(fn ($l) => $l->allowances()),
            'deductions' => (float) $lines->sum('deductions_ksh'),
            'net' => (float) $lines->sum('net_ksh'),
            'unpaid' => (float) $lines->where('status', 'unpaid')->sum('net_ksh'),
            'paid' => (float) $lines->where('status', 'paid')->sum('net_ksh'),
            'unpaid_count' => $lines->where('status', 'unpaid')->count(),
            'paid_count' => $lines->where('status', 'paid')->count(),
            'held_count' => $lines->where('status', 'held')->count(),
            'held' => (float) $lines->where('status', 'held')->sum('net_ksh'),
        ];
    }

    /** The people the run will actually settle — a held line stays with Human Resources for the next month. */
    public function payableLines()
    {
        return $this->lines->where('status', 'unpaid');
    }

    /** The run can still be worked on while Human Resources holds it, and again if Finance sends it back. */
    public function isOpen(): bool
    {
        return in_array($this->status, ['prepared', 'returned'], true);
    }

    public function mustBe(string ...$statuses): void
    {
        if (in_array($this->status, $statuses, true)) {
            return;
        }

        abort(422, $this->reference.' is '.$this->statusLabel().', so that action is not open on it. '
            .'It has to be: '.collect($statuses)->map(fn ($s) => self::STATUSES[$s] ?? $s)->join(' or ').'.'
            .' Nothing is deleted and no second run is made for '.$this->periodLabel().'.');
    }

    /** PR-2026-010 — the month the run belongs to, so a slip and a ledger line always agree. */
    public static function nextReference(string $period): string
    {
        $on = strtotime($period);

        return 'PR-'.date('Y', $on).'-'.str_pad((string) date('n', $on), 2, '0', STR_PAD_LEFT);
    }

    public function amountLabel(float $amount): string
    {
        return Finance::ksh($amount);
    }
}
