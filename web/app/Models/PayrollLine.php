<?php

namespace App\Models;

use App\Support\Finance;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

/**
 * One person's month of pay: what they are on, the allowances added, every deduction taken off, and the net the
 * programme owes them. The figures are held as typed so a slip prints exactly what Human Resources worked out.
 */
class PayrollLine extends Model
{
    public const ALLOWANCES = [
        'house_ksh' => 'House allowance',
        'transport_ksh' => 'Transport allowance',
        'other_allowance_ksh' => 'Other allowance',
    ];

    public const DEDUCTIONS = [
        'paye_ksh' => 'PAYE',
        'nssf_ksh' => 'NSSF',
        'shif_ksh' => 'SHIF / NHIF',
        'levy_ksh' => 'Housing levy',
        'other_deduction_ksh' => 'Loan, advance or other',
    ];

    /** What each figure is called on the screen and on the slip — the order the columns print in. */
    public const COLUMNS = [
        'basic_ksh' => 'Basic pay',
        'house_ksh' => 'House',
        'transport_ksh' => 'Transport',
        'other_allowance_ksh' => 'Other allowance',
        'paye_ksh' => 'PAYE',
        'nssf_ksh' => 'NSSF',
        'shif_ksh' => 'SHIF / NHIF',
        'levy_ksh' => 'Housing levy',
        'other_deduction_ksh' => 'Other deduction',
    ];

    public const METHODS = ['bank' => 'Bank transfer', 'mpesa' => 'M-Pesa', 'cheque' => 'Cheque', 'cash' => 'Cash'];

    public const STATUSES = ['unpaid' => 'Waiting to be paid', 'paid' => 'Paid', 'held' => 'Held back this month'];

    protected $fillable = [
        'payroll_run_id', 'staff_record_id', 'name', 'email', 'job_title', 'department',
        'basic_ksh', 'house_ksh', 'transport_ksh', 'other_allowance_ksh', 'other_allowance_note',
        'paye_ksh', 'nssf_ksh', 'shif_ksh', 'levy_ksh', 'other_deduction_ksh', 'other_deduction_note',
        'gross_ksh', 'deductions_ksh', 'net_ksh',
        'status', 'hold_reason', 'method', 'payee_account', 'provider_ref', 'paid_on', 'paid_by_user',
    ];

    protected $casts = [
        'basic_ksh' => 'decimal:2', 'house_ksh' => 'decimal:2', 'transport_ksh' => 'decimal:2',
        'other_allowance_ksh' => 'decimal:2', 'paye_ksh' => 'decimal:2', 'nssf_ksh' => 'decimal:2',
        'shif_ksh' => 'decimal:2', 'levy_ksh' => 'decimal:2', 'other_deduction_ksh' => 'decimal:2',
        'gross_ksh' => 'decimal:2', 'deductions_ksh' => 'decimal:2', 'net_ksh' => 'decimal:2',
        'paid_on' => 'date',
    ];

    public function run()
    {
        return $this->belongsTo(PayrollRun::class, 'payroll_run_id');
    }

    public function record()
    {
        return $this->belongsTo(StaffRecord::class, 'staff_record_id')->withDefault();
    }

    public function payer()
    {
        return $this->belongsTo(User::class, 'paid_by_user')->withDefault();
    }

    /** The pay figures a month's line carries, in the closed order the sheet prints them. */
    public static function figuresFrom(Request $request): array
    {
        $fields = collect(self::COLUMNS)->keys()->implode(',');

        $data = $request->validate([
            'name' => 'required|string|max:150',
            'job_title' => 'nullable|string|max:120',
            'department' => 'nullable|string|max:90',
        ] + array_fill_keys(explode(',', $fields), 'nullable|numeric|min:0|max:99999999') + [
            'other_allowance_note' => 'nullable|string|max:90',
            'other_deduction_note' => 'nullable|string|max:90',
        ], [
            'basic_ksh.min' => 'Basic pay cannot be below zero.',
        ], self::COLUMNS + ['name' => 'Person', 'job_title' => 'Job title', 'department' => 'Department']);

        foreach (array_keys(self::COLUMNS) as $k) {
            $data[$k] = round((float) ($data[$k] ?? 0), 2);
        }

        return $data;
    }

    /** Everything added on top of basic pay. */
    public function allowances(): float
    {
        return round((float) $this->house_ksh + (float) $this->transport_ksh + (float) $this->other_allowance_ksh, 2);
    }

    /** Everything taken off. */
    public function deductionTotal(): float
    {
        return round(array_sum(array_map(fn ($k) => (float) $this->{$k}, array_keys(self::DEDUCTIONS))), 2);
    }

    /**
     * Gross, deductions and net, worked the same way on the register, the slip and the ledger. A person is never
     * taken below zero by a deduction — the office corrects the figure instead.
     */
    public function recompute(): self
    {
        $gross = round((float) $this->basic_ksh + $this->allowances(), 2);
        $deductions = $this->deductionTotal();

        abort_if($deductions > $gross, 422,
            $this->name.' cannot be paid for '.$this->run->periodLabel().' with deductions of '
            .Finance::ksh($deductions).' against gross pay of '.Finance::ksh($gross)
            .'. Settle the figures on the line first.');

        $this->gross_ksh = $gross;
        $this->deductions_ksh = $deductions;
        $this->net_ksh = round($gross - $deductions, 2);

        return $this;
    }

    public function statusLabel(): string
    {
        return self::STATUSES[$this->status] ?? ucfirst($this->status);
    }

    public function methodLabel(): string
    {
        return self::METHODS[$this->method] ?? '—';
    }

    public function netLabel(): string
    {
        return Finance::ksh((float) $this->net_ksh);
    }

    public function money(float $amount): string
    {
        return Finance::ksh($amount);
    }

    /** The allowance and deduction lines worth printing on a slip, each with the note the office typed. */
    public function breakdown(string $side): array
    {
        $keys = $side === 'allowance' ? self::ALLOWANCES : self::DEDUCTIONS;
        $note = $side === 'allowance' ? $this->other_allowance_note : $this->other_deduction_note;

        return collect($keys)
            ->map(fn ($label, $column) => [
                'label' => $column === 'other_allowance_ksh' || $column === 'other_deduction_ksh'
                    ? ($note ?: $label) : $label,
                'amount' => (float) $this->{$column},
            ])
            ->where('amount', '>', 0)
            ->values()
            ->all();
    }
}
