@extends('layouts.admin')
@section('title', 'Impact dashboard')

@section('content')
@php use App\Support\Finance; use App\Support\Impact; @endphp
<h1>Impact dashboard @if ($county) — {{ $county }} @endif</h1>
<p class="page-lede">Programme results counted live from the registers, county by county.</p>

<form method="GET" action="{{ route('admin.impacts.index') }}" class="filter-bar">
    <div>
        <label for="county">County</label>
        <select name="county" id="county" onchange="this.form.submit()">
            <option value="">Every county — the whole programme</option>
            @foreach (array_keys($counties) as $c)
                <option value="{{ $c }}" @selected($county === $c)>{{ $c }}</option>
            @endforeach
        </select>
    </div>
    <button class="btn btn-sm btn-outline" type="submit">Show</button>
    @if ($county)
        <a class="btn btn-sm btn-ghost" href="{{ route('admin.impacts.index') }}">Whole programme</a>
    @endif
    <span class="hint">A county's own report, with its records listed, is under <a href="{{ route('admin.reports.index') }}">Reports</a>.</span>
</form>

<div class="stat-row">
    @foreach ($headline as $h)
        <div class="stat">
            <small>{{ $h['label'] }}</small>
            <b>{{ match ($h['unit'] ?? 'count') {
                'money' => Finance::ksh((float) $h['value']),
                'kg' => number_format((float) $h['value'], 1).' kg',
                default => number_format((int) $h['value']),
            } }}</b>
        </div>
    @endforeach
</div>

@foreach (Impact::GROUPS as $key => $items)
    <div class="card">
        <h2 class="section-head">{{ $items['label'] }}</h2>
        <p class="help">{{ $items['note'] }}.</p>
        <table class="data indicator">
            <tbody>
                @foreach ($groups[$key] as $row)
                    <tr>
                        <td style="width:38%"><b>{{ $row['label'] }}</b></td>
                        <td class="num" style="width:18%">
                            @if (! empty($row['pending']))
                                <span class="tag">not counted yet</span>
                            @else
                                <span class="kv">{{ match ($row['unit'] ?? 'count') {
                                    'money' => Finance::ksh((float) $row['value']),
                                    'kg' => number_format((float) $row['value'], 1).' kg',
                                    default => number_format((int) $row['value']),
                                } }}</span>
                            @endif
                        </td>
                        <td class="hint">{{ $row['note'] }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@endforeach

<div class="card">
    <h2 class="section-head">Projects on the nine budget lines</h2>
    <p class="help">
        The programme reports work against the same nine lines finance budgets against (§14.2). A project falls on a
        line by the trade typed when it was posted — the trades actually typed are shown so the grouping can be checked.
        Anything that cannot be matched is listed on its own rather than dropped, so a missing trade does not silently
        reduce the totals.
    </p>
    <table class="data money-table">
        <thead>
            <tr><th>Budget line</th><th>Projects</th><th>Awarded or closed</th><th>Trades typed on them</th></tr>
        </thead>
        <tbody>
            @foreach ($sectors as $row)
                <tr>
                    <td><b>{{ $row['label'] }}</b></td>
                    <td>{{ $row['total'] }}</td>
                    <td>{{ $row['working'] }}</td>
                    <td><span class="hint">{{ $row['trades'] ? implode(', ', $row['trades']) : '—' }}</span></td>
                </tr>
            @endforeach
        </tbody>
    </table>
</div>

<p class="hint">
    Waiting on a decision somewhere in the programme right now: <b>{{ $waiting }}</b> items — see
    <a href="{{ route('admin.departments') }}">all departments</a>.
</p>
@endsection
