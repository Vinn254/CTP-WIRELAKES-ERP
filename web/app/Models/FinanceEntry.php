<?php

namespace App\Models;

use App\Support\Finance;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Module 11 — the ledger. Every shilling in and every shilling out is one line here, tied to the budget
 * line it is charged against and, where it belongs to something, to that project, cohort, supplier or member.
 * Purchases that were paid and payouts that were released post their own lines: the office never retypes them.
 */
class FinanceEntry extends Model
{
    public const DIRECTIONS = ['income' => 'Money in', 'expense' => 'Money out'];

    public const INCOME_KINDS = [
        'membership_fee' => 'Membership payment',
        'training_fee' => 'Course fee paid by a trainee',
        'project_payment' => 'Payment from a hirer',
        'invoice_settlement' => 'Payment against an invoice',
        'service_commission' => 'Service commission',
        'refurbished_sale' => 'Sale of a refurbished device',
        'recycling_sale' => 'Recycling sale',
        'donor_fund' => 'Donor funds',
        'partner_fund' => 'Partner funds',
        'internal_fund' => 'Internal funds',
        'other_income' => 'Other money in',
    ];

    public const EXPENSE_KINDS = [
        'procurement' => 'Goods bought on a purchase order',
        'payroll' => 'Staff payroll — salaries',
        'fundi_payout' => 'E-Fundi payout — project work',
        'trainer_payout' => 'Trainer payout — training delivery',
        'materials_issued' => 'Materials issued from inventory',
        'transport' => 'Transport',
        'accommodation' => 'Accommodation',
        'training_cost' => 'Training costs — materials and venue',
        'project_misc' => 'Other project expense',
        'operating_cost' => 'Office and operating cost',
    ];

    /** Which budget line a kind of money falls out of, so the office cannot charge training money to a build. */
    public const KIND_BUDGET = [
        'procurement' => null,          // the request already carries its budget category
        'fundi_payout' => null,         // the project's own category
        'trainer_payout' => 'training',
        'materials_issued' => null,     // the item's own category
        'transport' => 'operations',
        'accommodation' => 'operations',
        'training_cost' => 'training',
        'project_misc' => null,
        'operating_cost' => 'operations',
    ];

    public const METHODS = [
        'mpesa' => 'M-Pesa',
        'bank' => 'Bank transfer',
        'cheque' => 'Cheque',
        'cash' => 'Cash',
        'other' => 'Other means',
        'pending' => 'Not yet settled',
    ];

    public const KINDS = self::INCOME_KINDS + self::EXPENSE_KINDS;

    protected $fillable = [
        'reference', 'fiscal_year', 'direction', 'kind', 'budget_category', 'title', 'amount_ksh', 'method',
        'happened_on', 'note', 'project_id', 'cohort_id', 'community_id', 'ctp_id', 'fundi_id',
        'source_type', 'source_id', 'recorded_by_user',
    ];

    protected $casts = [
        'fiscal_year' => 'integer',
        'amount_ksh' => 'decimal:2',
        'happened_on' => 'date',
    ];

    /** What the office types in when it records money itself. */
    public static function dataFrom(Request $request): array
    {
        $kinds = array_keys(self::KINDS);

        $data = $request->validate([
            'direction' => 'required|in:income,expense',
            'kind' => 'required|in:'.implode(',', $kinds),
            'title' => 'required|string|max:190',
            'amount_ksh' => 'required|numeric|min:0.01|max:1000000000',
            'happened_on' => 'required|date',
            'method' => 'required|in:'.implode(',', array_keys(self::METHODS)),
            'budget_category' => 'nullable|in:'.implode(',', array_keys(BudgetLine::categories())),
            'project_id' => 'nullable|integer|exists:projects,id',
            'cohort_id' => 'nullable|integer|exists:cohorts,id',
            'community_id' => 'nullable|integer|exists:communities,id',
            'fundi_id' => 'nullable|integer|exists:fundi_registrations,id',
            'ctp_id' => 'nullable|integer|exists:ctp_registrations,id',
            'note' => 'nullable|string|max:1000',
        ], [
            'kind.required' => 'Say what kind of money this is — the ledger only accepts the tracked items.',
            'method.pending' => 'Money that has not settled yet is still recorded, with its date.',
        ], [
            'kind' => 'What it is',
            'amount_ksh' => 'Amount',
            'happened_on' => 'Date',
            'budget_category' => 'Charged to',
        ]);

        $data['direction'] = self::directionOf($data['kind']);
        // Funding in is not charged anywhere; money out always sits on a budget line.
        $data['budget_category'] = $data['direction'] === 'income'
            ? null
            : ($data['budget_category'] ?? self::budgetCategoryFor($data));

        return $data;
    }

    public static function directionOf(string $kind): string
    {
        return isset(self::EXPENSE_KINDS[$kind]) ? 'expense' : 'income';
    }

