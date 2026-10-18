@extends('layouts.admin')
@section('title', 'Needs assessment')

@php
    use App\Models\Need;
    $tabs = ['proposed' => 'Awaiting review', 'prioritised' => 'Prioritised', 'converted' => 'Converted', 'closed' => 'Closed', 'all' => 'All'];
@endphp

@section('content')
<p class="back"><a href="{{ route('admin.department', 'communities') }}">&larr; Community Management</a></p>
<h1>Needs assessment</h1>
<p class="sub page-lede">
    Step 5 of the assessment workflow: the programme office reviews what CTPs pinned in the field, sets the priority
    and flags the funded ones for the project pipeline. <b>Converting a need drafts its project without leaving this page.</b>
</p>

<div class="stat-row">
    <div class="stat"><small>Awaiting review</small><b>{{ $counts['proposed'] ?? 0 }}</b></div>
    <div class="stat"><small>Prioritised</small><b>{{ $counts['prioritised'] ?? 0 }}</b></div>
    <div class="stat"><small>Converted</small><b>{{ $counts['converted'] ?? 0 }}</b></div>
    <div class="stat"><small>Closed</small><b>{{ $counts['closed'] ?? 0 }}</b></div>
</div>

<form method="GET" class="filter-bar">
    <input type="search" name="q" value="{{ $search }}" placeholder="Search a need, its detail, community or county" aria-label="Search a need, its detail, community or county">
    <span class="fld"><label for="type">Need type</label>
    <select name="type" id="type">
        <option value="all">All need types</option>
        @foreach ($types as $key => $label)
            <option value="{{ $key }}" @selected($type === $key)>{{ $label }}</option>
        @endforeach
    </select>
    </span>
    <button class="btn btn-sm btn-outline" type="submit">Filter</button>
    @if ($search || $type !== 'all')
        <a class="btn btn-sm btn-ghost" href="{{ route('admin.needs.index', ['status' => $status]) }}">Clear</a>
    @endif
</form>

<div class="tabs">
    @foreach ($tabs as $s => $lbl)
        <a class="tab {{ $status === $s ? 'on' : '' }}" href="{{ route('admin.needs.index', ['status' => $s, 'q' => $search, 'type' => $type]) }}">
            {{ $lbl }}@if ($s !== 'all')<span class="n">{{ $counts[$s] ?? 0 }}</span>@endif
        </a>
    @endforeach
</div>

@forelse ($needs as $need)
    <div class="card need-row">
        <div class="row-line">
            <b>{{ $need->title }}</b>
            <span class="tag">{{ Need::typeLabel($need->type) }}</span>
            <span class="status {{ $need->status }}">{{ $need->statusLabel() }}</span>
            <a class="hint" href="{{ route('admin.communities.show', $need->community_id) }}">
                {{ $need->community->community_code ?: $need->community->name }} · {{ $need->community->county }}
            </a>
            <span class="hint">{{ $need->priorityLabel() }} priority · flagged by
                {{ $need->assessor->full_name ?? 'staff' }} {{ $need->created_at->format('d M Y') }}</span>
            @if ($need->hasPin())
                <a class="doc-link" href="https://www.google.com/maps?q={{ $need->gps_lat }},{{ $need->gps_lng }}" target="_blank" rel="noopener">pin ↗</a>
            @endif
        </div>
        @if ($need->detail)<p class="help">{{ $need->detail }}</p>@endif
        @if ($need->reviewer)
            <p class="hint">Last reviewed by {{ $need->reviewer->name }} on {{ $need->reviewed_at?->format('d M Y H:i') }}.</p>
        @endif
        @include('admin.needs.actions', ['need' => $need])
    </div>
@empty
    <div class="card"><p class="hint">Nothing in this view. CTPs add needs from <b>/portal &rarr; My communities</b>.</p></div>
@endforelse

<div class="pagination">{{ $needs->links() }}</div>
@endsection
