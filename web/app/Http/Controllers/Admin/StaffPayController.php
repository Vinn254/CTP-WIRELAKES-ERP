<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PayrollLine;
use App\Models\PayrollRun;
use App\Support\Payroll;
use Illuminate\Http\Request;

/**
 * The staff to be paid. Human Resources works the month out and sends it here; Finance sees each person's net,
 * settles them one by one — and every settlement posts its own line to the money register. A sheet whose figures
 * are wrong is returned to Human Resources rather than paid, and nothing on it is deleted.
 */
class StaffPayController extends Controller
{
    public function index(Request $request)
    {
        $status = in_array($request->query('status'), array_keys(PayrollRun::STATUSES), true)
            ? $request->query('status') : 'sent';

        $counts = [];
        foreach (array_keys(PayrollRun::STATUSES) as $key) {
            $counts[$key] = PayrollRun::where('status', $key)->count();
        }

        return view('admin.payroll.paying', [
            'runs' => PayrollRun::query()
                ->when($status !== 'all', fn ($q) => $q->where('status', $status))
                ->orderByDesc('period')
                ->get()
                ->map(fn (PayrollRun $run) => ['run' => $run, 'totals' => $run->totals()]),
            'status' => $status,
            'counts' => $counts,
            'statuses' => PayrollRun::STATUSES,
            'toPay' => (float) PayrollRun::where('status', 'sent')
                ->get()->sum(fn (PayrollRun $run) => $run->totals()['unpaid']),
            'paidYear' => (float) PayrollLine::where('status', 'paid')
                ->whereHas('run', fn ($q) => $q->where('period', '>=', now()->startOfYear()->toDateString()))
                ->sum('net_ksh'),
        ]);
    }

    public function show(PayrollRun $run)
    {
        return view('admin.payroll.run', Payroll::desk($run) + [
            'self' => 'admin.staffpay.show',
            'canEdit' => false,
            'canSend' => false,
            'canPay' => $run->status === 'sent',
            'canReturn' => $run->status === 'sent',
        ]);
    }

    public function pay(Request $request, PayrollLine $line)
    {
        return back()->with('status', Payroll::pay($line, $request));
    }

    public function returnTo(Request $request, PayrollRun $run)
    {
        $data = $request->validate([
            'reason' => 'required|string|max:500',
        ], ['reason.required' => 'Say what Finance needs corrected — the sheet goes back to Human Resources with it.'],
            ['reason' => 'What must be corrected']);

        return back()->with('status', Payroll::returnTo($run, $data['reason'], $request->user()));
    }
}