    /** The budget line a line of money belongs to. A request or a project already knows; the rest follows the kind. */
    public static function budgetCategoryFor(array $data): ?string
    {
        $kind = $data['kind'];

        if ($kind === 'procurement' && ! empty($data['purchase_request_budget_category'])) {
            return $data['purchase_request_budget_category'];
        }

        return self::KIND_BUDGET[$kind] ?? 'operations';
    }

    /** A line the system posts for itself — a paid purchase order, a released payout, an issued stock card. */
    public static function post(string $kind, string $title, float $amount, array $extra = []): self
    {
        $on = $extra['happened_on'] ?? now()->toDateString();

        return static::create([
            'reference' => static::nextReference($on),
            'fiscal_year' => (int) substr($on, 0, 4),
            'direction' => static::directionOf($kind),
            'kind' => $kind,
            'budget_category' => $extra['budget_category'] ?? static::budgetCategoryFor(['kind' => $kind]),
            'title' => $title,
            'amount_ksh' => $amount,
            'method' => $extra['method'] ?? 'pending',
            'happened_on' => $on,
            'note' => $extra['note'] ?? null,
            'project_id' => $extra['project_id'] ?? null,
            'cohort_id' => $extra['cohort_id'] ?? null,
            'community_id' => $extra['community_id'] ?? null,
            'ctp_id' => $extra['ctp_id'] ?? null,
            'fundi_id' => $extra['fundi_id'] ?? null,
            'source_type' => $extra['source_type'] ?? null,
            'source_id' => $extra['source_id'] ?? null,
            'recorded_by_user' => $extra['recorded_by_user'] ?? auth()->id(),
        ]);
    }

    public function project()
    {
        return $this->belongsTo(Project::class)->withDefault();
    }

    public function cohort()
    {
        return $this->belongsTo(Cohort::class)->withDefault();
    }

    public function community()
    {
        return $this->belongsTo(Community::class)->withDefault();
    }

    public function fundi()
    {
        return $this->belongsTo(FundiRegistration::class, 'fundi_id')->withDefault();
    }

    public function recorder()
    {
        return $this->belongsTo(User::class, 'recorded_by_user')->withDefault();
    }

    public function kindLabel(): string
    {
        return self::KINDS[$this->kind] ?? Str::headline($this->kind);
    }

    public function methodLabel(): string
    {
        return self::METHODS[$this->method] ?? Str::headline($this->method);
    }

    public function budgetLabel(): string
    {
        return BudgetLine::categoryLabel($this->budget_category);
    }

    public function amountLabel(): string
    {
        return Finance::ksh((float) $this->amount_ksh);
    }

    /** Signed so one column can show the effect this line has on the programme's money. */
    public function signedAmount(): float
    {
        return $this->direction === 'income' ? (float) $this->amount_ksh : -(float) $this->amount_ksh;
    }

    public static function spent(int $year, string $category): float
    {
        return (float) static::where('fiscal_year', $year)->where('direction', 'expense')
            ->where('budget_category', $category)->sum('amount_ksh');
    }

    public static function earned(int $year, ?string $category = null): float
    {
        return (float) static::where('fiscal_year', $year)->where('direction', 'income')
            ->when($category, fn ($q) => $q->where('budget_category', $category))->sum('amount_ksh');
    }

    /** The money that came in for something — a project's account, a cohort's record. */
    public static function incomeFor(string $type, int $id): float
    {
        return (float) static::where('direction', 'income')->where(static::columnFor($type), $id)->sum('amount_ksh');
    }

    public static function expenseFor(string $type, int $id): float
    {
        return (float) static::where('direction', 'expense')->where(static::columnFor($type), $id)->sum('amount_ksh');
    }

    public static function existsFor(string $type, int $id, string $kind): bool
    {
        return static::where(static::columnFor($type), $id)->where('kind', $kind)->exists();
    }

    private static function columnFor(string $type): string
    {
        return match ($type) {
            'project' => 'project_id',
            'cohort' => 'cohort_id',
            'community' => 'community_id',
            'fundi' => 'fundi_id',
            'ctp' => 'ctp_id',
        };
    }

    /** FIN-2026-000125 — the sequence restarts each year, the way the office numbers its payment vouchers. */
    public static function nextReference(?string $on = null): string
    {
        $year = (int) date('Y', strtotime($on ?? now()->toDateString()));
        $prefix = 'FIN-'.$year.'-';

        $highest = static::where('reference', 'like', $prefix.'%')
            ->selectRaw('MAX(CAST(SUBSTRING(reference, ?) AS UNSIGNED)) n', [strlen($prefix) + 1])
            ->value('n');

        return $prefix.str_pad((string) ((int) $highest + 1), 5, '0', STR_PAD_LEFT);
    }
}
