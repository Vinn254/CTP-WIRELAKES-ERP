@extends('layouts.admin')
@section('title', 'Programme timetable')

@section('content')
<div class="head-row">
    <div>
        <h1>Programme timetable</h1>
        <p class="page-lede">The week each programme runs on — a trainer reads it and still sends their own.</p>
    </div>
</div>

@include('partials.errors')

<div class="stat-row">
    <div class="stat"><small>Programmes</small><b>{{ $rows->count() }}</b></div>
    <div class="stat"><small>Weeks set</small><b>{{ $rows->where('classes', '>', 0)->count() }}</b></div>
    <div class="stat"><small>Classes a week</small><b>{{ $rows->sum('classes') }}</b></div>
    <div class="stat"><small>Hours a week</small><b>{{ \App\Models\TimetableSlot::clocked($rows->sum('hours')) ?: 0 }}</b></div>
</div>

<div class="table-scroll">
    <table class="data">
        <thead>
            <tr>
                <th>Programme</th><th>Units</th><th>Intakes running</th><th>Classes on the week</th>
                <th>Hours a week</th><th>Status</th><th></th>
            </tr>
        </thead>
        <tbody>
            @forelse ($rows as $row)
                <tr>
                    <td><b>{{ $row['training']->name }}</b> <span class="hint">{{ $row['training']->code }}</span></td>
                    <td>{{ $row['units'] }}</td>
                    <td>{{ $row['cohorts'] }}</td>
                    <td>{{ $row['classes'] }}</td>
                    <td class="length">{{ \App\Models\TimetableSlot::clocked($row['hours']) ?: '0' }} h</td>
                    <td>
                        @if ($row['week'])
                            <span class="status {{ $row['week']->statusClass() }}">{{ $row['week']->statusLabel() }}</span>
                        @else
                            <span class="status draft">Not set</span>
                        @endif
                    </td>
                    <td class="actions">
                        <a class="btn btn-sm btn-lime" href="{{ route('admin.programme_timetables.edit', $row['training']) }}">
                            {{ $row['week'] ? 'Edit the week' : 'Set the week' }}</a>
                    </td>
                </tr>
            @empty
                <tr><td colspan="7" class="empty-cell">No programme is on the list yet.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>

<p class="hint">A week is built from the units of that programme only. Trainers teaching it see it on their own
    timetable page, beside the week they write for their cohort.</p>
@endsection
