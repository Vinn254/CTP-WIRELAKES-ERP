<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BudgetLine;
use App\Models\Cohort;
use App\Models\FinanceEntry;
use App\Models\Project;
use Illuminate\Http\Request;

/**
 * Module 11 — the ledger. Every shilling in and out is one line here. Lines posted by the system (a paid
 * purchase order, a released payout) say where they came from and are not retyped; a line the office got wrong is
 * corrected with an opposite line rather than deleted, so the mistake stays visible.
 */
class LedgerController extends Controller
{
    public function index(Request $request)
    {
        $direction = $request->query('f', 'all');
        $kind = $request->query('kind', 'all');
        $budget = $request->query('budget', 'all');
        $year = (int) $request->query('year', now()->year);
        $search = trim((string) $request->query('q'));

        $base = fn () => FinanceEntry::where('fiscal_year', $year);

        $entries = FinanceEntry::where('fiscal_year', $year)
            ->when($direction !== 'all', fn ($q) => $q->where('direction', $direction))
            ->when($kind !== 'all', fn ($q) => $q->where('kind', $kind))
            ->when($budget !== 'all', fn ($q) => $q->where('budget_category', $budget))
            ->when($search !== '', function ($q) use ($search) {
                $q->where(fn ($w) => $w->where('reference', 'like', "%{$search}%")
                    ->orWhere('title', 'like', "%{$search}%")
                    ->orWhere('note', 'like', "%{$search}%"));
            })
            ->with(['project:id,title', 'cohort:id,reference', 'fundi:id,full_name,ef_code', 'recorder:id,name'])
            ->orderByDesc('happened_on')->orderByDesc('id')
            ->paginate(30)->withQueryString();

        $income = (float) $base()->where('direction', 'income')->sum('amount_ksh');
        $expense = (float) $base()->where('direction', 'expense')->sum('amount_ksh');

        return view('admin.finance.ledger', [
            'entries' => $entries,
            'year' => $year,
            'years' => FinanceEntry::select('fiscal_year')->distinct()->orderByDesc('fiscal_year')->pluck('fiscal_year')
                ->concat(BudgetLine::years())->unique()->values(),
            'direction' => $direction,
            'kind' => $kind,
            'budget' => $budget,
            'search' => $search,
            'income' => $income,
            'expense' => $expense,
            'balance' => $income - $expense,
            'planned' => (float) BudgetLine::planFor($year)->sum('amount'),
            'counts' => [
                'all' => $base()->count(),
                'income' => $base()->where('direction', 'income')->count(),
                'expense' => $base()->where('direction', 'expense')->count(),
            ],
            'kinds' => FinanceEntry::KINDS,
            'categories' => BudgetLine::categories(),
            'methods' => FinanceEntry::METHODS,
            'projects' => Project::orderByDesc('id')->limit(200)->get(['id', 'title', 'reference']),
            'cohorts' => Cohort::orderByDesc('id')->limit(200)->get(['id', 'reference']),
        ]);
    }

    public function store(Request $request)
    {
        $data = FinanceEntry::dataFrom($request);

        $entry = FinanceEntry::create([
            ...$data,
            'reference' => FinanceEntry::nextReference($data['happened_on']),
            'fiscal_year' => (int) substr($data['happened_on'], 0, 4),
            'recorded_by_user' => auth()->id(),
        ]);

        return back()->with('status', $entry->reference.' recorded: '.$entry->kindLabel().' of '
            .$entry->amountLabel().' on '.$entry->happened_on->format('d M Y').' by '
            .($entry->recorder->name ?? 'the office').' — '
            .($entry->direction === 'income' ? 'money in.' : 'charged to '.$entry->budgetLabel().'.'));
    }
}
