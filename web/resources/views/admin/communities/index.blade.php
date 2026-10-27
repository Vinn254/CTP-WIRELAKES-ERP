@extends('layouts.admin')
@section('title', 'Community register')

@php
    use App\Models\Community;
    $tabs = ['all' => 'All'] + Community::STATUSES;
@endphp

@section('content')
<h1>Community register</h1>
<p class="page-lede">Every community the programme works in and its status.</p>

<div class="stat-row">
    <div class="stat"><small>Communities</small><b>{{ $counts->sum() }}</b></div>
    <div class="stat"><small>Awaiting review</small><b>{{ $counts['pending_review'] ?? 0 }}</b></div>
    <div class="stat"><small>Confirmed</small><b>{{ $counts['active'] ?? 0 }}</b></div>
    <div class="stat"><small>Needs flagged</small><b>{{ $proposedNeeds }}</b></div>
</div>

<form method="GET" class="filter-bar">
    <input type="search" name="q" value="{{ $search }}" placeholder="Search COM ID, community, ward or county" aria-label="Search COM ID, community, ward or county">
    <button class="btn btn-sm btn-outline" type="submit">Search</button>
    @if ($search)<a class="btn btn-sm btn-ghost" href="{{ route('admin.communities.index', ['status' => $status]) }}">Clear</a>@endif
    <a class="btn btn-sm btn-primary" href="{{ route('admin.communities.create') }}">Add community</a>
</form>

<div class="tabs">
    @foreach ($tabs as $s => $lbl)
        <a class="tab {{ $status === $s ? 'on' : '' }}" href="{{ route('admin.communities.index', ['status' => $s, 'q' => $search]) }}">
            {{ $lbl }}@if ($s !== 'all')<span class="n">{{ $counts[$s] ?? 0 }}</span>@endif
        </a>
    @endforeach
</div>

<table class="data">
    <thead>
        <tr>
            <th>COM ID</th><th>Community</th><th>Location</th><th>CTPs mobilising</th>
            <th>People</th><th>Needs</th><th>Beneficiaries</th><th>Projects</th><th>Status</th><th></th>
        </tr>
    </thead>
    <tbody>
        @forelse ($communities as $c)
            @php
                $lead = $c->ctps->firstWhere('pivot.role', 'lead');
                $others = $c->ctps->reject(fn ($t) => $t->pivot->role === 'lead');
            @endphp
            <tr>
                <td><b>{{ $c->community_code ?: '—' }}</b>@if (! $c->community_code)<div class="hint">issued on approval</div>@endif</td>
                <td>
                    <a href="{{ route('admin.communities.show', $c) }}"><b>{{ $c->name }}</b></a>
                    @if ($c->village)<div class="hint">{{ $c->village }}</div>@endif
                </td>
                <td>{{ $c->county }}<div class="hint">{{ collect([$c->sub_county, $c->ward])->filter()->join(', ') }}</div></td>
                <td>
                    @if ($lead)
                        <a href="{{ dept_link('admin.promoters.show', ['ctp' => $lead]) }}">{{ $lead->full_name }}</a>
                        <div class="hint">lead · {{ $lead->ctp_code }}</div>
                    @elseif ($others->isNotEmpty())
                        <span class="hint">no lead named</span>
                    @else
                        <span class="hint">not assigned</span>
                    @endif
                    @if ($others->isNotEmpty())
                        <div class="hint">{{ $others->count() }} more mobilising</div>
                    @endif
                </td>
                <td>{{ $c->population ? number_format($c->population) : '—' }}<div class="hint">{{ $c->households ? number_format($c->households).' hh' : 'hh —' }}</div></td>
                <td>{{ $c->needs_count }}</td>
                <td>{{ $c->beneficiaries_count }}</td>
                <td>{{ $c->projects_count }}</td>
                <td><span class="status {{ $c->status }}">{{ $c->statusLabel() }}</span></td>
                <td class="actions"><a class="btn btn-sm btn-outline" href="{{ route('admin.communities.show', $c) }}">Open</a></td>
            </tr>
        @empty
            <tr><td colspan="10" class="empty-cell">No communities in this view yet.</td></tr>
        @endforelse
    </tbody>
</table>

<div class="pagination">{{ $communities->links() }}</div>
@endsection
