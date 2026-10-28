@extends('layouts.admin')
@section('title', 'Staff payroll')

@php
    use App\Models\PayrollRun;
    use App\Support\Finance;

    $tone = ['prepared' => 'pending_review', 'sent' => 'pending_payment', 'returned' => 'suspended', 'paid' => 'active'];
@endphp

@section('content')
<h1>Staff payroll</h1>
<p class="page-lede">The sheets Human Resources has sent for payment, settled person by person.</p>

<div class="stat-row">
    <div class="stat"><small>To pay now</small><b>{{ Finance::ksh($toPay) }}</b></div>
    <div class="stat"><small>Waiting on this desk</small><b>{{ $counts['sent'] ?? 0 }}</b></div>
    <div class="stat"><small>Paid this year</small><b>{{ Finance::ksh($paidYear) }}</b></div>
    <div class="stat"><small>Months paid</small><b>{{ $counts['paid'] ?? 0 }}</b></div>
</div>

<form method="GET" action="{{ route('admin.staffpay.index') }}" class="filter-bar">
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
        <p class="span-note">Nothing is with Finance for payment. Human Resources sends a month's sheet here once
            every line carries its allowances and deductions.</p>
    </div>
@else
    <div class="table-scroll">
        <table class="data">
            <thead>
                <tr>
                    <th>Month</th><th>Ref</th><th class="num">Staff to pay</th><th class="num">Gross</th>
                    <th class="num">Deductions</th><th class="num">Net</th><th class="num">Still to pay</th>
                    <th>Status</th><th></th>
                </tr>
            </thead>
            <tbody>
                @foreach ($runs as $row)
                    @php $run = $row['run']; $t = $row['totals']; @endphp
                    <tr>
                        <td><b>{{ $run->periodLabel() }}</b>
                            <small>{{ $run->sent_at?->format('d M Y') ?: 'not yet sent' }}</small></td>
                        <td class="nowrap">{{ $run->reference }}</td>
                        <td class="num">{{ $t['unpaid_count'] }} of {{ $t['people'] }}</td>
                        <td class="num">{{ Finance::ksh($t['gross']) }}</td>
                        <td class="num">{{ Finance::ksh($t['deductions']) }}</td>
                        <td class="num">{{ Finance::ksh($t['net']) }}</td>
                        <td class="num">{{ $t['unpaid_count'] ? Finance::ksh($t['unpaid']) : '—' }}</td>
                        <td><span class="status {{ $tone[$run->status] ?? 'closed' }}">{{ $run->statusLabel() }}</span></td>
                        <td class="actions"><a class="btn btn-sm btn-outline"
                            href="{{ route('admin.staffpay.show', $run) }}">Pay</a></td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@endif

<p class="hint">Each settlement posts its own line to the money register, so pay is never paid twice.</p>
@endsection
