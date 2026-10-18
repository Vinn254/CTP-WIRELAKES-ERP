@extends('layouts.admin')
@section('title', 'Programme settings')

@section('content')
@php use App\Support\Finance; @endphp
@include('partials.errors')
@include('partials.flash')
<p class="back"><a href="{{ route('admin.department', 'me_gis') }}">&larr; M&amp;E, GIS and Reports</a></p>
<h1>Programme settings</h1>
<p class="sub" style="color:var(--muted);margin:6px 0 16px;">
    §15.6 lists what the administration section configures. Only the switches <b>the code actually reads</b> are on
    this page — a setting nothing looks at would be worse than none, because it would look like it works.
    Page wording, fees shown on the public site and branding are edited in
    {!! dept_ref('admin.content.index', 'site content') !!} (Module 1); staff accounts and roles in
    {!! dept_ref('admin.staff.index', 'staff & roles') !!}.
</p>

@if (! $canEdit)
    <p class="help">
        You can read these values. Changing a programme setting is a <b>programme executive's</b> action — the same
        rule as issuing a certificate or releasing money.
    </p>
@endif

<div class="card">
    <form method="POST" action="{{ route('admin.settings.update') }}" class="need-form">
        @csrf
        @foreach ($rows as $row)
            <div class="field">
                <label for="{{ $row['key'] }}">{{ $row['label'] }} <span class="req">*</span></label>
                <input type="{{ $row['type'] === 'money' ? 'number' : 'text' }}"
                       id="{{ $row['key'] }}" name="{{ $row['key'] }}"
                       value="{{ old($row['key'], $row['value']) }}"
                       min="{{ $row['type'] === 'money' ? 0 : null }}" step="{{ $row['type'] === 'money' ? '0.01' : null }}"
                       maxlength="190" @disabled(! $canEdit)>
                <div class="help">{{ $row['read'] }}</div>
            </div>
        @endforeach

        @if ($canEdit)
            <button class="btn btn-primary" type="submit">Save the programme settings</button>
        @endif
    </form>
</div>

<div class="card">
    <h2 class="section-head">Rules that are not switches</h2>
    <table class="data">
        <thead><tr><th>Rule</th><th>What it is</th><th>Why it is not editable here</th></tr></thead>
        <tbody>
            <tr>
                <td><b>Mobilisation credit</b></td>
                <td>{{ collect($mobilisation)->map(fn ($l, $n) => $n.' people — '.$l)->join('; ') }}</td>
                <td><span class="hint">Built into the CTP lifecycle (Module 2 §5.4), so a fee waiver and the credit can never disagree.</span></td>
            </tr>
            <tr>
                <td><b>Who signs what off</b></td>
                <td>Staff prepare, a programme executive approves, issues a certificate or releases money</td>
                <td><span class="hint">Held in the code and the roles, not a value that can be switched off.</span></td>
            </tr>
            <tr>
                <td><b>Courses that can be run</b></td>
                <td>The trainings catalogue, extended by the office</td>
                <td><span class="hint">A closed list on the Training department's own screen (Module 4) — not a free-text setting.</span></td>
            </tr>
        </tbody>
    </table>
</div>
@endsection
