@extends('layouts.admin')
@section('title', 'Time logs')

@php
    use App\Models\TimeLog;
    use App\Support\Departments;

    $read = Departments::hosted();
    $here = $read ? ['department' => Departments::currentSlug()] : [];
    $self = request()->route()->getName();
@endphp

@section('content')
<h1>Time logs</h1>
<p class="page-lede">{{ $read ? 'The signed weeks, read here as proof of the work.' : 'Every week staff have filled, and the approval pay is built on.' }}</p>

<div class="stat-row">
    <div class="stat"><small>Weeks shown</small><b>{{ $weeks->count() }}</b></div>
    <div class="stat"><small>People</small><b>{{ $people }}</b></div>
    <div class="stat"><small>Waiting on this desk</small><b>{{ $counts['submitted'] ?? 0 }}</b></div>
    <div class="stat"><small>Approved</small><b>{{ $counts['approved'] ?? 0 }}</b></div>
    <div class="stat"><small>Hours</small><b>{{ number_format($hours, 2) }}</b></div>
    <div class="stat"><small>Units</small><b>{{ (int) $units }}</b></div>
</div>

<form method="GET" action="{{ route($self, $here) }}" class="filter-bar">
    <div class="field">
        <label>Month</label>
        <select name="month">
            <option value="">Every month</option>
            @foreach ($months as $m)
                <option value="{{ $m }}" @selected($month === $m)>{{ \Illuminate\Support\Carbon::parse($m.'-01')->format('F Y') }}</option>
            @endforeach
        </select>
    </div>
    <div class="field">
        <label>Status</label>
        <select name="status">
            <option value="all">Every week</option>
            @foreach ($statuses as $s => $lbl)
                <option value="{{ $s }}" @selected($status === $s)>{{ $lbl }} ({{ $counts[$s] ?? 0 }})</option>
            @endforeach
        </select>
    </div>
    <button class="btn btn-sm btn-outline" type="submit">Filter</button>
</form>

@if ($weeks->isEmpty())
    <div class="card">
        <p class="span-note">No week matches this filter. A worker fills their days on their own time log and signs the
            week; it arrives here for the office to approve.</p>
    </div>
@else
    <div class="table-scroll">
        <table class="data">
            <thead>
                <tr>
                    <th>Person</th><th>Dept</th><th>Week</th><th class="num">Days</th><th class="num">Hours</th>
                    <th class="num">Units</th><th>Status</th><th></th>
                </tr>
            </thead>
            <tbody>
                @foreach ($weeks as $week)
                    @php $w = $week['worker']; @endphp
                    <tr>
                        <td><b>{{ $w?->name ?? 'A removed account' }}</b>
                            <small>{{ $w?->email }}</small></td>
                        <td>{{ $week['department'] ?: '—' }}</td>
                        <td class="nowrap">{{ $week['label'] }}
                            <small>Week {{ $week['week_no'] }} of {{ \Illuminate\Support\Carbon::parse($week['month'].'-01')->format('F Y') }}</small></td>
                        <td class="num">{{ $week['days'] }}</td>
                        <td class="num">{{ number_format((float) $week['hours'], 2) }}</td>
                        <td class="num">{{ (int) $week['units'] }}</td>
                        <td><span class="status {{ ['draft' => 'closed', 'submitted' => 'pending_review',
                                'approved' => 'active', 'returned' => 'suspended'][$week['status']] ?? 'closed' }}">
                                {{ TimeLog::STATUSES[$week['status']] ?? $week['status'] }}</span></td>
                        <td class="actions"><a class="btn btn-sm btn-outline"
                            href="{{ Departments::inside('admin.timelogs.week', [$w, $week['start']->toDateString()]) }}">Open</a></td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@endif
@endsection
