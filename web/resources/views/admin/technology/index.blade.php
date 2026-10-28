@extends('layouts.admin')
@section('title', 'Technology registers')

@php
    use App\Support\Finance;
    use App\Support\Technology;

    $assetPill = ['operational' => 'active', 'degraded' => 'pending_review', 'in_repair' => 'awarded', 'down' => 'rejected', 'retired' => 'gray'];
    $ticketPill = ['open' => 'pending_review', 'assigned' => 'pending_review', 'in_progress' => 'awarded', 'resolved' => 'active', 'closed' => 'gray'];
    $projPill = ['identified' => 'pending_review', 'deployed' => 'awarded', 'completed' => 'active', 'on_hold' => 'rejected'];
@endphp

@section('content')
<h1>Technology registers</h1>
<p class="page-lede">Assets, technology projects and repair tickets in one place.</p>

<div class="stat-row">
    @foreach ($queues as $label => $count)
        <div class="stat"><small>{{ $label }}</small><b>{{ $count }}</b></div>
    @endforeach
</div>

<form method="GET" action="{{ route('admin.technology.index') }}" class="filter-bar">
    <label class="field">
        <span>Register</span>
        <select name="tab" onchange="this.form.submit()">
            @foreach ($tabs as $key => $t)
                <option value="{{ $key }}" @selected($tab === $key)>{{ $t['label'] }} ({{ $counts[$key] ?? 0 }})</option>
            @endforeach
        </select>
    </label>
    <div>
        <label for="category">Sub-area</label>
        <select name="category" id="category">
            <option value="">All five sub-areas</option>
            @foreach ($categories as $key => $c)
                <option value="{{ $key }}" @selected($category === $key)>{{ $c['label'] }}</option>
            @endforeach
        </select>
    </div>
    <div>
        <label for="q">Search</label>
        <input type="search" name="q" id="q" value="{{ $search }}" placeholder="code, name, place or technology" aria-label="Search: code, name, place or technology">
    </div>
    <button class="btn btn-sm btn-outline" type="submit">Filter</button>
    @if ($search || $category)<a class="btn btn-sm btn-ghost" href="{{ route('admin.technology.index', ['tab' => $tab]) }}">Clear</a>@endif
    <span class="hint">{{ $tabs[$tab]['note'] ?? '' }}</span>
</form>

@if ($tab === 'assets')
    <p style="margin:0 0 12px;">
        <a class="btn btn-sm btn-primary" href="{{ route('admin.technology.asset.create') }}">+ Add an asset</a>
    </p>
    <div class="card">
        <table class="data">
            <thead>
                <tr><th>Asset</th><th>Sub-area</th><th>Status</th><th>Held by / where</th><th>Warranty &amp; licence</th><th>Tickets</th></tr>
            </thead>
            <tbody>
                @forelse ($rows as $a)
                    <tr>
                        <td>
                            <a href="{{ route('admin.technology.asset', $a) }}"><b>{{ $a->asset_code }}</b></a>
                            <div>{{ str($a->name)->limit(46) }}</div>
                            <div class="hint">{{ $a->kind ?: 'no kind set' }} · {{ $a->sourceLabel() }}</div>
                        </td>
                        <td>{{ $a->categoryLabel() }}</td>
                        <td><span class="status {{ $assetPill[$a->status] ?? 'pending_review' }}">{{ $a->statusLabel() }}</span></td>
                        <td>{{ $a->holderLabel() }}<div class="hint">{{ $a->county ?: '—' }}</div></td>
                        <td class="hint">
                            @if ($a->warranty_expires_on)warranty to {{ $a->warranty_expires_on->format('d M Y') }}@else warranty — @endif
                            @if ($a->licence_renews_on)<div>licence renews {{ $a->licence_renews_on->format('d M Y') }}</div>@endif
                        </td>
                        <td>
                            @php $open = $a->tickets->whereIn('status', ['open', 'assigned', 'in_progress'])->count(); @endphp
                            <a href="{{ route('admin.technology.asset', $a) }}">{{ $a->tickets()->count() }} on file</a>
                            @if ($open)<div><span class="tag">{{ $open }} open</span></div>@endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="empty-cell">
                        Nothing on the register yet. Add the first device, licence or kit — its sub-area decides which of
                        the five it counts under.
                    </td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
