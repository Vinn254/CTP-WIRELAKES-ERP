@extends('layouts.admin')
@section('title', 'Reports')

@section('content')
<h1>Reports, by who reads them</h1>
<p class="page-lede">Each role's report, built live from the registers and exportable as CSV.</p>

@if ($county !== '')
    <p class="help">Reports will be counted for <b>{{ $county }}</b>.
        <a href="{{ route('admin.reports.index') }}">Every county instead</a></p>
@else
    <form method="GET" action="{{ route('admin.reports.index') }}" class="filter-bar">
        <div>
            <label for="county">Count everything for one county (optional)</label>
            <select name="county" id="county">
                <option value="">Every county</option>
                @foreach (array_keys($counties) as $c)
                    <option value="{{ $c }}" @selected($county === $c)>{{ $c }}</option>
                @endforeach
            </select>
        </div>
        <button class="btn btn-sm btn-outline" type="submit">Choose</button>
    </form>
@endif

<table class="data">
    <thead><tr><th>Reader</th><th>Report</th><th>What it answers</th><th></th></tr></thead>
    <tbody>
        @foreach ($readers as $key => $r)
            <tr>
                <td><b>{{ $r['reader'] }}</b></td>
                <td>{{ $r['title'] }}</td>
                <td><span class="hint">{{ $r['note'] }}</span></td>
                <td>
                    @if (! empty($r['pending']))
                        <span class="tag">not open yet</span>
                    @elseif ($key === 'member')
                        <a class="btn btn-sm btn-ghost" href="{{ route('portal.dashboard') }}">Member portal</a>
                    @else
                        <a class="btn btn-sm btn-outline"
                           href="{{ route('admin.reports.show', ['report' => $key, 'county' => $county !== '' ? $county : null]) }}">Open</a>
                    @endif
                </td>
            </tr>
        @endforeach
    </tbody>
</table>

<p class="hint">
    A county coordinator's report needs a county: pick one above, or open
    <a href="{{ route('admin.reports.show', ['report' => 'county']) }}">the county list</a> to see every county at once.
    The indicators behind these reports are on the <a href="{{ route('admin.impacts.index') }}">impact dashboard</a>.
</p>
@endsection
