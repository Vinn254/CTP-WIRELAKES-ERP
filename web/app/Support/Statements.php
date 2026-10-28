<?php

namespace App\Support;

use App\Models\ActivityLog;
use App\Models\BudgetLine;
use App\Models\FinanceEntry;
use App\Models\InventoryItem;
use App\Models\Invoice;
use App\Models\PayrollLine;
use App\Models\PayrollRun;
use App\Models\Payout;
use App\Models\PurchaseOrder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * The nine money statements Finance hands out. Nothing is stored here: each one is the money register, the
 * invoices, the orders, the payouts and the payroll read again in another order, so the same figures print on
 * screen, go into the CSV, and cannot be shaped into a nicer story.
 */
class Statements
{
    public const LIST = [
        'trial-balance' => ['title' => 'Trial balance',
            'note' => 'Every account the money passed through, on the side it moved, so the two sides agree to the shilling.'],
        'balance-sheet' => ['title' => 'Balance sheet',
            'note' => 'What the programme owns, what it owes, and the funds left between the two — as at the day asked.'],
        'profit-loss' => ['title' => 'Profit and loss',
            'note' => 'Money in against money out for the year, by what it was, and by the budget line it was charged to.'],
        'cashflow' => ['title' => 'Cashflow',
            'note' => 'Month by month: what arrived, what left, and the money held at the end of each month.'],
        'general-ledger' => ['title' => 'General ledger',
            'note' => 'Every line of money in date order with the running balance, for the year or for one month.'],
        'ar-ageing' => ['title' => 'Receivables ageing',
            'note' => 'Invoices still owed to the programme, sorted by how long they have been waiting.'],
        'ap-ageing' => ['title' => 'Payables ageing',
            'note' => 'Suppliers, fundis, trainers and staff the programme still has to pay, sorted by how long they have waited.'],
        'payroll-summary' => ['title' => 'Payroll summary',
            'note' => 'Each month Human Resources ran: pay, allowances, every deduction, and what Finance has settled.'],
        'finance-audit' => ['title' => 'Finance audit trail',
            'note' => 'Who moved which money record, what changed, and when — the register’s own story.'],
    ];

    /** @return array{key:string,title:string,reader:string,note:string,year:int,month:?string,sections:array,foot:string} */
    public static function build(string $key, int $year, ?string $month = null): array
    {
        $entry = self::LIST[$key];

        return [
            'key' => $key,
            'title' => $entry['title'],
            'reader' => 'Finance officer',
            'note' => $entry['note'],
            'year' => $year,
            'month' => $month,
            'period' => $month ? Carbon::parse($month.'-01')->format('F Y') : (string) $year,
            'sections' => match ($key) {
                'trial-balance' => static::trialBalance($year),
                'balance-sheet' => static::balanceSheet($year),
                'profit-loss' => static::profitLoss($year),
                'cashflow' => static::cashflow($year),
                'general-ledger' => static::ledger($year, $month),
                'ar-ageing' => static::receivables(),
                'ap-ageing' => static::payables(),
                'payroll-summary' => static::payroll($year),
                'finance-audit' => static::audit($year),
            },
            'foot' => 'Counted '.now()->format('d M Y H:i').'. Every figure is read from the registers the other desks write — '
                .'the money register, invoices, orders, payouts and payroll — and none of it is stored on this page.',
        ];
    }

    /** Fiscal years the office can ask for: every year with a line of money, plus this one. */
    public static function years(): array
    {
        $years = DB::table('finance_entries')->distinct()->orderByDesc('fiscal_year')->pluck('fiscal_year')
            ->map(fn ($y) => (int) $y)->all();

        return array_values(collect($years)->push((int) now()->year)->unique()->sortDesc()->values()->all());
    }

    /** The twelve months of a year, for the register filter. */
    public static function months(): array
    {
        return collect(range(1, 12))->mapWithKeys(fn ($m) => [
            sprintf('%02d', $m) => Carbon::create(null, $m, 1)->format('F'),
        ])->all();
    }

    // --------------------------------------------------------------------------------------------- statements

