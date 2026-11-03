@extends('layouts.admin')
@section('title', 'Staff directory')

@php
    use App\Support\Departments;
    use App\Support\Roles;

    $tabs = ['all' => 'Every person', 'incomplete' => 'File missing something', 'photo' => 'No profile photo',
        'kin' => 'C14 not filled', 'waiting' => 'Waiting for the login'];
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
            <span>{{ $noKin }} without next of kin</span>
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
                    <th class="num">Monthly pay</th><th>C14 — next of kin</th><th></th>
                </tr>
            </thead>
            <tbody>
                @foreach ($records as $r)
                    <tr>
                        <td>@if ($r->hasPhoto())<span class="avatar has-photo"><a
                                    href="{{ route('admin.directory.photo', $r) }}" data-zoom
                                    aria-label="Open the profile photo"><img
                                        src="{{ route('admin.directory.photo', $r) }}"
                                        alt="{{ $r->name }} — profile photo"></a></span>@endif
                            <b>{{ $r->name }}</b>
                            <small>{{ $r->user ? ($r->user->isSuperAdmin() ? 'Super admin' : ($r->user->active
                                ? 'Office staff' : 'Suspended')) : 'Onboarded — link not opened yet' }}</small></td>
                        <td>{{ $r->job_title ?: '—' }}
                            @if ($r->user && $r->user->roleSlugs())
                                <small>{{ implode(', ', \App\Support\Roles::namesFor($r->user->roleSlugs())) }}</small>
                            @endif</td>
                        <td>{{ $r->email }}@if ($r->phone)<small>{{ $r->phone }}</small>@endif</td>
                        <td>{{ $r->user ? $modules($r->user) : '—' }}</td>
                        <td class="nowrap">{{ $r->started_on?->format('d M Y') ?: '—' }}</td>
                        <td class="num">{{ $money($r->salary) }}</td>
                        <td>@if ($r->next_of_kin_name)
                                <b>{{ $r->next_of_kin_name }}</b> · {{ $r->next_of_kin_phone ?: 'no contact' }}
                                · {{ $r->next_of_kin_relation ?: 'relationship not written' }}
                                @if ($r->next_of_kin_2_name)
                                    <small>2nd: {{ $r->next_of_kin_2_name }} · {{ $r->next_of_kin_2_phone ?: 'no contact' }}
                                        · {{ $r->next_of_kin_2_relation ?: 'relationship not written' }}</small>
                                @endif
                            @else
                                <span class="tag gray">not written</span>
                            @endif</td>
                        <td class="actions">
                            <button class="btn btn-sm btn-outline" type="button" data-sheet="edit-{{ $r->id }}">Edit</button>
                            <a class="btn btn-sm btn-ghost" href="{{ route('admin.directory.show', $r) }}">Open</a>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    @foreach ($records as $r)
        @include('partials.sheet-start', ['name' => 'edit-'.$r->id, 'title' => 'Edit — '.$r->name,
            'sub' => 'What this desk holds on the person. Their account and the modules it opens are set under Staff & roles.'])
        <form method="POST" action="{{ route('admin.directory.update', $r) }}" class="fee-form"
              enctype="multipart/form-data" data-confirm="Save these changes to {{ $r->name }}’s file?"
              data-title="Edit the file">
            @csrf
            <input type="hidden" name="_sheet" value="edit-{{ $r->id }}">
            @include('admin.staff.partials.fields', ['owner' => $r, 'sheet' => 'edit-'.$r->id])
            <div class="sheet-actions">
                <button class="btn btn-sm btn-ghost" type="button" data-close-sheet>Cancel</button>
                <button class="btn btn-sm btn-lime" type="submit">Save the file</button>
            </div>
        </form>
        @include('partials.sheet-end')
    @endforeach
@endif

<p class="hint">Everyone holding a staff account is fetched onto this list on its own. A person arrives from
    <b>Onboarding</b> once they are given the job.</p>
@endsection
