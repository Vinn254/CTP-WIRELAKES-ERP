@extends('layouts.admin')
@section('title', 'Programme budget')

@section('content')
@include('partials.errors')
@php use App\Support\Finance; @endphp
<h1>Programme budget — {{ $year }}</h1>
<p class="page-lede">Set and check the yearly budget for each programme category.</p>

<div class="stat-row">
    <div class="stat"><small>Budgeted {{ $year }}</small><b>{{ Finance::ksh($planned) }}</b></div>
    <div class="stat"><small>Charged against it</small><b>{{ Finance::ksh($spent) }}</b></div>
    <div class="stat"><small>Left to spend</small><b>{{ Finance::ksh($left) }}</b></div>
    <div class="stat"><small>Money in this year</small><b>{{ Finance::ksh($income) }}</b></div>
    <div class="stat"><small>Categories over budget</small><b>{{ $plan->filter(fn ($r) => $r['amount'] > 0 && $r['spent'] > $r['amount'])->count() }}</b></div>
</div>

<form method="GET" action="{{ route('admin.budget.index') }}" class="filter-bar inline-filter">
    <label class="field">
        <span>Budget year</span>
        <select name="year" onchange="this.form.submit()">
            @foreach ($years as $y)
                <option value="{{ $y }}" @selected((int) $y === $year)>{{ $y }}@if ($y === $year) ({{ $plan->count() }})@endif</option>
            @endforeach
        </select>
    </label>
    <noscript><button class="btn btn-sm btn-outline" type="submit">Apply</button></noscript>
</form>

<table class="data">
    <thead>
        <tr>
            <th>Budget category</th><th>Budgeted</th><th>Charged</th><th>Left</th><th>Used</th>
            <th>Set by</th><th>On the plan</th><th></th>
        </tr>
    </thead>
    <tbody>
        @foreach ($plan as $row)
            @php
                $used = $row['amount'] > 0 ? round($row['spent'] / $row['amount'] * 100) : ($row['spent'] > 0 ? 100 : 0);
                $over = $row['amount'] > 0 && $row['spent'] > $row['amount'];
            @endphp
            <tr>
                <td><b>{{ $row['label'] }}</b>
                    @if ($row['line']?->note)<div class="hint">{{ $row['line']->note }}</div>@endif
                </td>
                <td>{{ Finance::ksh($row['amount']) }}</td>
                <td>
                    {{ Finance::ksh($row['spent']) }}
                    @if ($row['spent'] > 0)
                        <div class="hint"><a href="{{ route('admin.ledger.index', ['f' => 'expense', 'budget' => $row['category'], 'year' => $year]) }}">See the lines</a></div>
                    @endif
                </td>
                <td class="{{ $over ? 'flag-bad' : 'flag-good' }}">{{ Finance::ksh($row['amount'] - $row['spent']) }}</td>
                <td>
                    <div class="bar"><i style="width: {{ min(100, $used) }}%"></i></div>
                    <span class="hint">{{ $used }}% used</span>
                </td>
                <td><span class="hint">{{ $row['line']?->setter?->name ?? 'not set on this screen' }}</span></td>
                <td>
                    @if ($row['amount'] > 0 && ! $over)<span class="status active">Funded</span>
                    @elseif ($over)<span class="status closed">Over budget</span>
                    @else<span class="status pending_review">No money set</span>@endif
                </td>
                <td class="actions">
                    <details class="need-form">
                        <summary>{{ $row['line'] ? 'Change the amount' : 'Set a budget' }}</summary>
                        <form method="POST" action="{{ route('admin.budget.save') }}" class="fee-form"
                              data-confirm="Set {{ $row['label'] }} for {{ $year }}?" data-title="Set budget">
                            @csrf
                            <input type="hidden" name="fiscal_year" value="{{ $year }}">
                            <input type="hidden" name="category" value="{{ $row['category'] }}">
                            <div class="field">
                                <label>Amount for {{ $year }} (KSH) <span class="req">*</span></label>
                                <input type="number" name="amount_ksh" required min="0" step="1"
                                       value="{{ (int) $row['amount'] }}">
                            </div>
                            <div class="field" style="margin-top:10px;">
                                <label>What this line is for</label>
                                <input type="text" name="note" maxlength="1000"
                                       value="{{ old('note', $row['line']?->note) }}"
                                       placeholder="e.g. Tools and materials for the 12 lab projects.">
                            </div>
                            <button class="btn btn-lime btn-block" type="submit" style="margin-top:10px;">
                                {{ auth()->user()->role === 'superadmin' ? 'Set it' : 'Only an executive account can set this' }}
                            </button>
                            @if (auth()->user()->role !== 'superadmin')
                                <div class="hint" style="margin-top:8px;">Staff prepare the plan; a programme executive signs the budget lines.</div>
                            @endif
                        </form>
                    </details>
                </td>
            </tr>
        @endforeach
        <tr>
            <td><b>Total for {{ $year }}</b></td>
            <td><b>{{ Finance::ksh($planned) }}</b></td>
            <td><b>{{ Finance::ksh($spent) }}</b></td>
            <td><b>{{ Finance::ksh($left) }}</b></td>
            <td colspan="3" class="hint">The plan the programme is run on —.</td>
            <td></td>
        </tr>
    </tbody>
</table>

<div class="two-col">
    <div class="card">
        <h3>Money in this year, by what it is</h3>
        @forelse ($incomes as $kind => $total)
            <div class="row-line">
                <b>{{ \App\Models\FinanceEntry::KINDS[$kind] ?? $kind }}</b>
                <span class="hint">{{ Finance::ksh((float) $total) }}</span>
            </div>
        @empty
            <p class="hint">Nothing has been recorded as received yet. Member payments, hirer payments, donor funds
                and sales all come onto the <a href="{{ route('admin.ledger.index', ['year' => $year]) }}">money register</a>.</p>
        @endforelse
    </div>
    <div class="card">
        <h3>Money out this year, by what it was</h3>
        @forelse ($expenseKinds as $kind => $total)
            <div class="row-line">
                <b>{{ \App\Models\FinanceEntry::KINDS[$kind] ?? $kind }}</b>
                <span class="hint">{{ Finance::ksh((float) $total) }}</span>
            </div>
        @empty
            <p class="hint">Nothing has been charged yet. Purchases, payouts, materials and running costs post their
                own lines as they are released.</p>
        @endforelse
        <p class="hint">A category is only ever charged with money that belongs to it: a trainer is paid from the
            training line, never from a project build.</p>
    </div>
</div>
@endsection
