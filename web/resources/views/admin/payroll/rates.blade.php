@extends('layouts.admin')
@section('title', 'Deduction rates')

@php
    use App\Support\Finance;

    $money = fn ($v) => number_format((float) $v, 2, '.', '');
    $bands = collect($rates['bands']);
@endphp

@section('content')
<div class="head-row">
    <div>
        <h1>Deduction rates</h1>
        <p class="ah-meta"><span>{{ $bands->count() }} PAYE bands</span><span>Relief {{ Finance::ksh($rates['relief_ksh']) }}</span></p>
    </div>
    <div class="head-actions">
        <a class="btn btn-sm btn-outline" href="{{ route('admin.payroll.index') }}">Payroll register</a>
    </div>
</div>

<p class="page-lede">The rates the statutory deductions are worked at.</p>

@include('partials.errors')

<form method="POST" action="{{ route('admin.payroll.rates.save') }}" class="card">
    @csrf
    <h4>PAYE bands (monthly)</h4>
    <div class="table-scroll">
        <table class="data">
            <thead><tr><th>Taxable pay up to (KSh)</th><th class="num">Per cent</th></tr></thead>
            <tbody>
                @foreach (range(0, max($bands->count(), 1) - 1) as $i)
                    @php $band = $bands[$i] ?? ['up_to' => null, 'pct' => 0];
                    $up = old('bands.'.$i.'.up_to', $band['up_to']);
                    $pct = old('bands.'.$i.'.pct', $band['pct']); @endphp
                    <tr>
                        <td>
                            <input type="number" step="0.01" min="1" max="99999999" name="bands[{{ $i }}][up_to]"
                                   value="{{ filled($up) ? $money($up) : '' }}"
                                   placeholder="Leave the last one blank">
                        </td>
                        <td class="num">
                            <input type="number" step="0.01" min="0" max="100" name="bands[{{ $i }}][pct]"
                                   value="{{ $money($pct) }}" required>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    <p class="hint">The last band is open — everything above its ceiling. Each slice of taxable pay is taxed at its
        own rate, then the relief below is taken off.</p>

    <h4>Relief and the other statutory deductions</h4>
    <div class="form-grid-3">
        @foreach (['relief_ksh' => 'Personal relief (KSh a month)', 'nssf_pct' => 'NSSF per cent',
            'nssf_tier1' => 'NSSF tier 1 up to (KSh)', 'nssf_tier2' => 'NSSF tier 2 up to (KSh)',
            'shif_pct' => 'SHIF per cent', 'shif_min' => 'SHIF minimum (KSh a month)',
            'levy_pct' => 'Housing levy per cent'] as $key => $label)
            <div class="field">
                <label>{{ $label }}</label>
                <input type="number" step="0.01" min="0" max="99999999" name="{{ $key }}"
                       value="{{ $money(old($key, $rates[$key])) }}" required>
            </div>
        @endforeach
    </div>

    <div class="sheet-actions">
        <button class="btn btn-primary" type="submit">Save the rates</button>
    </div>
</form>

<div class="card">
    <h3>What the rates give a paysheet</h3>
    <table class="data">
        <thead><tr><th>Gross pay</th><th class="num">PAYE</th><th class="num">NSSF</th><th class="num">SHIF</th><th class="num">Housing levy</th><th class="num">Total off</th></tr></thead>
        <tbody>
            @foreach ([18000, 40000, 75000, 150000, 300000] as $gross)
                @php $fig = App\Support\Deductions::statutory($gross); @endphp
                <tr>
                    <td>{{ Finance::ksh($gross) }}</td>
                    <td class="num">{{ Finance::ksh($fig['paye_ksh']) }}</td>
                    <td class="num">{{ Finance::ksh($fig['nssf_ksh']) }}</td>
                    <td class="num">{{ Finance::ksh($fig['shif_ksh']) }}</td>
                    <td class="num">{{ Finance::ksh($fig['levy_ksh']) }}</td>
                    <td class="num"><b>{{ Finance::ksh(array_sum($fig)) }}</b></td>
                </tr>
            @endforeach
        </tbody>
    </table>
    <p class="hint">A worked example at these rates, on five gross pays. Nothing here is written to a sheet — a month
        is worked out on its own page, and any figure can still be corrected line by line.</p>
</div>
@endsection
