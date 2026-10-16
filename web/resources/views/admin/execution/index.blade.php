@extends('layouts.admin')
@section('title', 'Delivery register')

@php
    use App\Models\WorkOrder;
    use App\Support\Finance;

    $pill = ['issued' => 'pending_review', 'accepted' => 'pending_review', 'in_progress' => 'awarded',
        'completion_submitted' => 'awarded', 'confirmed' => 'active', 'disputed' => 'rejected',
        'closed' => 'active', 'cancelled' => 'rejected'];
@endphp

@section('content')
<p class="back"><a href="{{ route('admin.department', 'execution') }}">&larr; Project Execution &amp; Payment</a></p>
<h1>Delivery register</h1>
<p class="sub" style="color:var(--muted);margin:6px 0 16px;">
    Every work order an award has produced (§10.2). The order is generated the moment a project is given to a worker —
    on the project page or on the <a href="{{ route('admin.marketplace.index') }}">matching desk</a> — and this is where
    the rest of §10.4 is done: the store issuing materials against it, days logged on site, stages signed off, the
    hirer's inspection as the office recorded it, a dispute run to its decision, and the hand that opens the payout on
    the <a href="{{ route('admin.payouts.index') }}">Module 11 register</a>. No amount is calculated here.
</p>

<div class="stat-row">
    <div class="stat"><small>Waiting on the worker's signature</small><b>{{ $queues['awaiting'] }}</b></div>
    <div class="stat"><small>On site</small><b>{{ $queues['on_site'] }}</b></div>
    <div class="stat"><small>Waiting on the hirer's inspection</small><b>{{ $queues['inspection'] }}</b></div>
    <div class="stat"><small>Disputes open</small><b>{{ $queues['disputes'] }}</b></div>
    <div class="stat"><small>Confirmed, not yet handed to Finance</small><b>{{ $queues['unhanded'] }}</b></div>
</div>

<div class="tabs">
    @foreach ($tabs as $key => $t)
        <a class="tab {{ $tab === $key ? 'on' : '' }}"
           href="{{ route('admin.execution.index', array_filter(['tab' => $key, 'q' => $search])) }}">
            {{ $t['label'] }} <span class="hint">({{ $counts[$key] ?? 0 }})</span>
        </a>
    @endforeach
</div>

<form method="GET" action="{{ route('admin.execution.index') }}" class="filter-bar">
    <input type="hidden" name="tab" value="{{ $tab }}">
    <div>
        <label for="q">Order, worker or project</label>
        <input type="search" name="q" id="q" value="{{ $search }}" placeholder="WO-2026-00001, a name, or PRJ-">
    </div>
    <button class="btn btn-sm btn-outline" type="submit">Search</button>
    @if ($search)<a class="btn btn-sm btn-ghost" href="{{ route('admin.execution.index', ['tab' => $tab]) }}">Clear</a>@endif
    <span class="hint">Orders that are waiting on somebody come first — the worker, the hirer, or this desk.</span>
</form>

<div class="card">
    <table class="data">
        <thead>
            <tr>
                <th>Work order</th><th>Project</th><th>Worker</th><th>Schedule</th>
                <th>Agreed</th><th>On site</th><th>Stages signed</th><th>Next</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($orders as $o)
                <tr>
                    <td>
                        <a href="{{ route('admin.execution.show', $o) }}"><b>{{ $o->reference }}</b></a>
                        <div><span class="status {{ $pill[$o->status] ?? 'pending_review' }}">{{ $o->statusLabel() }}</span></div>
                    </td>
                    <td>
                        {{ $o->project->title }}
                        <div class="hint">{{ $o->project->reference }} · {{ $o->project->county ?: 'place not set' }}
                            · {{ $o->project->hirer->organisation ?: 'no hirer named' }}</div>
                    </td>
                    <td>
                        {{ $o->assignee_name ?: '—' }}
                        <div class="hint">{{ $o->assigneeLabel() }}
                            @if ($o->assignee_type === 'fundi' && $o->fundi->exists)
                                · {{ $o->fundi->ef_code ?: 'no EF code' }}
                            @endif
                        </div>
                    </td>
                    <td>
                        {{ \App\Models\WorkOrder::SCHEDULES[$o->schedule] ?? $o->schedule }}
                        <div class="hint">{{ $o->splitLabel() }}</div>
                    </td>
                    <td>{{ $o->agreed_amount_ksh !== null ? Finance::ksh((float) $o->agreed_amount_ksh) : '—' }}</td>
                    <td>{{ $o->percent_complete }}%
                        <div class="hint">{{ $o->start_on?->format('d M Y') ?? 'not started' }}
                            → {{ $o->deadline?->format('d M Y') ?? 'no deadline' }}</div>
                    </td>
                    <td>{{ $o->milestones->where('status', \App\Models\WorkOrderMilestone::SIGNED)->count() }}
                        of {{ $o->milestones->count() }}</td>
                    <td class="hint">
                        @if ($o->disputes->isNotEmpty())
                            <span class="tag">dispute open</span>
                        @elseif ($o->status === 'issued')
                            waiting for {{ $o->assignee_name ?: 'the worker' }} to sign
                        @elseif ($o->status === 'completion_submitted')
                            the hirer has to inspect
                        @elseif ($o->status === 'confirmed')
                            hand to Finance
                        @else
                            {{ $o->closed_on ? 'ended '. $o->closed_on->format('d M Y') : 'running' }}
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="8" style="text-align:center;color:var(--muted);padding:22px;">
                    No work order in this state yet. An order appears the moment a project is awarded to a worker
                    (§10.2) — award one on the <a href="{{ route('admin.marketplace.index') }}">matching desk</a>
                    or on <a href="{{ route('admin.projects.index') }}">Projects</a>.
                </td></tr>
            @endforelse
        </tbody>
    </table>
    <div class="pagination">{{ $orders->links() }}</div>
    <p class="hint">
        A stopped or closed order stays on this register with its reason (§10.4 step 12). Re-awarding a project to
        another worker does not rewrite the first order — it is marked stopped and the new worker gets their own.
    </p>
</div>
@endsection
