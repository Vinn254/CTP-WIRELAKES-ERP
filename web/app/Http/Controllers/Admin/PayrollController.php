<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PayrollLine;
use App\Models\PayrollRun;
use App\Support\Deductions;
use App\Support\Payroll;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * The payroll sheet, prepared by Human Resources. A month is drawn from the staff file, the allowances and every
 * deduction are worked out line by line, and the sheet then goes to Finance for payment. A month is run once and
 * corrected in place; a person who should not be paid this month is held with a reason, never removed from the sheet.
 */
class PayrollController extends Controller
{
    public function index(Request $request)
    {
        $status = in_array($request->query('status'), array_keys(PayrollRun::STATUSES), true)
            ? $request->query('status') : 'all';

        $runs = PayrollRun::query()
            ->when($status !== 'all', fn ($q) => $q->where('status', $status))
            ->orderByDesc('period')
            ->get()
            ->map(fn (PayrollRun $run) => ['run' => $run, 'totals' => $run->totals()]);

        $counts = [];
        foreach (array_keys(PayrollRun::STATUSES) as $key) {
            $counts[$key] = PayrollRun::where('status', $key)->count();
        }

        return view('admin.payroll.index', [
            'runs' => $runs,
            'status' => $status,
            'counts' => $counts,
            'statuses' => PayrollRun::STATUSES,
            'periods' => Payroll::periods(),
            'monthsRun' => PayrollRun::count(),
            'waiting' => PayrollRun::where('status', 'prepared')->count()
                + PayrollRun::where('status', 'returned')->count(),
            'thisMonth' => now()->startOfMonth()->toDateString(),
            'alreadyRun' => PayrollRun::whereIn('period', array_keys(Payroll::periods()))
                ->pluck('period')->map->toDateString()->all(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'period' => 'required|date',
            'note' => 'nullable|string|max:500',
        ], [], ['period' => 'Month']);

        $run = Payroll::open($data['period'], $data['note'] ?? null, $request->user());

        return redirect()->route('admin.payroll.show', $run)
            ->with('status', $run->reference.' drawn for '.$run->periodLabel().' with '
                .$run->lines()->count().' staff on the sheet. Set the allowances and deductions, then send it to Finance.');
    }

    public function show(PayrollRun $run)
    {
        return view('admin.payroll.run', Payroll::desk($run) + [
            'self' => 'admin.payroll.show',
            'canEdit' => $run->isOpen(),
            'canSend' => $run->isOpen() && Payroll::hr(),
        ]);
    }

    public function add(Request $request, PayrollRun $run)
    {
        $request->validate(['staff_record_id' => 'required|integer|exists:staff_records,id']);

        return back()->with('status', Payroll::add($run, (int) $request->input('staff_record_id'), $request));
    }

    public function line(Request $request, PayrollLine $line)
    {
        return back()->with('status', Payroll::figures($line, $request));
    }

    public function hold(Request $request, PayrollLine $line)
    {
        $data = $request->validate([
            'hold_reason' => 'required|string|max:300',
        ], ['hold_reason.required' => 'Say why the person is held back — the reason stays on the sheet.'],
            ['hold_reason' => 'Why they are held back']);

        return back()->with('status', Payroll::hold($line, $data['hold_reason']));
    }

    public function releaseHold(PayrollLine $line)
    {
        return back()->with('status', Payroll::releaseHold($line));
    }

    public function send(Request $request, PayrollRun $run)
    {
        return back()->with('status', Payroll::send($run, $request->user()));
    }

    /** The rates the statutory deductions are worked at, which Human Resources keeps. */
    public function rates()
    {
        abort_unless(Payroll::hr(), 403, 'The deduction rates are kept by the Human Resources desk.');

        return view('admin.payroll.rates', [
            'rates' => Deductions::rates(),
            'labels' => Deductions::labels(),
            'defaults' => Deductions::defaults(),
        ]);
    }

    public function saveRates(Request $request)
    {
        Deductions::save($request, $request->user());

        return redirect()->route('admin.payroll.rates')
            ->with('status', 'The deduction rates are saved. Any sheet still being prepared can be worked out again on them.');
    }

    /** Every line on the month, worked at the rates in force. The office still corrects a line by hand. */
    public function statutory(Request $request, PayrollRun $run)
    {
        [$done, $skipped] = Deductions::applyRun($run, $request->user());

        return back()->with('status', $done.' '.Str::plural('line', $done).' on '.$run->periodLabel()
            .' now '.($done === 1 ? 'carries' : 'carry').' PAYE, NSSF, SHIF and the housing levy at the rates Human Resources set.'
            .($skipped ? ' Left as typed: '.implode(', ', $skipped).' — the figures would take more than their pay.' : ''));
    }
}
