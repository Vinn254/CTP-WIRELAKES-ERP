@extends('layouts.admin')
@section('title', 'CTP profiles')

@php
    use App\Models\CtpRegistration;
    $tabs = ['all' => 'All'] + collect(CtpRegistration::STATUSES)->map(fn ($l) => $l)->all();
@endphp

@section('content')
<p class="back"><a href="{{ route('admin.department', 'promoters') }}">&larr; CTP Promoter Management</a></p>
<h1>CTP profiles</h1>
<p class="sub" style="color:var(--muted);margin:6px 0 16px;">
    Every promoter the programme has — from the person who has not paid yet to the CTP deployed on a project.
    This is the record Modules 3, 4, 5 and 12 read: <b>one person is a trainee, a mobiliser and a community agent at the same time.</b>
</p>

<div class="stat-row">
    <div class="stat"><small>Active CTPs</small><b>{{ $counts['active'] ?? 0 }}</b></div>
    <div class="stat"><small>Awaiting fee</small><b>{{ $counts['pending_payment'] ?? 0 }}</b></div>
    <div class="stat"><small>Awaiting review</small><b>{{ $counts['pending_review'] ?? 0 }}</b></div>
    <div class="stat"><small>Deployed</small><b>{{ ($counts['deployed'] ?? 0) + ($counts['graduated'] ?? 0) }}</b></div>
</div>

<p style="margin:0 0 14px;">
    <a class="btn btn-lime btn-sm" href="{{ route('admin.promoters.create') }}">+ Add a CTP from the office</a>
    <span class="hint" style="margin-left:10px;">
        For someone who cannot reach the website — the same application, the same fee and review steps.
    </span>
</p>

<form method="GET" class="filter-bar">
    <input type="search" name="q" value="{{ $search }}" placeholder="Search ID, name, phone or county">
    <button class="btn btn-sm btn-outline" type="submit">Search</button>
    @if ($search)<a class="btn btn-sm btn-ghost" href="{{ route('admin.promoters.index', ['status' => $status]) }}">Clear</a>@endif
</form>

<div class="tabs">
    @foreach ($tabs as $s => $lbl)
        <a class="tab {{ $status === $s ? 'on' : '' }}" href="{{ route('admin.promoters.index', ['status' => $s, 'q' => $search]) }}">
            {{ $lbl }}@if ($s !== 'all')<span class="n">{{ $counts[$s] ?? 0 }}</span>@endif
        </a>
    @endforeach
</div>

<table class="data">
    <thead>
        <tr>
            <th>CTP ID</th><th>Name</th><th>Track</th><th>County</th>
            <th title="People this CTP mobilised who paid and were approved">Mobilised</th>
            <th>Fee</th><th>Status</th><th>Joined</th><th></th>
        </tr>
    </thead>
    <tbody>
        @forelse ($ctps as $c)
            <tr>
                <td><b>{{ $c->ctp_code ?: '—' }}</b>@if (! $c->ctp_code)<div class="hint">not issued yet</div>@endif</td>
                <td>
                    <a href="{{ route('admin.promoters.show', $c) }}"><b>{{ $c->full_name }}</b></a>
                    <div class="hint">{{ $c->phone }} · {{ $c->email }}</div>
                </td>
                <td><span class="tag">{{ Str::headline($c->track) }}</span></td>
                <td>{{ $c->county }}<div class="hint">{{ collect([$c->sub_county, $c->ward])->filter()->join(', ') }}</div></td>
                <td>{{ $c->mobilised_count }}</td>
                <td><span class="fee-tag {{ $c->fee_status }}">{{ CtpRegistration::FEE_STATUSES[$c->fee_status] ?? $c->fee_status }}</span></td>
                <td><span class="status {{ $c->status }}">{{ $c->statusLabel() }}</span></td>
                <td>{{ $c->created_at->format('d M Y') }}</td>
                <td style="text-align:right;"><a class="btn btn-sm btn-outline" href="{{ route('admin.promoters.show', $c) }}">Open profile</a></td>
            </tr>
        @empty
            <tr><td colspan="9" style="text-align:center;color:var(--muted);padding:28px;">No CTPs in this view yet.</td></tr>
        @endforelse
    </tbody>
</table>

<div class="pagination">{{ $ctps->links() }}</div>
@endsection
