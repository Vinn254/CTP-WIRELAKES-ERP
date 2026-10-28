@extends('layouts.admin')
@section('title', $record->name)

@php
    use App\Support\Departments;
    use App\Support\Leave;

    $account = $record->user;
    $money = fn ($v) => $v === null ? '—' : 'KSh '.number_format((float) $v);
    $shown = fn ($v) => $v ?: '—';
@endphp

@section('content')
@include('partials.errors')
<div class="applicant-head card">
    @php
        $photoUrl = $record->hasPhoto() ? route('admin.directory.photo', $record) : null;
        $initials = collect(explode(' ', trim($record->name)))->filter()->take(2)
            ->map(fn ($word) => mb_strtoupper(mb_substr($word, 0, 1)))->join('');
    @endphp
    <span class="avatar-lg{{ $photoUrl ? ' has-photo' : '' }}">
        @if ($photoUrl)
            <a href="{{ $photoUrl }}" data-zoom aria-label="Open the profile photo"><img src="{{ $photoUrl }}" alt="{{ $record->name }} — profile photo"></a>
        @else{{ $initials }}@endif
    </span>
    <div class="ah-main">
        <h1>{{ $record->name }}
            <span class="status {{ $account ? ($account->active ? 'active' : 'rejected') : 'pending_review' }}">
                {{ $account ? ($account->active ? 'Working here' : 'Account deactivated') : 'Waiting for the login' }}
            </span></h1>
        <p class="ah-meta">
            <span>{{ $record->job_title ?: 'No job title yet' }}</span>
            <span>{{ $record->email }}</span>
            @if ($record->phone)<span>{{ $record->phone }}</span>@endif
            @if ($record->started_on)<span>since {{ $record->started_on->format('d M Y') }}</span>@endif
            @if ($account)
                <span>{{ $account->isSuperAdmin() ? 'Super admin' : 'Opens '.count($account->departmentSlugs())
                    .' of '.count($liveDepartments).' modules' }}</span>
            @endif
        </p>
    </div>
    <div class="ah-contact">
        <button class="btn btn-sm btn-lime" type="button" data-sheet="file">Amend the file</button>
        <button class="btn btn-sm btn-ghost" type="button" onclick="window.print()">Print</button>
    </div>
</div>

@if ($record->missing())
    <div class="card callout">
        <b>Still to be written up:</b> {{ implode(', ', $record->missing()) }}.
    </div>
@endif

<div class="card">
    <div class="section-head"><h3>The person</h3></div>
    <dl class="kv">
        <dt>Name</dt><dd>{{ $record->name }}</dd>
        <dt>Email</dt><dd>{{ $record->email }}</dd>
        <dt>Phone</dt><dd>{{ $shown($record->phone) }}</dd>
        <dt>Job title</dt><dd>{{ $shown($record->job_title) }}</dd>
        <dt>ID number</dt><dd>{{ $shown($record->id_number) }}</dd>
        <dt>KRA PIN</dt><dd>{{ $shown($record->kra_pin) }}</dd>
        <dt>NSSF</dt><dd>{{ $shown($record->nssf_number) }}</dd>
        <dt>NHIF</dt><dd>{{ $shown($record->nhif_number) }}</dd>
        <dt>Address</dt><dd>{{ $shown($record->address) }}</dd>
        <dt>First day</dt><dd>{{ $record->started_on?->format('d M Y') ?: '—' }}</dd>
    </dl>
</div>

<div class="two-col">
    <div class="card">
        <div class="section-head"><h3>Pay</h3></div>
        <dl class="kv">
            <dt>Monthly pay</dt><dd>{{ $money($record->salary) }}</dd>
            <dt>Bank</dt><dd>{{ $shown($record->bank_name) }}</dd>
            <dt>Branch</dt><dd>{{ $shown($record->bank_branch) }}</dd>
            <dt>Account</dt><dd>{{ $shown($record->bank_account) }}</dd>
        </dl>
        <p class="hint">Pay is a Human Resources record. It is not printed on a member screen.</p>
    </div>

    <div class="card">
        <div class="section-head"><h3>Next of kin</h3></div>
        <dl class="kv">
            <dt>Name</dt><dd>{{ $shown($record->next_of_kin_name) }}</dd>
            <dt>Relationship</dt><dd>{{ $shown($record->next_of_kin_relation) }}</dd>
            <dt>Phone</dt><dd>{{ $shown($record->next_of_kin_phone) }}</dd>
        </dl>
    </div>
</div>

<div class="card">
    <div class="section-head"><h3>Documents</h3></div>
    @if ($record->hasC14())
        <p><a class="btn btn-sm btn-outline" href="{{ route('admin.directory.c14', $record) }}" target="_blank">
            Open the C14</a>
            <span class="hint">{{ $record->c14_name }} · {{ number_format(max(1, (int) $record->c14_size / 1024), 1) }} KB
                — kept on the private disk and served to this desk only.</span></p>
    @else
        <p class="help">No C14 on record yet. Use <b>Amend the file</b> to attach it.</p>
    @endif
    @if ($record->application)
        <p class="hint">Came in through the opening {{ $record->application->opening?->reference ?: '—' }}
            ({{ $record->application->name }} applied
            {{ $record->application->created_at?->format('d M Y') }}).</p>
    @endif
</div>

<div class="card">
    <div class="section-head"><h3>Leave</h3></div>
    @if ($account && \App\Support\Leave::worker($account))
        <p class="hint">{{ Leave::name('annual') }}:
            {{ Leave::format(Leave::balance($account, 'annual')['left']) }} left for {{ now()->year }} ·
            {{ Leave::format(Leave::balance($account, 'sick')['left']) }} sick days left.
            <a href="{{ route('admin.leave.index', ['type' => 'annual']) }}">The register</a></p>
    @else
        <p class="help">This file has no staff account yet, so there is no leave balance to read.</p>
    @endif
</div>

@include('partials.sheet-start', ['name' => 'file', 'title' => 'Amend the file',
    'sub' => 'What Human Resources holds on this person. Pay and next of kin stay on this desk.'])
    <form method="POST" action="{{ route('admin.directory.update', $record) }}" class="fee-form"
          enctype="multipart/form-data" data-confirm="Save these changes to {{ $record->name }}’s file?"
          data-title="Amend the file">
        @csrf
        <input type="hidden" name="_sheet" value="file">
        @include('admin.staff.partials.fields', ['owner' => $record])
        <div class="sheet-actions">
            <button class="btn btn-sm btn-ghost" type="button" data-close-sheet>Cancel</button>
            <button class="btn btn-sm btn-lime" type="submit">Save the file</button>
        </div>
    </form>
@include('partials.sheet-end')
@endsection
