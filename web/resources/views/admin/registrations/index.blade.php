@extends('layouts.admin')
@section('title', $label)

@php
    use Illuminate\Support\Str;
    $types = ['ctp' => 'CTP', 'fundi' => 'E-Fundi'];
    // Applicants who have not paid the one-time fee yet are not reviewable, so they get their own tab;
    // the rest of the CTP lifecycle (suspended, graduated, deployed) is managed in Module 2 — CTP profiles.
    $waiting = ['pending_payment' => 'Awaiting fee'];
    $statuses = match ($type) {
        'ctp' => $waiting + ['pending_review' => 'Pending', 'active' => 'Approved', 'all' => 'All'],
        'fundi' => $waiting + ['pending_review' => 'Pending', 'active' => 'Approved', 'rejected' => 'Rejected', 'all' => 'All'],
        default => ['pending_review' => 'Pending', 'active' => 'Approved', 'rejected' => 'Rejected', 'all' => 'All'],
    };
@endphp

@section('content')
<h1>{{ $label }}</h1>
<p class="page-lede">Review and approve new CTP and E-Fundi applications.</p>

<div class="tabs">
    @foreach ($types as $t => $lbl)
        <a class="tab {{ $t === $type ? 'on' : '' }}" href="{{ route('admin.registrations.index', $t) }}">
            {{ $lbl }}
            @if ($pendingByType[$t] ?? 0)<span class="n" title="{{ $pendingByType[$t] }} pending">{{ $pendingByType[$t] }}</span>@endif
        </a>
    @endforeach
</div>

<div class="tabs">
    @foreach ($statuses as $s => $lbl)
        <a class="tab {{ $status === $s ? 'on' : '' }}" href="{{ route('admin.registrations.index', ['type' => $type, 'status' => $s]) }}">
            {{ $lbl }}@if ($s !== 'all')<span class="n">{{ $counts[$s] ?? 0 }}</span>@endif
        </a>
    @endforeach
</div>

<table class="data">
    <thead>
        <tr>
            <th>Reference</th>
            <th>Name</th>
            <th>Phone</th>
            @if ($type === 'fundi')<th>Tier</th>@endif
            @if ($type === 'ctp')<th>Track</th>@endif
            <th>County</th>
            <th>Assigned projects</th>
            <th>Status</th>
            <th>Submitted</th>
            <th></th>
        </tr>
    </thead>
    <tbody>
        @forelse ($records as $r)
            <tr>
                <td><b>{{ $r->reference }}</b></td>
                <td>
                    {{ $r->full_name ?? $r->contact_name }}
                    <div class="hint">{{ $r->email }}</div>
                </td>
                <td>{{ $r->phone }}</td>
                @if ($type === 'fundi')<td><span class="tag">{{ \App\Models\FundiRegistration::tierLabel($r->tier) }}</span></td>@endif
                @if ($type === 'ctp')<td><span class="tag">{{ Str::headline($r->track) }}</span>
                    @if ($r->fundi_tier)<div class="hint">reads as {{ $r->gradeLabel() }}</div>@endif</td>@endif
                <td>{{ $r->county ?? implode(', ', array_slice((array) $r->service_counties, 0, 2)) }}@if ($type === 'fundi' && count((array) $r->service_counties) > 2)<span class="hint"> +{{ count((array) $r->service_counties) - 2 }}</span>@endif</td>
                <td>
                    @if ($assigned[$r->id] ?? false)
                        @foreach ($assigned[$r->id] as $a)
                            <a class="mini-chip {{ $a->status === 'approved' ? 'chip-award' : '' }}" href="{{ route('admin.projects.show', $a->project_id) }}">
                                {{ $a->project->reference ?? 'Project deleted' }}@if ($a->status !== 'approved') · {{ str_replace('_', ' ', $a->status) }}@endif
                            </a>
                        @endforeach
                    @else
                        <span class="hint">None yet</span>
                    @endif
                </td>
                <td><span class="status {{ $r->status }}">{{ method_exists($r, 'statusLabel') ? $r->statusLabel() : str_replace('_', ' ', $r->status) }}</span></td>
                <td>{{ $r->created_at->format('d M Y') }}</td>
                <td class="actions">
                    <a class="btn btn-sm btn-outline" href="{{ route('admin.registrations.show', [$type, $r->id]) }}">Review</a>
                    @if ($type === 'ctp')<a class="btn btn-sm btn-ghost" href="{{ dept_link('admin.promoters.show', ['ctp' => $r->id]) }}">Profile</a>@endif
                </td>
            </tr>
        @empty
            <tr><td colspan="10" class="empty-cell">No {{ str_replace('_', ' ', $status) }} applications yet.</td></tr>
        @endforelse
    </tbody>
</table>

<div class="pagination">{{ $records->links() }}</div>
@endsection
