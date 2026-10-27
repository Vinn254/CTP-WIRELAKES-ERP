@extends('layouts.admin')
@section('title', 'Programme map')

@section('content')
@php use App\Support\KenyaMap; @endphp
@include('partials.errors')
<h1>Where the programme is working</h1>
<p class="page-lede">Kenya map showing how many records each county holds per layer.</p>

<div class="tabs">
    @foreach ($layers as $key => $l)
        <a class="tab {{ $layer === $key ? 'on' : '' }}" href="{{ route('admin.gis.map', ['layer' => $key]) }}">
            {{ $l['label'] }}<span class="n">{{ array_sum($l['counts']) }}</span>
        </a>
    @endforeach
</div>

@if ($selected)
    <p class="help">
        Showing <b>{{ $layers[$layer]['label'] }}</b> in <b>{{ $selected }}</b> —
        {{ $records->count() }} {{ Str::plural('record', $records->count()) }}.
        <a href="{{ route('admin.gis.map', ['layer' => $layer]) }}">Back to the whole map</a>
    </p>
@else
    <p class="help">
        {{ $total }} {{ Str::plural('record', $total) }} on this layer, across
        {{ $tiles->where('count', '>', 0)->count() + $offBoard->count() }} counties that name one.
        The busiest county holds {{ $peak }}.
    </p>
@endif

<div class="kenya-grid" style="--cols: {{ KenyaMap::COLS }}; --rows: {{ KenyaMap::ROWS }};">
    @foreach ($tiles as $t)
        <a class="map-tile band-{{ KenyaMap::band($t['count'], $peak) }} {{ $selected === $t['county'] ? 'on' : '' }}"
           style="grid-column: {{ $t['col'] }}; grid-row: {{ $t['row'] }};"
           href="{{ route('admin.gis.map', ['layer' => $layer, 'county' => $t['county']]) }}"
           title="{{ $t['county'] }} — {{ $t['count'] }} {{ Str::plural('record', $t['count']) }} on {{ $layers[$layer]['label'] }}">
            <b>{{ $t['count'] }}</b>
            <small>{{ $t['county'] }}</small>
        </a>
    @endforeach
</div>

<p class="hint key">
    <span><i class="map-swatch band-0"></i> nothing recorded</span>
    <span><i class="map-swatch band-1"></i> a few</span>
    <span><i class="map-swatch band-2"></i></span>
    <span><i class="map-swatch band-3"></i></span>
    <span><i class="map-swatch band-4"></i> busiest on this layer</span>
</p>

@if ($offBoard->isNotEmpty())
    <div class="card">
        <h2 class="section-head">Counties the records name that are not on the board</h2>
        <p class="help">
            These are shown rather than hidden, because a county spelled differently from the register's list would
            otherwise vanish from the map while still holding records. Correct the spelling on the record itself.
        </p>
        <table class="data">
            <thead><tr><th>As written on the record</th><th>Records</th></tr></thead>
            <tbody>
                @foreach ($offBoard as $o)
                    <tr>
                        <td><a href="{{ route('admin.gis.map', ['layer' => $layer, 'county' => $o['county']]) }}">{{ $o['county'] }}</a></td>
                        <td>{{ $o['count'] }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@endif

@if ($selected)
    <div class="card">
        <h2 class="section-head">{{ $layers[$layer]['label'] }} in {{ $selected }}</h2>
        <table class="data">
            <thead><tr><th>Code</th><th>Record</th><th>Where it stands</th></tr></thead>
            <tbody>
                @forelse ($records as $r)
                    <tr>
                        <td><span class="hint">{{ $r['code'] ?: '—' }}</span></td>
                        <td><a href="{{ $r['url'] }}"><b>{{ $r['title'] }}</b></a></td>
                        <td><span class="hint">{{ $r['meta'] }}</span></td>
                    </tr>
                @empty
                    <tr><td colspan="3" class="empty-cell">
                        No {{ strtolower($layers[$layer]['label']) }} recorded against that county name.
                    </td></tr>
                @endforelse
            </tbody>
        </table>
        <p class="hint">
            The same list, filtered, is on <a href="{{ route($layers[$layer]['route']) }}">{{ $layers[$layer]['label'] }}</a>.
        </p>
    </div>
@endif
@endsection
