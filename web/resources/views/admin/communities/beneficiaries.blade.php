@extends('layouts.admin')
@section('title', 'Beneficiaries')

@php
    use App\Models\Beneficiary;
    $tabs = ['all' => 'All'] + Beneficiary::TYPES;
@endphp

@section('content')
<h1>Beneficiaries</h1>
<p class="page-lede">The people, households and businesses the programme's projects reached.</p>

<div class="stat-row">
    <div class="stat"><small>Registered</small><b>{{ $counts->sum() }}</b></div>
    <div class="stat"><small>Students</small><b>{{ $counts['student'] ?? 0 }}</b></div>
    <div class="stat"><small>Households</small><b>{{ $counts['household'] ?? 0 }}</b></div>
    <div class="stat"><small>Businesses</small><b>{{ ($counts['business'] ?? 0) + ($counts['organisation'] ?? 0) }}</b></div>
</div>

@if ($community)
    <p class="filter-note">
        Filtered to <b>{{ $community->community_code ?: $community->name }}</b> ·
        <a href="{{ route('admin.communities.show', $community) }}">open the community</a> ·
        <a href="{{ route('admin.beneficiaries.index') }}">show everyone</a>
    </p>
@endif

<form method="GET" class="filter-bar">
    <input type="search" name="q" value="{{ $search }}" placeholder="Search a name" aria-label="Search a name">
    <button class="btn btn-sm btn-outline" type="submit">Search</button>
    @if ($search)<a class="btn btn-sm btn-ghost" href="{{ route('admin.beneficiaries.index', ['type' => $type]) }}">Clear</a>@endif
</form>

<div class="tabs">
    @foreach ($tabs as $s => $lbl)
        <a class="tab {{ $type === $s ? 'on' : '' }}" href="{{ route('admin.beneficiaries.index', ['type' => $s, 'q' => $search, 'community' => $community?->id]) }}">
            {{ $lbl }}@if ($s !== 'all')<span class="n">{{ $counts[$s] ?? 0 }}</span>@endif
        </a>
    @endforeach
</div>

<table class="data">
    <thead>
        <tr>
            <th>Name</th><th>Type</th><th>Age / gender</th><th>Community</th>
            <th>Reached by</th><th>Date of benefit</th><th>Logged by</th><th></th>
        </tr>
    </thead>
    <tbody>
        @forelse ($beneficiaries as $b)
            <tr>
                <td><b>{{ $b->name }}</b>@if ($b->showsIdNumber())<div class="hint">ID on file with consent</div>@endif</td>
                <td><span class="tag">{{ $b->typeLabel() }}</span></td>
                <td>{{ $b->age ?: '—' }}<div class="hint">{{ $b->gender ? ucfirst($b->gender) : '—' }}</div></td>
                <td>
                    <a href="{{ route('admin.communities.show', $b->community_id) }}">{{ $b->community->name ?? 'Community removed' }}</a>
                    <div class="hint">{{ $b->community->county ?? '' }}</div>
                </td>
                <td>
                    @if ($b->project)
                        <a href="{{ dept_link('admin.projects.show', ['project' => $b->project_id]) }}">{{ $b->project->reference }}</a>
                    @else
                        <span class="hint">not tied</span>
                    @endif
                </td>
                <td>{{ $b->benefited_on->format('d M Y') }}</td>
                <td>
                    @if ($b->creator)
                        <a href="{{ dept_link('admin.promoters.show', ['ctp' => $b->created_by_ctp]) }}">{{ $b->creator->full_name }}</a>
                        <div class="hint">{{ $b->creator->ctp_code }}</div>
                    @else
                        {{ $b->staffUser->name ?? 'staff' }}
                    @endif
                </td>
                <td class="actions">
                    <form method="POST" action="{{ route('admin.beneficiaries.delete', $b) }}" class="mini-form"
                          data-confirm="Remove {{ $b->name }} from the beneficiary count?" data-title="Remove beneficiary">
                        @csrf
                        @method('DELETE')
                        <button class="btn btn-sm btn-ghost" type="submit">Remove</button>
                    </form>
                </td>
            </tr>
        @empty
            <tr><td colspan="8" class="empty-cell">No beneficiaries recorded in this view yet.</td></tr>
        @endforelse
    </tbody>
</table>

<div class="pagination">{{ $beneficiaries->links() }}</div>
@endsection
