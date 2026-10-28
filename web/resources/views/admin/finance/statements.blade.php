@extends('layouts.admin')
@section('title', 'Financial reports')

@section('content')
<h1>Financial reports</h1>
<p class="page-lede">Nine money statements, counted live from the registers.</p>

<div class="stat-row">
    <div class="stat"><small>Statements here</small><b>{{ count($statements) }}</b></div>
    <div class="stat"><small>Year counted</small><b>{{ $year }}</b></div>
    <div class="stat"><small>Years on the register</small><b>{{ count($years) }}</b></div>
</div>

<form method="GET" action="{{ route('admin.statements.index') }}" class="filter-bar">
    <div class="field">
        <label>Count the year</label>
        <select name="year">
            @foreach ($years as $y)
                <option value="{{ $y }}" @selected($year === $y)>{{ $y }}</option>
            @endforeach
        </select>
    </div>
    <button class="btn btn-sm btn-outline" type="submit">Choose</button>
</form>

<div class="table-scroll">
    <table class="data">
        <thead><tr><th>Statement</th><th>What it answers</th><th></th></tr></thead>
        <tbody>
            @foreach ($statements as $key => $item)
                <tr>
                    <td><b>{{ $item['title'] }}</b></td>
                    <td><span class="hint">{{ $item['note'] }}</span></td>
                    <td class="actions"><a class="btn btn-sm btn-outline"
                        href="{{ route('admin.statements.show', ['statement' => $key, 'year' => $year]) }}">Open</a></td>
                </tr>
            @endforeach
        </tbody>
    </table>
</div>

<p class="hint">Every statement is counted again the moment it is opened, so a screen, a file and a printout
    always carry the same figures. The money register itself is written only by
    <a href="{{ route('admin.ledger.index') }}">Money register</a>.</p>
@endsection
