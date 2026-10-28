@extends('layouts.admin')
@section('title', 'Staff directory')

@php
    use App\Support\Departments;
    use App\Support\Roles;

    $tabs = ['all' => 'Every person', 'incomplete' => 'File missing something', 'c14' => 'No C14 on record',
        'waiting' => 'Waiting for the login'];
    $money = fn ($v) => $v === null ? '—' : 'KSh '.number_format((float) $v);
    $modules = fn ($u) => $u?->isSuperAdmin() ? 'Every module'
        : (count($u?->departmentSlugs() ?? []).'/'.count(Departments::live()));
@endphp

@section('content')
<div class="head-row">
    <div>
        <h1>Staff directory</h1>
        <p class="ah-meta">
            <span><b>{{ $all }}</b> on the register</span>
            <span>{{ $withAccount }} with a login</span>
            <span>{{ $incomplete }} file{{ $incomplete === 1 ? '' : 's' }} short</span>
            <span>{{ $noC14 }} without a C14</span>
        </p>
    </div>
    <div class="head-actions">
        <button class="btn btn-sm btn-ghost" type="button" onclick="window.print()">Print</button>
    </div>
</div>

<form method="GET" action="{{ route('admin.directory.index') }}" class="filter-bar inline-filter">
    <label class="field">
        <span>Directory filter</span>
        <select name="f" onchange="this.form.submit()">
            @foreach ($tabs as $key => $label)
                <option value="{{ $key }}" @selected($filter === $key)>{{ $label }}</option>
            @endforeach
        </select>
    </label>
    <noscript><button class="btn btn-sm btn-outline" type="submit">Apply</button></noscript>
</form>

@if ($records->isEmpty())
    <div class="card"><p class="page-lede">Nobody is on this list yet.</p></div>
@else
    <div class="table-scroll">
        <table class="data">
            <thead>
                <tr>
                    <th>Name</th><th>Job title</th><th>Contact</th><th>Modules opened</th><th>First day</th>
                    <th class="num">Monthly pay</th><th>Next of kin</th><th>C14</th><th></th>
                </tr>
            </thead>
            <tbody>
                @foreach ($records as $r)
                    <tr>
                        <td><b>{{ $r->name }}</b>
                            <small>{{ $r->user ? ($r->user->isSuperAdmin() ? 'Super admin' : ($r->user->active
                                ? 'Office staff' : 'Deactivated')) : 'Onboarded — link not opened yet' }}</small></td>
                        <td>{{ $r->job_title ?: '—' }}
                            @if ($r->user && $r->user->roleSlugs())
                                <small>{{ implode(', ', \App\Support\Roles::namesFor($r->user->roleSlugs())) }}</small>
                            @endif</td>
                        <td>{{ $r->email }}@if ($r->phone)<small>{{ $r->phone }}</small>@endif</td>
                        <td>{{ $r->user ? $modules($r->user) : '—' }}</td>
                        <td class="nowrap">{{ $r->started_on?->format('d M Y') ?: '—' }}</td>
                        <td class="num">{{ $money($r->salary) }}</td>
                        <td>{{ $r->next_of_kin_name ?: '—' }}@if ($r->next_of_kin_phone)<small>{{ $r->next_of_kin_phone }}</small>@endif</td>
                        <td>@if ($r->hasC14())
                                <a href="{{ route('admin.directory.c14', $r) }}" target="_blank">on file</a>
                            @else
                                <span class="tag gray">none</span>
                            @endif</td>
                        <td class="actions">
                            <a class="btn btn-sm btn-outline" href="{{ route('admin.directory.show', $r) }}">Open</a>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@endif

<p class="hint">Everyone holding a staff account is fetched onto this list on its own. A person arrives from
    <b>Onboarding</b> once they are given the job.</p>
@endsection
