@extends('layouts.admin')
@section('title', 'Payroll')

@php
    use App\Models\PayrollRun;
    use App\Support\Finance;

    $tone = ['prepared' => 'pending_review', 'sent' => 'pending_payment', 'returned' => 'suspended', 'paid' => 'active'];
@endphp

@section('content')
<h1>Payroll</h1>
<p class="page-lede">Run each month's pay, then send the sheet to Finance.</p>

<div class="stat-row">
    <div class="stat"><small>Months run</small><b>{{ $monthsRun }}</b></div>
    <div class="stat"><small>Being prepared</small><b>{{ $waiting }}</b></div>
    <div class="stat"><small>With Finance</small><b>{{ $counts['sent'] ?? 0 }}</b></div>
    <div class="stat"><small>Paid months</small><b>{{ $counts['paid'] ?? 0 }}</b></div>
</div>

<div class="head-actions" style="margin:0 0 18px">
    <button class="btn btn-primary" type="button" data-sheet="run">Run a month's payroll</button>
</div>

@include('partials.sheet-start', ['name' => 'run', 'title' => 'Run a month\'s payroll',
    'sub' => 'Everyone on the staff file is drawn onto the sheet at the pay on their record.'])
<form method="POST" action="{{ route('admin.payroll.store') }}" class="fee-form"
      data-confirm="Draw this month's payroll from the staff file?" data-title="Run payroll">
    @csrf
    <input type="hidden" name="_sheet" value="run">
    <div class="field">
        <label>Month <span class="req">*</span></label>
        <select name="period" required>
            @foreach ($periods as $date => $label)
                <option value="{{ $date }}" @selected($date === $thisMonth) @disabled(in_array($date, $alreadyRun, true))>
                    {{ $label }}{{ in_array($date, $alreadyRun, true) ? ' — already run' : '' }}
                </option>
            @endforeach
        </select>
        <div class="help">A month is run once. Anything wrong is corrected on that sheet.</div>
    </div>
    <div class="field">
        <label>Note on this month</label>
        <input type="text" name="note" maxlength="500" value="{{ old('note') }}"
               placeholder="e.g. Two new officers joined on the 1st">
    </div>
    <div class="sheet-actions">
        <button class="btn btn-ghost" type="button" data-close-sheet>Cancel</button>
        <button class="btn btn-primary" type="submit">Draw the sheet</button>
    </div>
</form>
@include('partials.sheet-end')

<form method="GET" action="{{ route('admin.payroll.index') }}" class="filter-bar">
    <div class="field">
        <label>Status</label>
        <select name="status">
            <option value="all">Every month</option>
            @foreach ($statuses as $key => $label)
                <option value="{{ $key }}" @selected($status === $key)>{{ $label }} ({{ $counts[$key] ?? 0 }})</option>
            @endforeach
        </select>
    </div>
    <button class="btn btn-sm btn-outline" type="submit">Filter</button>
</form>

@if ($runs->isEmpty())
    <div class="card">
        <p class="span-note">No month matches this filter. Run a month's payroll and the staff on the directory come
            onto the sheet with their pay.</p>
    </div>
@else
    <div class="table-scroll">
        <table class="data">
            <thead>
                <tr>
                    <th>Month</th><th>Ref</th><th class="num">Staff</th><th class="num">Gross</th>
                    <th class="num">Allowances</th><th class="num">Deductions</th><th class="num">Net</th>
                    <th class="num">To pay</th><th>Status</th><th></th>
                </tr>
            </thead>
            <tbody>
                @foreach ($runs as $row)
                    @php $run = $row['run']; $t = $row['totals']; @endphp
                    <tr>
                        <td><b>{{ $run->periodLabel() }}</b></td>
                        <td class="nowrap">{{ $run->reference }}</td>
                        <td class="num">{{ $t['people'] }}</td>
                        <td class="num">{{ Finance::ksh($t['gross']) }}</td>
                        <td class="num">{{ Finance::ksh($t['allowances']) }}</td>
                        <td class="num">{{ Finance::ksh($t['deductions']) }}</td>
                        <td class="num">{{ Finance::ksh($t['net']) }}</td>
                        <td class="num">{{ $t['unpaid_count'] ? Finance::ksh($t['unpaid']) : '—' }}</td>
                        <td><span class="status {{ $tone[$run->status] ?? 'closed' }}">{{ $run->statusLabel() }}</span></td>
                        <td class="actions"><a class="btn btn-sm btn-outline"
                            href="{{ route('admin.payroll.show', $run) }}">Open</a></td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@endif

<p class="hint">Paybill <b>{{ Finance::paybill()['number'] }}</b>, account number
    <b>{{ Finance::paybill()['account'] }}</b>.</p>
@endsection