    private static function trialBalance(int $year): array
    {
        $rows = [];

        foreach (FinanceEntry::INCOME_KINDS as $kind => $label) {
            if (($sum = static::sum($year, 'income', $kind)) > 0) {
                $rows[] = [$label, '—', Finance::ksh($sum)];
            }
        }

        foreach (FinanceEntry::EXPENSE_KINDS as $kind => $label) {
            if (($sum = static::sum($year, 'expense', $kind)) > 0) {
                $rows[] = [$label, Finance::ksh($sum), '—'];
            }
        }

        $held = static::sum($year, 'income') - static::sum($year, 'expense');
        $rows[] = ['Money the programme holds', Finance::ksh(max(0, $held)), Finance::ksh(max(0, -$held))];

        $debit = static::sum($year, 'expense') + max(0, $held);
        $credit = static::sum($year, 'income') + max(0, -$held);

        return [
            static::section('The two sides, '.$year, ['Account', 'Money out (debit)', 'Money in (credit)'], $rows,
                'Money in is a credit, money spent is a debit, and the money still held closes the other side — '
                .'so the two columns must always carry the same total.'),
            static::section('Totals', ['Account', 'Money out (debit)', 'Money in (credit)'], [
                ['Both sides', Finance::ksh($debit), Finance::ksh($credit)],
                ['They agree?', $debit === $credit ? 'Yes — the two sides are equal' : 'No — a line is missing its kind', ''],
            ]),
        ];
    }

    private static function balanceSheet(int $year): array
    {
        $asAt = Carbon::create($year, 12, 31)->min(now());
        $held = static::heldThrough($asAt);
        $stock = static::stockValue();
        $owed = Invoice::where('status', 'open')->get()->sum(fn (Invoice $i) => max(0.0, $i->balanceKsh()));

        $payables = static::payableLines();

        return [
            static::section('What the programme owns, as at '.$asAt->format('d M Y'), ['Item', 'Amount'], [
                ['Money held — Paybill, bank and cash', Finance::ksh($held)],
                ['Goods in the store, at what they cost', Finance::ksh($stock)],
                ['Money still owed to the programme', Finance::ksh($owed)],
                ['Everything it owns', Finance::ksh($held + $stock + $owed)],
            ]),
            static::section('What the programme owes', ['Item', 'Amount'], array_merge(
                [
                    ['Goods and services taken, not yet paid', Finance::ksh($payables['orders'])],
                    ['E-Fundi and trainer payouts waiting to be released', Finance::ksh($payables['payouts'])],
                    ['Staff pay not yet settled', Finance::ksh($payables['payroll'])],
                ],
                [['Everything it owes', Finance::ksh(array_sum($payables))]]
            )),
            static::section('The difference', ['Item', 'Amount'], [
                ['Owned less owed — funds left to run the programme', Finance::ksh($held + $stock + $owed - array_sum($payables))],
            ], 'A thin month is not a mistake in these figures: the programme spends grant money it was given to spend.'),
        ];
    }

    private static function profitLoss(int $year): array
    {
        $in = [];
        $out = [];

        foreach (FinanceEntry::INCOME_KINDS as $kind => $label) {
            if (($sum = static::sum($year, 'income', $kind)) > 0) {
                $in[] = [$label, Finance::ksh($sum)];
            }
        }

        foreach (FinanceEntry::EXPENSE_KINDS as $kind => $label) {
            if (($sum = static::sum($year, 'expense', $kind)) > 0) {
                $out[] = [$label, Finance::ksh($sum)];
            }
        }

        $earned = static::sum($year, 'income');
        $spent = static::sum($year, 'expense');

        $budgets = [];
        foreach (BudgetLine::CATEGORIES as $key => $label) {
            $planned = (float) BudgetLine::where('fiscal_year', $year)->where('category', $key)->sum('amount_ksh');
            $used = static::sum($year, 'expense', null, $key);
            if ($planned > 0 || $used > 0) {
                $budgets[] = [$label, Finance::ksh($planned), Finance::ksh($used),
                    Finance::ksh(max(0, $planned - $used)), $used > $planned && $planned > 0 ? 'Over the plan' : 'Within the plan'];
            }
        }

        return [
            static::section('Money in, '.$year, ['What it was', 'Amount'], array_merge($in, [
                ['Everything that came in', Finance::ksh($earned)],
            ])),
            static::section('Money out, '.$year, ['What it was for', 'Amount'], array_merge($out, [
                ['Everything that went out', Finance::ksh($spent)],
            ])),
            static::section('The year’s result', ['Item', 'Amount'], [
                [$spent <= $earned ? 'Money in hand after the year’s spending' : 'Spent beyond the money that arrived',
                    Finance::ksh(abs($earned - $spent))],
            ]),
            static::section('Against the budget lines', ['Budget line', 'Planned', 'Spent', 'Left', 'Where it stands'], $budgets,
                'The plan is what the office set on Programme budget; the spending is only what the register holds.'),
        ];
    }

