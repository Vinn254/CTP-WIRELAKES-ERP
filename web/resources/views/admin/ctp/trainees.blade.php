@extends('layouts.admin')
@section('title', 'CTP trainee interface')

@php
    use App\Models\CtpRegistration;
@endphp

@section('content')
<p class="back"><a href="{{ route('admin.department', 'trainee') }}">&larr; CTP Trainee Interface</a></p>
<h1>Open a CTP interface</h1>
<p class="sub" style="color:var(--muted);margin:6px 0 16px;">
    Pick a promoter and see <b>exactly what they see</b> — training, mobilisation, communities and their project board.
    This is a staff window: nothing here touches their account, and a CTP who signs in never sees any of these admin screens.
</p>

<div class="stat-row">
    <div class="stat"><small>Can sign in now</small><b>{{ $accounts }}</b></div>
    <div class="stat"><small>Active CTPs</small><b>{{ CtpRegistration::where('status', 'active')->count() }}</b></div>
    <div class="stat"><small>Deployed</small><b>{{ CtpRegistration::where('status', 'deployed')->count() }}</b></div>
    <div class="stat"><small>Not signed up yet</small><b>{{ CtpRegistration::whereNull('user_id')->count() }}</b></div>
</div>

<form method="GET" class="filter-bar">
    <input type="search" name="q" value="{{ $search }}" placeholder="Search CTP ID or name">
    <button class="btn btn-sm btn-outline" type="submit">Search</button>
    @if ($search)<a class="btn btn-sm btn-ghost" href="{{ route('admin.trainee.index') }}">Clear</a>@endif
</form>

<table class="data">
    <thead>
        <tr><th>CTP ID</th><th>Name</th><th>Track</th><th>County</th><th>Mobilised</th><th>Portal account</th><th>Status</th><th></th></tr>
    </thead>
    <tbody>
        @forelse ($ctps as $c)
            <tr>
                <td><b>{{ $c->ctp_code ?: '—' }}</b></td>
                <td><b>{{ $c->full_name }}</b><div class="hint">{{ $c->phone }}</div></td>
                <td><span class="tag">{{ Str::headline($c->track) }}</span></td>
                <td>{{ $c->county }}</td>
                <td>{{ $c->mobilised_count }}</td>
                <td>
                    @if ($c->user)
                        <span class="status {{ $c->user->active ? 'active' : 'suspended' }}">{{ $c->user->active ? 'can sign in' : 'locked' }}</span>
                        <div class="hint">{{ $c->user->email }}</div>
                    @else
                        <span class="hint">no account — approval issues the activation link</span>
                    @endif
                </td>
                <td><span class="status {{ $c->status }}">{{ $c->statusLabel() }}</span></td>
                <td style="text-align:right;white-space:nowrap;">
                    <a class="btn btn-sm btn-primary" href="{{ route('admin.trainee.show', $c) }}">Open interface</a>
                    <a class="btn btn-sm btn-ghost" href="{{ route('admin.promoters.show', $c) }}">Profile</a>
                </td>
            </tr>
        @empty
            <tr><td colspan="8" style="text-align:center;color:var(--muted);padding:28px;">No CTPs match that search.</td></tr>
        @endforelse
    </tbody>
</table>

<div class="pagination">{{ $ctps->links() }}</div>
@endsection
