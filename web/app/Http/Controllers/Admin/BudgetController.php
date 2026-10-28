<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BudgetLine;
use App\Models\FinanceEntry;
use App\Models\Programme;
use App\Support\Finance;
use Illuminate\Http\Request;

/**
 * Module 11 — the programme budget. One line per category per financial year, with what has actually been
 * charged against it read off the ledger. The office sets the amounts; the categories stay the closed list the
 * programme is planned around so the total always means the same thing year to year.
 */
class BudgetController extends Controller
{
    public function index(Request $request)
    {
        $years = BudgetLine::years()->union(collect([now()->year]))->unique()->sortDesc()->values();
        $year = (int) $request->input('year', $years->first() ?: now()->year);
        $plan = BudgetLine::planFor($year);

        $planned = (float) $plan->sum('amount');
        $spent = (float) $plan->sum('spent');

        return view('admin.finance.budget', [
            'year' => $year,
            'years' => $years,
            'plan' => $plan,
            'planned' => $planned,
            'spent' => $spent,
            'left' => $planned - $spent,
            'income' => FinanceEntry::earned($year),
            'categories' => BudgetLine::categories(),
            'incomes' => FinanceEntry::where('fiscal_year', $year)->where('direction', 'income')
                ->selectRaw('kind, SUM(amount_ksh) total')->groupBy('kind')->pluck('total', 'kind'),
            'expenseKinds' => FinanceEntry::where('fiscal_year', $year)->where('direction', 'expense')
                ->selectRaw('kind, SUM(amount_ksh) total')->groupBy('kind')->pluck('total', 'kind'),
        ]);
    }

    /**
     * The office takes up a new programme whenever the work needs one. It carries no money, so it is not a
     * signed-off decision — it only puts its name on the plan and on the dropdowns every money line is filled from.
     */
    public function storeProgramme(Request $request)
    {
        $programme = Programme::create(Programme::dataFrom($request) + ['added_by_user' => $request->user()->id]);
        Programme::forgetCache();

        return back()->with('status', $programme->label.' is now on the plan. Set its budget when the money is allocated.');
    }

    /**
     * Setting a budget line is a money decision, so it is signed off by a programme executive — the same rule as
     * releasing a payment. Staff can still record what came in and went out.
     */
    public function save(Request $request)
    {
        Finance::requireRelease('Setting the budget for '.($request->input('category') ?: 'a category'));

        $data = BudgetLine::dataFrom($request);
        $year = (int) ($data['fiscal_year'] ?: now()->year);

        $line = BudgetLine::firstOrNew(['fiscal_year' => $year, 'category' => $data['category']]);
        $fresh = ! $line->exists;

        $line->fill(['amount_ksh' => $data['amount_ksh'], 'note' => $data['note'] ?? null,
            'set_by_user' => auth()->id()])->save();

        $message = BudgetLine::categories()[$data['category']].' for '.$year.' is set at '
            .Finance::ksh((float) $data['amount_ksh']).'.';
        $message .= $fresh
            ? ' That category was not on the plan before, so a new line was opened for it.'
            : ' Its line was updated — money already charged against it stays on the ledger.';

        return back()->with('status', $message);
    }
}