    private static function cashflow(int $year): array
    {
        $forward = static::heldBefore(Carbon::create($year, 1, 1));
        $rows = [];
        $running = $forward;

        for ($m = 1; $m <= 12; $m++) {
            $month = sprintf('%d-%02d', $year, $m);
            $in = static::sum($year, 'income', null, null, $month);
            $out = static::sum($year, 'expense', null, null, $month);
            if ($in + $out <= 0) {
                continue;
            }
            $running += $in - $out;
            $rows[] = [Carbon::parse($month.'-01')->format('F Y'), Finance::ksh($in), Finance::ksh($out),
                Finance::ksh($in - $out), Finance::ksh($running)];
        }

        $methods = [];
        foreach (FinanceEntry::METHODS as $key => $label) {
            $in = FinanceEntry::where('fiscal_year', $year)->where('direction', 'income')->where('method', $key)->sum('amount_ksh');
            $out = FinanceEntry::where('fiscal_year', $year)->where('direction', 'expense')->where('method', $key)->sum('amount_ksh');
            if ($in + $out > 0) {
                $methods[] = [$label, Finance::ksh((float) $in), Finance::ksh((float) $out)];
            }
        }

        return [
            static::section('Month by month, '.$year, ['Month', 'Money in', 'Money out', 'The month’s effect', 'Money held after it'],
                array_merge(
                    [['Brought forward on 1 Jan '.$year, '—', '—', '—', Finance::ksh($forward)]],
                    $rows
                ),
                'Only the months that moved are listed. The last column is the money the programme held when each month closed.'),
            static::section('How it was settled', ['Means', 'Money in', 'Money out'], $methods,
                'Money marked as not settled yet is still on the register with its date, so nothing is hidden.'),
        ];
    }

    private static function ledger(int $year, ?string $month): array
    {
        $query = FinanceEntry::where('fiscal_year', $year)
            ->when($month, fn ($q) => $q->where('happened_on', 'like', $month.'%'))
            ->orderBy('happened_on')->orderBy('id');

        $running = $month
            ? static::heldBefore(Carbon::parse($month.'-01'))
            : static::heldBefore(Carbon::create($year, 1, 1));

        $rows = [['Brought forward', '', '', '', '—', '—', Finance::ksh($running)]];

        foreach ((clone $query)->cursor() as $entry) {
            /** @var FinanceEntry $entry */
            $running += $entry->signedAmount();
            $rows[] = [
                $entry->happened_on?->format('d M Y') ?? '—',
                $entry->reference,
                $entry->title,
                $entry->kindLabel(),
                $entry->direction === 'income' ? Finance::ksh((float) $entry->amount_ksh) : '—',
                $entry->direction === 'expense' ? Finance::ksh((float) $entry->amount_ksh) : '—',
                Finance::ksh($running),
            ];
        }

        $rows[] = ['Carried forward', '', '', '', '—', '—', Finance::ksh($running)];

        return [static::section(
            'The register'.($month ? ' for '.Carbon::parse($month.'-01')->format('F Y') : ' for '.$year),
            ['Date', 'Reference', 'What it was', 'Account', 'Money in', 'Money out', 'Balance after it'], $rows,
            'This is the whole register read in date order. A line is only ever written once, by the desk that owes it.'
        )];
    }

