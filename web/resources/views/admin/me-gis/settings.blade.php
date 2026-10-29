@extends('layouts.admin')
@section('title', 'Programme settings')

@section('content')
@php use App\Support\Finance; @endphp
@include('partials.errors')
@include('partials.flash')
<h1>Programme settings</h1>
<p class="page-lede">Edits the programme settings the application actually reads.</p>

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
                @if ($row['type'] === 'flag')
                    <select id="{{ $row['key'] }}" name="{{ $row['key'] }}" @disabled(! $canEdit)>
                        <option value="off" @selected(old($row['key'], $row['value']) !== 'on')>Off — payments are written in by hand</option>
                        <option value="on" @selected(old($row['key'], $row['value']) === 'on')>On — the M-Pesa line is live</option>
                    </select>
                @else
                    <input type="{{ $row['type'] === 'money' ? 'number' : 'text' }}"
                           id="{{ $row['key'] }}" name="{{ $row['key'] }}"
                           value="{{ old($row['key'], $row['value']) }}"
                           min="{{ $row['type'] === 'money' ? 0 : null }}" step="{{ $row['type'] === 'money' ? '0.01' : null }}"
                           maxlength="190" @disabled(! $canEdit)>
                @endif
                <div class="help">{{ $row['read'] }}</div>
            </div>
        @endforeach

        @if ($canEdit)
            <button class="btn btn-primary" type="submit">Save the programme settings</button>
        @endif
    </form>
</div>

<div class="card">
    <h2 class="section-head">Email delivery</h2>
    <p class="sub">
        Everything the programme posts goes out through the settings below. They live in the <code>.env</code> file on
        the server, not on this page. When an email has not arrived, run the test — it answers with what the mail
        server actually said instead of staying silent.
    </p>
    <table class="data">
        <thead><tr><th>Setting</th><th>Currently in use</th></tr></thead>
        <tbody>
            <tr>
                <td><b>Mailer</b></td>
                <td>{{ $mail['mailer'] }}
                    @if ($mail['mailer'] !== 'smtp')
                        <span class="hint">— the app is not sending email with this setting</span>
                    @endif
                </td>
            </tr>
            <tr><td>Mail server</td><td>{{ $mail['host'] }}:{{ $mail['port'] }} ({{ $mail['scheme'] }})</td></tr>
            <tr><td>Signs in as</td><td>{{ $mail['username'] !== '' ? $mail['username'] : '— no username —' }}</td></tr>
            <tr><td>Sent from</td><td>{{ $mail['from'] !== '' ? $mail['from'] : '— no from address —' }}</td></tr>
        </tbody>
    </table>
    @if (auth()->user()->role === 'superadmin')
        <form method="POST" action="{{ route('admin.settings.mail_check') }}" class="inline-form">
            @csrf
            <button class="btn btn-primary" type="submit">Send a test email to {{ auth()->user()->email }}</button>
        </form>
    @endif
</div>

<div class="card">
    <h2 class="section-head">Rules that are not switches</h2>
    <table class="data">
        <thead><tr><th>Rule</th><th>What it is</th><th>Why it is not editable here</th></tr></thead>
        <tbody>
            <tr>
                <td><b>Mobilisation credit</b></td>
                <td>{{ collect($mobilisation)->map(fn ($l, $n) => $n.' people — '.$l)->join('; ') }}</td>
                <td><span class="hint">Built into the CTP lifecycle, so a fee waiver and the credit can never disagree.</span></td>
            </tr>
            <tr>
                <td><b>Who signs what off</b></td>
                <td>Staff prepare, a programme executive approves, issues a certificate or releases money</td>
                <td><span class="hint">Held in the code and the roles, not a value that can be switched off.</span></td>
            </tr>
            <tr>
                <td><b>Courses that can be run</b></td>
                <td>The trainings catalogue, extended by the office</td>
                <td><span class="hint">A closed list on the Training department's own screen — not a free-text setting.</span></td>
            </tr>
        </tbody>
    </table>
</div>
@endsection
