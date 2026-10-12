@extends('layouts.admin')
@section('title', $label)

@php
    $tabs = ['ctp' => 'CTPs', 'fundi' => 'Fundis', 'hirer' => 'Hirers'];
    $statuses = ['pending_review' => 'Pending review', 'active' => 'Approved', 'rejected' => 'Rejected', 'all' => 'All'];
@endphp

@section('content')
<p><a href="{{ route('admin.dashboard') }}">← Departments</a></p>
<h1>{{ $label }}</h1>

<div style="display:flex;gap:10px;flex-wrap:wrap;margin:14px 0;">
    @foreach ($tabs as $t => $lbl)
        <a class="btn btn-sm {{ $t === $type ? 'btn-primary' : 'btn-ghost' }}" href="{{ route('admin.registrations.index', $t) }}">{{ $lbl }}</a>
    @endforeach
    <span style="flex:1;"></span>
    @foreach ($statuses as $s => $lbl)
        <a class="btn btn-sm {{ $status === $s ? 'btn-lime' : 'btn-ghost' }}" href="{{ route('admin.registrations.index', ['type' => $type, 'status' => $s]) }}">{{ $lbl }}</a>
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
            @if ($type === 'hirer')<th>Organisation</th>@endif
            <th>County</th>
            <th>Status</th>
            <th>Submitted</th>
            <th></th>
        </tr>
    </thead>
    <tbody>
        @forelse ($records as $r)
            <tr>
                <td>{{ $r->reference }}</td>
                <td>{{ $r->full_name ?? $r->contact_name }}</td>
                <td>{{ $r->phone }}</td>
                @if ($type === 'fundi')<td>{{ \Illuminate\Support\Str::headline($r->tier) }}</td>@endif
                @if ($type === 'ctp')<td>{{ \Illuminate\Support\Str::headline($r->track) }}</td>@endif
                @if ($type === 'hirer')<td>{{ $r->organisation }}</td>@endif
                <td>{{ $r->county ?? implode(', ', array_slice((array) $r->service_counties, 0, 2)) }}@if($type === 'fundi' && count((array) $r->service_counties) > 2) +{{ count((array) $r->service_counties) - 2 }}@endif</td>
                <td><span class="status {{ $r->status }}">{{ str_replace('_', ' ', $r->status) }}</span></td>
                <td>{{ $r->created_at->format('d M Y') }}</td>
                <td><a class="btn btn-sm btn-outline" href="{{ route('admin.registrations.show', [$type, $r->id]) }}">Review</a></td>
            </tr>
        @empty
            <tr><td colspan="10" style="text-align:center;color:var(--muted);padding:28px;">No {{ str_replace('_', ' ', $status) }} applications yet.</td></tr>
        @endforelse
    </tbody>
</table>

<div class="pagination">{{ $records->links() }}</div>
@endsection
