<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Support\Impact;
use App\Support\Reports;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Module 12 — the reports , one for each reader. A report is never saved and never edited: it is the registers
 * read again at the moment it is asked for, so the same page can be handed out as a file and say the same thing.
 */
class ReportController extends Controller
{
    public function index(Request $request)
    {
        return view('admin.me-gis.reports', [
            'readers' => Reports::READERS,
            'county' => trim((string) $request->query('county')),
            'counties' => Impact::counties(),
        ]);
    }

    public function show(Request $request, string $report)
    {
        abort_unless(array_key_exists($report, Reports::READERS), 404, 'No such report.');

        return view('admin.me-gis.report', [
            'report' => Reports::build($report, $this->county($request)),
            'counties' => Impact::counties(),
            'county' => $this->county($request),
        ]);
    }

    /**  data export — the same figures the page prints, as a CSV download. */
    public function csv(Request $request, string $report): StreamedResponse
    {
        abort_unless(array_key_exists($report, Reports::READERS), 404, 'No such report.');

        $built = Reports::build($report, $this->county($request));
        $place = str($this->county($request) ?? '')->slug();
        $name = $report.($place->isEmpty() ? '' : '-'.$place).'-'.now()->format('Y-m-d').'.csv';

        return response()->streamDownload(
            fn () => print Reports::csv($built['sections']),
            $name,
            ['Content-Type' => 'text/csv; charset=UTF-8']
        );
    }

    private function county(Request $request): ?string
    {
        $county = trim((string) $request->query('county'));

        return $county !== '' ? $county : null;
    }
}