    private static function receivables(): array
    {
        $rows = [];
        $buckets = static::emptyBuckets('Not yet due');

        foreach (Invoice::where('status', 'open')->orderBy('due_on')->orderBy('issued_on')->get() as $invoice) {
            $balance = $invoice->balanceKsh();
            if ($balance <= 0) {
                continue;
            }
            $since = $invoice->due_on ?? $invoice->issued_on;
            $days = $since ? (int) $since->diffInDays(now(), false) : 0;
            $bucket = static::bucket($days, 'Not yet due');
            $buckets[$bucket]['count']++;
            $buckets[$bucket]['amount'] += $balance;

            $rows[] = [
                $invoice->memberName() ?: $invoice->billedToLabel(),
                $invoice->reference,
                $invoice->issued_on?->format('d M Y') ?? '—',
                $invoice->due_on?->format('d M Y') ?? 'No date set',
                Finance::ksh((float) $invoice->amount_ksh),
                Finance::ksh($invoice->paidKsh()),
                Finance::ksh($balance),
                $bucket,
            ];
        }

        return [
            static::section('Still owed to the programme', ['Billed to', 'Invoice', 'Billed on', 'Due by', 'Billed', 'Paid so far',
                'Still owed', 'How long it has waited'], $rows),
            static::section('Totals by how long it has waited', ['Waiting', 'Invoices', 'Still owed'],
                array_map(fn (array $b) => [$b['label'], $b['count'], Finance::ksh($b['amount'])], array_values($buckets))),
        ];
    }

    private static function payables(): array
    {
        $rows = [];
        $buckets = static::emptyBuckets('Just sent');

        $record = function (string $to, string $what, ?Carbon $since, float $amount) use (&$rows, &$buckets): void {
            $days = $since ? (int) $since->diffInDays(now(), false) : 0;
            $bucket = static::bucket($days, 'Just sent');
            $buckets[$bucket]['count']++;
            $buckets[$bucket]['amount'] += $amount;
            $rows[] = [$to, $what, $since?->format('d M Y') ?? '—', Finance::ksh($amount), $days, $bucket];
        };

        foreach (PurchaseOrder::whereIn('status', ['received', 'matched'])->with('supplier')->get() as $order) {
            $owed = (float) ($order->invoice_amount_ksh ?: $order->amount_ksh);
            $record($order->supplier->name ?: 'A supplier', 'Order '.$order->reference.' — goods received',
                $order->matched_on ?: $order->received_on, $owed);
        }

        foreach (Payout::whereIn('stage', ['queued', 'approved'])->get() as $payout) {
            $record($payout->fundi?->full_name ?: ($payout->trainer_id ? $payout->trainer->personName() : null) ?: 'A payee',
                $payout->streamLabel().' payout '.$payout->reference,
                $payout->updated_at, (float) $payout->net_ksh);
        }

        foreach (PayrollLine::whereIn('status', ['unpaid', 'held'])->whereHas('run',
            fn ($q) => $q->where('status', 'sent'))->get() as $line) {
            $record($line->name, 'Pay for '.$line->run->periodLabel(), $line->run->sent_at, (float) $line->net_ksh);
        }

        usort($rows, fn (array $a, array $b) => $b[4] <=> $a[4]);

        return [
            static::section('Still to be paid', ['Owed to', 'What it is for', 'Waiting since', 'Amount', 'Days waiting',
                'How long it has waited'], $rows,
                'Goods taken and not yet paid, payouts waiting for the executive’s release, and staff pay a month has not settled.'),
            static::section('Totals by how long they have waited', ['Waiting', 'Lines', 'Amount owed'],
                array_map(fn (array $b) => [$b['label'], $b['count'], Finance::ksh($b['amount'])], array_values($buckets))),
        ];
    }

