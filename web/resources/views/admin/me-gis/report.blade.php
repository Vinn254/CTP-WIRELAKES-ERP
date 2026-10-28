@extends('layouts.admin')
@section('title', $report['title'])

@section('content')
<p class="back"><a href="{{ route('admin.reports.index') }}">&larr; Reports</a></p>
<h1>{{ $report['title'] }}@if ($report['county']) — {{ $report['county'] }} @endif</h1>
<p class="sub" style="color:var(--muted);margin:6px 0 12px;">
    Written for the <b>{{ $report['reader'] }}</b>. {{ $report['note'] }}
</p>

<div class="report-bar">
    <form method="GET" action="{{ route('admin.reports.show', $report['key']) }}" class="mini-form">
        <select name="county" aria-label="County">
            <option value="">Every county</option>
            @foreach (array_keys($counties) as $c)
                <option value="{{ $c }}" @selected($report['county'] === $c)>{{ $c }}</option>
            @endforeach
        </select>
        <button class="btn btn-sm btn-outline" type="submit">Recount</button>
    </form>
    <a class="btn btn-sm btn-ghost" href="{{ route('admin.reports.export', ['report' => $report['key'], 'county' => $report['county']]) }}">
        Export as CSV
    </a>
    <button class="btn btn-sm btn-ghost" type="button" onclick="window.print()">Print</button>
</div>

@include('partials.report-sections')

<p class="hint">{{ $report['foot'] }}</p>
@endsection
