@extends('layouts.admin')
@section('title', 'Leave')

@php
    use App\Models\LeaveRequest;
    use App\Support\Departments;
    use App\Support\Leave;

    $tabs = ['' => 'All applications', 'pending' => 'Waiting on this desk', 'approved' => 'Approved',
        'rejected' => 'Declined', 'cancelled' => 'Withdrawn'];
    $current = (string) request('status', '');
    $self = request()->route()->getName();
    $here = $read ? ['department' => Departments::currentSlug()] : [];
    $statusClass = ['pending' => 'pending_review', 'approved' => 'active', 'rejected' => 'rejected', 'cancelled' => 'closed'];
    $awayToday = $rows->filter(fn ($r) => $r->status === 'approved'
        && $r->starts_on->lte(today()) && $r->ends_on->gte(today()));
@endphp

@section('content')
<div class="head-row">
    <div>
        <h1>Leave</h1>
        <p class="ah-meta">
            <span><b>{{ $year }}</b> register</span>
            <span>{{ $rows->count() }} {{ Str::plural('application', $rows->count()) }}</span>
            <span>{{ $waiting->count() }} waiting</span>
            <span>{{ number_format($rows->where('status', 'approved')->sum('days'), 0) }} days approved</span>
            <span>{{ $awayToday->count() }} away today</span>
        </p>
    </div>
    <div class="head-actions">
        @foreach ($years as $y)
            <a class="btn btn-sm btn-outline {{ $y === $year ? 'btn-lime' : '' }}"
               href="{{ route($self, $here + ['year' => $y]) }}">{{ $y }}</a>
        @endforeach
        <button class="btn btn-sm btn-ghost" type="button" onclick="window.print()">Print</button>
    </div>
</div>

@if ($read)
    <p class="page-lede">Who of this desk’s people is on leave, read here as their own records.</p>
@endif

<form method="GET" action="{{ route($self, $here) }}" class="filter-bar inline-filter">
    <label class="field">
        <span>Leave status</span>
        <select name="status" onchange="this.form.submit()">
            @foreach ($tabs as $s => $lbl)
                <option value="{{ $s }}" @selected($current === $s)>{{ $lbl }}@if ($s !== '') ({{ $rows->where('status', $s)->count() }})@endif</option>
            @endforeach
        </select>
    </label>
    <noscript><button class="btn btn-sm btn-outline" type="submit">Apply</button></noscript>
</form>

<div class="card" style="margin-bottom:22px;">
    <div class="section-head"><h3>What each type gives</h3></div>
    <div class="table-scroll">
        <table class="data">
            <thead>
                <tr><th>Type</th><th class="num">Days a year</th><th>How it is counted</th><th class="num">Applied</th>
                    <th class="num">Approved</th></tr>
            </thead>
            <tbody>
                @foreach ($types as $slug => $t)
                    @php $ofType = $rows->where('type', $slug); @endphp
                    <tr>
                        <td><b>{{ $t['name'] }}</b><small>{{ $t['note'] }}</small></td>
                        <td class="num">{{ Leave::format((float) $t['days']) }}</td>
                        <td>{{ $slug === 'annual' ? '1.75 days for every month worked, 21 when the year is complete'
                            : 'Given in full for the year' }}</td>
                        <td class="num">{{ $ofType->count() }}</td>
                        <td class="num">{{ Leave::format((float) $ofType->where('status', 'approved')->sum('days')) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    @unless ($read)
        <p class="hint">Every one of these is answered by this desk — Human Resources approves or declines. Nothing here
            is booked by a department on someone’s behalf.</p>
    @endunless
</div>

@if ($rows->isEmpty())
    <div class="card">
        <p class="page-lede">{{ $year }} has no leave applications on the register.</p>
    </div>
@else
    <div class="table-scroll">
        <table class="data">
            <thead>
                <tr>
                    <th>Who</th><th>Type</th><th>Dates</th><th class="num">Days</th><th>Cover &amp; contact</th>
                    <th>Reason</th><th>Status</th>@unless ($read)<th>Decision</th>@endunless
                </tr>
            </thead>
            <tbody>
                @foreach ($rows as $row)
                    <tr>
                        <td><b>{{ $row->workerLabel() }}</b>
                            <small>{{ $row->holderKind() }}@if ($row->user?->email) · {{ $row->user->email }} @endif</small></td>
                        <td>{{ $row->typeLabel() }}</td>
                        <td class="nowrap">{{ $row->periodLabel() }}</td>
                        <td class="num">{{ Leave::format($row->days) }}</td>
                        <td>{{ $row->cover_for ?: '—' }}@if ($row->phone)<small>{{ $row->phone }}</small>@endif</td>
                        <td class="text">{{ $row->reason ?: '—' }}</td>
                        <td><span class="status {{ $statusClass[$row->status] ?? 'closed' }}">{{ $row->statusLabel() }}</span>
                            @if ($row->decision_note)<small>{{ $row->decision_note }}</small>@endif
                            @if ($row->decidedBy)<small>{{ $row->decidedBy->name }} ·
                                {{ $row->decided_at?->format('d M Y') }}</small>@endif</td>
                        @unless ($read)
                            <td class="actions">
                                @if ($row->isPending())
                                    <form method="POST" action="{{ route('admin.leave.decide', $row->id) }}"
                                          class="inline-form mini-edit" data-title="Leave decision"
                                          data-confirm="Approve or decline {{ $row->workerLabel() }}’s {{ $row->typeLabel() }}?">
                                        @csrf
                                        <select name="decision" aria-label="Decision" required>
                                            <option value="approved">Approve</option>
                                            <option value="rejected">Decline</option>
                                        </select>
                                        <input type="text" name="note" maxlength="500"
                                               placeholder="Needed only when declining">
                                        <button class="btn btn-sm btn-outline" type="submit">Send</button>
                                    </form>
                                @else
                                    <span class="hint">answered</span>
                                @endif
                            </td>
                        @endunless
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@endif
@endsection
