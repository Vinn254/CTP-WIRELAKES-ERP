@extends('layouts.admin')
@section('title', 'Matching desk')

@section('content')
@php use App\Support\Marketplace; @endphp
<h1>Matching desk</h1>
<p class="page-lede">Match open projects with fundis and award the work.</p>

<div class="stat-row">
    <div class="stat"><small>Open projects with no search yet</small><b>{{ $queues['unmatched'] }}</b></div>
    <div class="stat"><small>Asked, waiting for an answer</small><b>{{ $queues['waiting'] }}</b></div>
    <div class="stat"><small>Taken by the fundi</small><b>{{ $queues['accepted'] }}</b></div>
    <div class="stat"><small>Nobody answered in time</small><b>{{ $queues['quiet'] }}</b></div>
</div>

<form method="GET" action="{{ route('admin.marketplace.index') }}" class="filter-bar">
    <label class="field">
        <span>Match status</span>
        <select name="tab" onchange="this.form.submit()">
            @foreach (['all' => 'Every open project', 'unmatched' => 'Needs a search', 'waiting' => 'Answers outstanding', 'answered' => 'Someone took it'] as $key => $label)
                <option value="{{ $key }}" @selected($tab === $key)>{{ $label }}</option>
            @endforeach
        </select>
    </label>
    <div>
        <label for="county">County</label>
        <select name="county" id="county">
            <option value="">Every county</option>
            @foreach ($counties as $c)
                <option value="{{ $c }}" @selected($county === $c)>{{ $c }}</option>
            @endforeach
        </select>
    </div>
    <button class="btn btn-sm btn-outline" type="submit">Filter</button>
    @if ($county)<a class="btn btn-sm btn-ghost" href="{{ route('admin.marketplace.index', ['tab' => $tab]) }}">Clear county</a>@endif
    <span class="hint">Ordered by the deadline it has to be done by.</span>
</form>

<div class="card">
    <table class="data">
        <thead>
            <tr>
                <th>Reference</th><th>Project</th><th>Trade typed</th><th>County</th><th>Deadline</th>
                <th>Applied</th><th>Shortlisted</th><th>Asked</th><th>Took it</th><th>No answer</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($projects as $p)
                <tr>
                    <td><a href="{{ route('admin.marketplace.show', $p) }}"><b>{{ $p->reference }}</b></a></td>
                    <td>
                        <a href="{{ route('admin.marketplace.show', $p) }}">{{ $p->title }}</a>
                        <div class="hint">{{ $p->categoryLabel() }}</div>
                    </td>
                    <td>{{ $p->trade ?: '—' }}</td>
                    <td>{{ $p->county ?: '—' }}</td>
                    <td>{{ $p->deadline?->format('d M Y') ?? 'No date' }}</td>
                    <td>{{ $p->applications_count }}</td>
                    <td>{{ $p->matches_count }}</td>
                    <td>@if ($p->invited_count) <span class="tag">{{ $p->invited_count }} outstanding</span> @else 0 @endif</td>
                    <td>@if ($p->accepted_count) <b>{{ $p->accepted_count }}</b> @else 0 @endif</td>
                    <td>{{ $p->quiet_count ?: '—' }}</td>
                </tr>
            @empty
                <tr><td colspan="10" class="empty-cell">
                    No open project in this state. A project reaches this desk when it is posted and set to
                    <b>Open for applications</b>.
                </td></tr>
            @endforelse
        </tbody>
    </table>
    <p class="hint">
        The search scores a fundi out of 100 on the skills the trade names (40), the county the work is in (25), their
        own availability (15), years in trade (10) and projects already done (10). {{ $bar }}% is the standing bar;
        going below it, or widening to neighbouring counties, is written on every round it was used for.
        The top {{ Marketplace::INVITE_TOP }} of a round are the ones asked.
    </p>
</div>
@endsection
