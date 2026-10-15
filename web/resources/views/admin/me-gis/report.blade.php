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

@foreach ($report['sections'] as $section)
    <div class="card">
        <h2 class="section-head">{{ $section['heading'] }}</h2>
        @if ($section['note'])<p class="help">{{ $section['note'] }}</p>@endif
        <table class="data">
            <thead>
                <tr>@foreach ($section['columns'] as $col)<th>{{ $col }}</th>@endforeach</tr>
            </thead>
            <tbody>
                @forelse ($section['rows'] as $row)
                    <tr>@foreach ($row as $cell)<td>{{ $cell }}</td>@endforeach</tr>
                @empty
                    <tr><td colspan="{{ count($section['columns']) }}" style="text-align:center;color:var(--muted);padding:16px;">
                        Nothing recorded against this yet.
                    </td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
@endforeach

<p class="hint">{{ $report['foot'] }}</p>
@endsection
