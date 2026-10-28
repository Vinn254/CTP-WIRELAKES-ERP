<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Support\Statements;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * The money statements. Finance asks for one, the desk reads the registers again in the order the statement
 * wants, and the same figures go to the screen, the CSV and the printer. A statement is never saved or edited:
 * the day after, it is counted fresh from what the other desks wrote.
 */
class StatementController extends Controller
{
    public function index(Request $request)
    {
        return view('admin.finance.statements', [
            'statements' => Statements::LIST,
            'year' => $this->year($request),
            'years' => Statements::years(),
        ]);
    }

    public function show(Request $request, string $statement)
    {
        return view('admin.finance.statement', [
            'report' => Statements::build($this->key($statement), $this->year($request), $this->month($request)),
            'year' => $this->year($request),
            'years' => Statements::years(),
            'month' => $this->month($request),
            'months' => Statements::months(),
        ]);
    }

    /** The same statement the page prints, as a file the office hands over. */
    public function csv(Request $request, string $statement): StreamedResponse
    {
        $key = $this->key($statement);
        $built = Statements::build($key, $this->year($request), $this->month($request));
        $name = str_replace('-', '_', $key).'-'.$built['period'].'.csv';

        return response()->streamDownload(
            fn () => print Statements::csv($built['sections']),
            $name,
            ['Content-Type' => 'text/csv; charset=UTF-8']
        );
    }

    private function key(string $statement): string
    {
        abort_unless(array_key_exists($statement, Statements::LIST), 404, 'No such statement.');

        return $statement;
    }

    private function year(Request $request): int
    {
        $year = (int) $request->query('year');

        return in_array($year, Statements::years(), true) ? $year : (int) now()->year;
    }

    private function month(Request $request): ?string
    {
        $month = trim((string) $request->query('month'));

        return preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $month) ? $month : null;
    }
}