    private static function payroll(int $year): array
    {
        $runs = PayrollRun::where('period', 'like', $year.'%')->orderBy('period')->get();

        $rows = [];
        $sums = array_fill_keys(['basic', 'allowances', 'statutory', 'other_deductions', 'gross', 'deductions', 'net',
            'paid', 'unpaid', 'held'], 0.0);
        $people = 0;

        foreach ($runs as $run) {
            $t = $run->totals();
            $by = DB::table('payroll_lines')->where('payroll_run_id', $run->id)->selectRaw(
                'SUM(basic_ksh) basic, SUM(house_ksh + transport_ksh + other_allowance_ksh) allowances,
                 SUM(paye_ksh + nssf_ksh + shif_ksh + levy_ksh) statutory, SUM(other_deduction_ksh) otherd,
                 SUM(gross_ksh) gross, SUM(deductions_ksh) ded, SUM(net_ksh) net,
                 SUM(CASE WHEN status = \'paid\' THEN net_ksh ELSE 0 END) paid,
                 SUM(CASE WHEN status = \'unpaid\' THEN net_ksh ELSE 0 END) unpaid,
                 SUM(CASE WHEN status = \'held\' THEN net_ksh ELSE 0 END) held'
            )->first();

            $people += $t['people'];
            $sums['basic'] += (float) $by->basic;
            $sums['allowances'] += (float) $by->allowances;
            $sums['statutory'] += (float) $by->statutory;
            $sums['other_deductions'] += (float) $by->otherd;
            $sums['gross'] += (float) $by->gross;
            $sums['deductions'] += (float) $by->ded;
            $sums['net'] += (float) $by->net;
            $sums['paid'] += (float) $by->paid;
            $sums['unpaid'] += (float) $by->unpaid;
            $sums['held'] += (float) $by->held;

            $counted = ! $run->isOpen();
            $shown = fn (float $value, bool $must = true) => $must ? Finance::ksh($value) : '—';

            $rows[] = [$run->periodLabel(), $run->reference, $t['people'], Finance::ksh((float) $by->basic),
                Finance::ksh((float) $by->allowances), Finance::ksh((float) $by->statutory),
                Finance::ksh((float) $by->otherd), $shown((float) $by->gross, $counted),
                $shown((float) $by->ded, $counted), $shown((float) $by->net, $counted),
                $shown((float) $by->paid, $counted), $shown((float) $by->unpaid, $counted),
                $shown((float) $by->held, $counted), $run->statusLabel()];
        }

        return [
            static::section('Each month payroll was run, '.$year, ['Month', 'Ref', 'Staff', 'Basic pay', 'Allowances',
                'PAYE, NSSF, SHIF and levy', 'Other deductions', 'Gross', 'Total deductions', 'Net pay', 'Paid',
                'Still to pay', 'Held back', 'Where the sheet is'], $rows,
                $runs->isEmpty() ? 'No month has been run yet. Human Resources runs a month from the staff file, '
                    .'and Finance settles it person by person.' : null),
            static::section('The year so far', ['Item', 'Amount'], [
                ['Months run', (string) $runs->count()],
                ['Person-months paid for', (string) $people],
                ['Basic pay', Finance::ksh($sums['basic'])],
                ['Allowances added', Finance::ksh($sums['allowances'])],
                ['Gross pay', Finance::ksh($sums['gross'])],
                ['Statutory deductions kept', Finance::ksh($sums['statutory'])],
                ['Other deductions kept', Finance::ksh($sums['other_deductions'])],
                ['Net pay owed to staff', Finance::ksh($sums['net'])],
                ['Settled by Finance', Finance::ksh($sums['paid'])],
                ['Still to pay', Finance::ksh($sums['unpaid'])],
                ['Held back by Human Resources', Finance::ksh($sums['held'])],
            ]),
        ];
    }

    private static function audit(int $year): array
    {
        $models = [FinanceEntry::class, Invoice::class, Payout::class, PurchaseOrder::class, PurchaseRequest::class,
            BudgetLine::class, InventoryItem::class, PayrollRun::class, PayrollLine::class];

        $rows = ActivityLog::whereIn('subject_type', $models)
            ->whereYear('created_at', $year)->orderByDesc('created_at')->limit(300)->get()
            ->map(fn (ActivityLog $log) => [
                $log->created_at->format('d M Y H:i'),
                $log->actor_name ?: 'The system itself',
                ActivityLog::ACTIONS[$log->action] ?? $log->action,
                ActivityLog::recordName($log->subject_type),
                $log->subject_label,
                $log->detail ?: '—',
            ])->all();

        return [static::section('Every change to a money record, '.$year,
            ['When', 'Who', 'Action', 'Kind of record', 'Which record', 'What changed'], $rows,
            'The trail is written by the application itself and never edited: a wrong entry is answered by the next one. '
            .'Salaries, bank details and next of kin are never copied here.')];
    }

    // --------------------------------------------------------------------------------------------- plumbing

    /** Money in less money out, optionally narrowed to one kind, one budget line or one month. */
    private static function sum(int $year, string $direction, ?string $kind = null, ?string $budget = null,
                                ?string $month = null): float
    {
        return (float) FinanceEntry::where('fiscal_year', $year)->where('direction', $direction)
            ->when($kind, fn ($q) => $q->where('kind', $kind))
            ->when($budget, fn ($q) => $q->where('budget_category', $budget))
            ->when($month, fn ($q) => $q->where('happened_on', 'like', $month.'%'))
            ->sum('amount_ksh');
    }

    private static function heldThrough(Carbon $asAt): float
    {
        return (float) FinanceEntry::where('happened_on', '<=', $asAt->toDateString())
            ->selectRaw('COALESCE(SUM(CASE WHEN direction = \'income\' THEN amount_ksh ELSE -amount_ksh END), 0) n')
            ->value('n');
    }

    private static function heldBefore(Carbon $on): float
    {
        return static::heldThrough($on->copy()->subDay());
    }

    private static function stockValue(): float
    {
        return (float) InventoryItem::where('status', 'active')
            ->selectRaw('COALESCE(SUM(qty_on_hand * unit_cost_ksh), 0) n')->value('n');
    }

    /** @return array{orders:float,payouts:float,payroll:float} */
    private static function payableLines(): array
    {
        $orders = (float) PurchaseOrder::whereIn('status', ['received', 'matched'])
            ->selectRaw('COALESCE(SUM(COALESCE(NULLIF(invoice_amount_ksh, 0), amount_ksh)), 0) n')->value('n');

        $payouts = (float) Payout::whereIn('stage', ['queued', 'approved'])->sum('net_ksh');

        $payroll = (float) PayrollLine::whereIn('status', ['unpaid', 'held'])
            ->whereHas('run', fn ($q) => $q->where('status', 'sent'))->sum('net_ksh');

        return ['orders' => $orders, 'payouts' => $payouts, 'payroll' => $payroll];
    }

    private static function emptyBuckets(string $first): array
    {
        return collect(array_merge([$first], self::BUCKETS))->mapWithKeys(fn (string $label) => [
            $label => ['label' => $label, 'count' => 0, 'amount' => 0.0],
        ])->all();
    }

    /** How long a bill has waited. A receivable counts to its due date; a payable counts from the day it fell due. */
    private static function bucket(int $days, string $first): string
    {
        return match (true) {
            $days <= 0 => $first,
            $days <= 30 => self::BUCKETS[0],
            $days <= 60 => self::BUCKETS[1],
            $days <= 90 => self::BUCKETS[2],
            default => self::BUCKETS[3],
        };
    }

    private const BUCKETS = ['Up to 30 days', '31 to 60 days', '61 to 90 days', 'Over 90 days'];

    private static function section(string $heading, array $columns, array $rows, ?string $note = null): array
    {
        return ['heading' => $heading, 'columns' => $columns,
            'rows' => array_values(array_map(fn ($r) => array_values((array) $r), $rows)), 'note' => $note];
    }

    /** @return string the same sections a screen prints, as a file the office can hand over */
    public static function csv(array $sections): string
    {
        return Reports::csv($sections);
    }
}
