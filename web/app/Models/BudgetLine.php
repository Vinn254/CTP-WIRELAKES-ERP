<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

/**
 * Module 11 — one programme budget line. A programme, a financial year and the amount set for it. The plan the
 * programme was drawn on is built in below; the office can take up a new programme on the budget screen, and its
 * line is set here the same way as any other.
 */
class BudgetLine extends Model
{
    /** , plus office and operations which every programme has to fund but the plan leaves at zero. */
    public const BUILT_IN = [
        'construction' => 'Construction',
        'electrical' => 'Electrical',
        'ict' => 'ICT',
        'networking' => 'Networking',
        'robotics' => 'Robotics',
        'ai' => 'Artificial intelligence',
        'training' => 'Training',
        'ewaste' => 'E-Waste',
        'operations' => 'Office and operations',
    ];

    /** The plan as the programme first meets it — , KSh 5,500,000 for 2026. */
    public const PLAN_2026 = [
        'construction' => 3000000,
        'electrical' => 500000,
        'ict' => 750000,
        'networking' => 250000,
        'robotics' => 300000,
        'ai' => 200000,
        'training' => 400000,
        'ewaste' => 100000,
    ];

    protected $fillable = ['fiscal_year', 'category', 'amount_ksh', 'note', 'set_by_user'];

    protected $casts = ['fiscal_year' => 'integer', 'amount_ksh' => 'decimal:2'];

    /** Every programme money can be planned on — the built-in plan plus whatever the office has taken up. */
    public static function categories(): array
    {
        return Programme::categories();
    }

    public function setter()
    {
        return $this->belongsTo(User::class, 'set_by_user')->withDefault();
    }

    public static function categoryLabel(?string $key): string
    {
        if (! $key) {
            return 'No budget line';
        }

        return self::categories()[$key] ?? ucwords(str_replace('-', ' ', $key));
    }

    /** The years the office has set money for, newest first — the same list the ledger and payouts read. */
    public static function years()
    {
        return self::where('fiscal_year', '>', 0)
            ->select('fiscal_year')->distinct()->orderByDesc('fiscal_year')->pluck('fiscal_year');
    }

    /** Every programme with its line for one year, so the budget page always shows the full plan. */
    public static function planFor(int $year)
    {
        $lines = self::where('fiscal_year', $year)->get()->keyBy('category');
        $programmes = Programme::query()->get()->keyBy('category');

        return collect(self::categories())->map(function ($label, $key) use ($lines, $programmes, $year) {
            return [
                'category' => $key,
                'label' => $label,
                'amount' => (float) ($lines[$key]->amount_ksh ?? 0),
                'line' => $lines[$key] ?? null,
                'programme' => $programmes[$key] ?? null,
                'spent' => FinanceEntry::spent($year, $key),
            ];
        });
    }

    public static function dataFrom(Request $request): array
    {
        return $request->validate([
            'fiscal_year' => 'required|integer|min:2000|max:2100',
            'category' => 'required|in:'.implode(',', array_keys(self::categories())),
            'amount_ksh' => 'required|numeric|min:0|max:1000000000',
            'note' => 'nullable|string|max:1000',
        ], [], ['fiscal_year' => 'Financial year', 'category' => 'Budget category', 'amount_ksh' => 'Amount budgeted']);
    }
}
