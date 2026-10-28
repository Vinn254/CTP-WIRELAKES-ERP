@extends('layouts.admin')
@section('title', 'Onboarding')

@section('content')
@include('partials.errors')
<div class="head-row">
    <div>
        <h1>Onboarding</h1>
        <p class="ah-meta">
            <span><b>{{ $waiting->count() }}</b> waiting</span>
            <span>{{ $waiting->where('status', 'picked')->count() }} given the job</span>
            <span>{{ $waiting->where('status', 'shortlisted')->count() }} shortlisted</span>
        </p>
    </div>
</div>

<p class="page-lede">Write the staff file of a person the careers desk shortlisted and gave the job.</p>

@if ($waiting->isEmpty())
    <div class="card"><p class="page-lede">Nobody is waiting to be onboarded.</p></div>
@else
    <div class="table-scroll">
        <table class="data">
            <thead>
                <tr><th>Who applied</th><th>Opening</th><th>Contact</th><th>Where it stands</th>
                    <th>Read by</th><th>CV</th><th></th></tr>
            </thead>
            <tbody>
                @foreach ($waiting as $a)
                    <tr>
                        <td><b>{{ $a->name }}</b>@if ($a->town)<small>{{ $a->town }}</small>@endif</td>
                        <td>{{ $a->opening?->title ?: '—' }}<small>{{ $a->opening?->reference }}</small></td>
                        <td>{{ $a->email }}@if ($a->phone)<small>{{ $a->phone }}</small>@endif</td>
                        <td><span class="status {{ $a->status === 'picked' ? 'active' : 'pending_review' }}">
                            {{ $a->statusLabel() }}</span></td>
                        <td>{{ $a->reviewer?->name ?: 'nobody yet' }}</td>
                        <td>@if ($a->hasCv())
                                <a href="{{ route('admin.careers.cv', $a) }}" target="_blank">open</a>
                            @else
                                <span class="hint">none</span>
                            @endif</td>
                        <td class="actions">
                            <button class="btn btn-sm btn-lime" type="button"
                                    data-sheet="onboard-{{ $a->id }}">Onboard</button>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@endif

@foreach ($waiting as $a)
    @include('partials.sheet-start', ['name' => 'onboard-'.$a->id,
        'title' => 'Onboard '.$a->name,
        'sub' => 'This writes the staff file, raises the login invitation and puts them on the directory.'])
        <form method="POST" action="{{ route('admin.onboarding.store', $a) }}" class="fee-form"
              enctype="multipart/form-data" data-confirm="Onboard {{ $a->name }} as staff?"
              data-title="Onboard">
            @csrf
            <input type="hidden" name="_sheet" value="onboard-{{ $a->id }}">
            @include('admin.staff.partials.fields', ['owner' => $a])

            <div class="field full">
                <label>Roles <span class="hint">— tick every one this person holds</span></label>
                <div class="role-grid">
                    @foreach ($roles as $slug => $r)
                        <label class="role-pick">
                            <input type="checkbox" name="roles[]" value="{{ $slug }}"
                                   data-depts="{{ implode(' ', $r['departments']) }}">
                            <b>{{ $r['name'] }}</b>
                            <small>{{ $r['desc'] }}</small>
                        </label>
                    @endforeach
                </div>
            </div>

            <details class="dept-pick" @if ($errors->any() && old('_sheet') === 'onboard-'.$a->id) open @endif>
                <summary>Departments this opens</summary>
                <div class="dept-box">
                    @foreach ($liveDepartments as $d)
                        <label class="chk"><input type="checkbox" name="departments[]" value="{{ $d['slug'] }}"
                            @checked(in_array($d['slug'], \App\Support\Roles::departmentsFor((array) old('roles', [])), true))>
                            {{ $d['name'] }}</label>
                    @endforeach
                    <div class="hint">A role opens all of its modules. Add a department here to open one extra
                        module without giving the whole role.</div>
                </div>
            </details>

            <div class="sheet-actions">
                <button class="btn btn-sm btn-ghost" type="button" data-close-sheet>Cancel</button>
                <button class="btn btn-sm btn-lime" type="submit">Onboard them</button>
            </div>
        </form>
    @include('partials.sheet-end')
@endforeach

@if ($done->isNotEmpty())
    <h2 class="sec-title">Onboarded</h2>
    <div class="table-scroll">
        <table class="data">
            <thead><tr><th>Who</th><th>Opening</th><th>Onboarded</th><th>By</th><th>File</th></tr></thead>
            <tbody>
                @foreach ($done as $a)
                    <tr>
                        <td><b>{{ $a->name }}</b><small>{{ $a->email }}</small></td>
                        <td>{{ $a->opening?->title ?: '—' }}</td>
                        <td class="nowrap">{{ $a->onboarded_at?->format('d M Y') ?: '—' }}</td>
                        <td>{{ $a->onboarder?->name ?: '—' }}</td>
                        <td class="actions">
                            @if ($a->staffRecord)
                                <a class="btn btn-sm btn-outline"
                                   href="{{ route('admin.directory.show', $a->staffRecord) }}">Open</a>
                            @else
                                <span class="hint">no file</span>
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@endif

<p class="hint">An applicant reaches this desk from the careers board: shortlist them, then mark them
    <b>Given the job</b>. Pay and next of kin are typed once here and amended on the directory after that.</p>
@endsection