@elseif ($tab === 'projects')
    <p style="margin:0 0 12px;">
        <a class="btn btn-sm btn-primary" href="{{ route('admin.technology.project.create') }}">+ New technology project</a>
    </p>
    <div class="card">
        <table class="data">
            <thead>
                <tr><th>Project</th><th>Sub-area</th><th>Technology</th><th>Mentor</th><th>Community</th><th>Stage</th></tr>
            </thead>
            <tbody>
                @forelse ($rows as $p)
                    <tr>
                        <td>
                            <a href="{{ route('admin.technology.project', $p) }}"><b>{{ $p->reference }}</b></a>
                            <div>{{ str($p->title)->limit(46) }}</div>
                        </td>
                        <td>{{ $p->categoryLabel() }}</td>
                        <td>{{ $p->technology ?: '—' }}</td>
                        <td>{{ $p->mentor->full_name ?: 'no mentor' }}</td>
                        <td>{{ $p->community->name ?: '—' }}</td>
                        <td>
                            <span class="status {{ $projPill[$p->status] ?? 'pending_review' }}">{{ $p->statusLabel() }}</span>
                            <div class="hint">{{ $p->progress_percent }}%</div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="empty-cell">
                        No robotics, programming or AI work recorded yet.
                    </td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
@elseif ($tab === 'tickets')
    <div class="card">
        <table class="data">
            <thead>
                <tr><th>Ticket</th><th>Asset</th><th>Fault</th><th>Priority</th><th>Assigned to</th><th>Status</th></tr>
            </thead>
            <tbody>
                @forelse ($rows as $t)
                    <tr>
                        <td>
                            <a href="{{ route('admin.technology.asset', $t->asset) }}"><b>{{ $t->reference }}</b></a>
                            <div class="hint">opened {{ $t->created_at?->format('d M Y') }}</div>
                        </td>
                        <td>{{ $t->asset->asset_code ?? '—' }}<div class="hint">{{ str($t->asset->name ?? '')->limit(24) }}</div></td>
                        <td>{{ str($t->title)->limit(40) }}<div class="hint">{{ str($t->fault)->limit(52) }}</div></td>
                        <td><span class="tag {{ $t->priority === 'urgent' ? '' : 'gold' }}">{{ $t->priorityLabel() }}</span></td>
                        <td>{{ $t->fundi->full_name ?? 'not assigned' }}</td>
                        <td><span class="status {{ $ticketPill[$t->status] ?? 'pending_review' }}">{{ $t->statusLabel() }}</span></td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="empty-cell">
                        No repair ticket is open. Open one from any asset on the
                        <a href="{{ route('admin.technology.index', ['tab' => 'assets']) }}">asset register</a>.
                    </td></tr>
                @endforelse
            </tbody>
        </table>
        <p class="hint">A ticket is answered by the fundi on their own <b>My repair jobs</b> screen; the office confirms the fix and closes it here.</p>
    </div>
@else {{-- alerts --}}
    <div class="card">
        <table class="data">
            <thead>
                <tr><th>Asset</th><th>Sub-area</th><th>Warranty expires</th><th>Licence renews</th><th>Sooner of the two</th></tr>
            </thead>
            <tbody>
                @forelse ($rows as $a)
                    @php
                        $wDays = Technology::daysLeft($a->warranty_expires_on?->toDateString());
                        $lDays = Technology::daysLeft($a->licence_renews_on?->toDateString());
                        $soon = collect([$wDays, $lDays])->filter(fn ($d) => $d !== null)->min();
                    @endphp
                    <tr>
                        <td><a href="{{ route('admin.technology.asset', $a) }}"><b>{{ $a->asset_code }}</b></a>
                            <div class="hint">{{ str($a->name)->limit(40) }}</div></td>
                        <td>{{ $a->categoryLabel() }}</td>
                        <td>{{ $a->warranty_expires_on?->format('d M Y') ?? '—' }}@if ($wDays !== null)<div class="hint">{{ $wDays < 0 ? 'expired' : $wDays.' day(s) left' }}</div>@endif</td>
                        <td>{{ $a->licence_renews_on?->format('d M Y') ?? '—' }}@if ($lDays !== null)<div class="hint">{{ $lDays < 0 ? 'lapsed' : $lDays.' day(s) left' }}</div>@endif</td>
                        <td>
                            @if ($soon === null)<span class="hint">no date</span>
                            @elseif ($soon < 0)<span class="status rejected">run out</span>
                            @else<span class="status pending_review">{{ $soon }} day(s)</span>@endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="empty-cell">
                        Nothing runs out inside {{ Technology::ALERT_WINDOW_DAYS }} days. Record a warranty or licence
                        end-date on an asset and it shows up here in good time.
                    </td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
@endif
@endsection
