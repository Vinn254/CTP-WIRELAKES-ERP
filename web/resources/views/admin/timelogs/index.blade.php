@extends('layouts.admin')
@section('title', 'Trainer time logs')

@php
    use App\Models\TimeLog;
    use App\Support\Departments;

    $tabs = ['all' => 'All weeks', 'submitted' => 'Waiting on this desk', 'draft' => 'Being filled',
        'returned' => 'Returned', 'approved' => 'Approved'];
    $read = Departments::hosted();
    $here = $read ? ['department' => Departments::currentSlug()] : [];
    $self = request()->route()->getName();
@endphp

@section('content')
<h1>Trainer time logs</h1>
<p class="page-lede">{{ $read ? 'Trainers’ signed weeks, read as proof of work.' : 'Every week a trainer has signed, and what the desk owes them.' }}</p>

<div class="stat-row">
    <div class="stat"><small>Weeks on record</small><b>{{ $weeks->count() }}</b></div>
    <div class="stat"><small>Waiting on this desk</small><b>{{ $counts['submitted'] ?? 0 }}</b></div>
    <div class="stat"><small>Approved</small><b>{{ $counts['approved'] ?? 0 }}</b></div>
    <div class="stat"><small>Hours logged</small><b>{{ number_format($hours, 2) }}</b></div>
</div>

<div class="tabs">
    @foreach ($tabs as $s => $lbl)
        <a class="tab {{ $status === $s ? 'on' : '' }}" href="{{ route($self, $here + ['status' => $s]) }}">
            {{ $lbl }}@if ($s !== 'all')<span class="n">{{ $counts[$s] ?? 0 }}</span>@endif
        </a>
    @endforeach
</div>

@if ($weeks->isEmpty())
    <div class="card">
        <p class="span-note">{{ $read ? 'No trainer has signed a week yet.' : 'Nothing is signed yet. A trainer fills their days on their own week sheet and signs the week; it arrives here for this desk to approve.' }}</p>
    </div>
@else
    <div class="table-scroll">
        <table class="data">
            <thead>
                <tr>
                    <th>Trainer</th><th>Site</th><th>Week</th><th class="num">Days</th><th class="num">Hours</th>
                    <th class="num">Units</th><th>Status</th><th></th>
                </tr>
            </thead>
            <tbody>
                @foreach ($weeks as $week)
                    @php $t = $week['trainer']; @endphp
                    <tr>
                        <td><b>{{ $t?->personName() ?? 'A removed posting' }}</b>
                            <small>{{ $t?->holderLabel() }}@if ($t?->holderCode()) · {{ $t->holderCode() }} @endif</small></td>
                        <td>{{ $t?->placeLabel() ?: '—' }}</td>
                        <td class="nowrap">{{ $week['label'] }}</td>
                        <td class="num">{{ $week['days'] }}</td>
                        <td class="num">{{ number_format((float) $week['hours'], 2) }}</td>
                        <td class="num">{{ (int) $week['units'] }}</td>
                        <td><span class="status {{ ['draft' => 'closed', 'submitted' => 'pending_review',
                                'approved' => 'active', 'returned' => 'suspended'][$week['status']] ?? 'closed' }}">
                                {{ TimeLog::STATUSES[$week['status']] ?? $week['status'] }}</span></td>
                        <td class="actions"><a class="btn btn-sm btn-outline"
                            href="{{ Departments::inside('admin.timelogs.week', [$t, $week['start']->toDateString()]) }}">Open</a></td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@endif
@endsection
