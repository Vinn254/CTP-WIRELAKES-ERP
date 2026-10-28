@extends('layouts.admin')
@section('title', $report['title'].' '.$report['period'])

@php
    $ledger = $report['key'] === 'general-ledger';
    $self = 'admin.statements.show';
@endphp

@section('content')
<div class="head-row">
    <div>
        <h1>{{ $report['title'] }} — {{ $report['period'] }}</h1>
        <p class="ah-meta">
            <span><b>{{ $report['reader'] }}</b></span>
            <span>{{ count($report['sections']) }} {{ Str::plural('table', count($report['sections'])) }}</span>
            <span>Counted from the registers</span>
        </p>
    </div>
    <div class="head-actions">
        <a class="btn btn-sm btn-outline" href="{{ route('admin.statements.index', ['year' => $year]) }}">All statements</a>
        <a class="btn btn-sm btn-ghost"
           href="{{ route('admin.statements.export', ['statement' => $report['key'], 'year' => $year, 'month' => $month]) }}">Export as CSV</a>
        <button class="btn btn-sm btn-ghost" type="button" onclick="window.print()">Print</button>
    </div>
</div>

<p class="page-lede">{{ $report['note'] }}</p>

<form method="GET" action="{{ route($self, $report['key']) }}" class="filter-bar">
    <div class="field">
        <label>Year</label>
        <select name="year">
            @foreach ($years as $y)
                <option value="{{ $y }}" @selected($year === $y)>{{ $y }}</option>
            @endforeach
        </select>
    </div>
    @if ($ledger)
        <div class="field">
            <label>Month</label>
            <select name="month">
                <option value="">Every month of {{ $year }}</option>
                @foreach ($months as $mm => $label)
                    <option value="{{ $year }}-{{ $mm }}" @selected($month === $year.'-'.$mm)>{{ $label }}</option>
                @endforeach
            </select>
        </div>
    @endif
    <button class="btn btn-sm btn-outline" type="submit">Recount</button>
</form>

@include('partials.report-sections')

<p class="hint">{{ $report['foot'] }}</p>
@endsection
