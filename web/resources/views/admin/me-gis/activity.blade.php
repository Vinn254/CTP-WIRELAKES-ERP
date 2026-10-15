@extends('layouts.admin')
@section('title', 'Activity trail')

@section('content')
<p class="back"><a href="{{ route('admin.department', 'me_gis') }}">&larr; M&amp;E, GIS and Reports</a></p>
<h1>Who did what, when</h1>
<p class="sub" style="color:var(--muted);margin:6px 0 16px;">
    §15.6 asks for a trail of every decision. It writes itself: a watched record — an application, a seat, a
    certificate, a payout, a ledger line, a stock card — puts its own line here whenever it is opened, changed or
    closed off, by whichever screen did it. <b>Nothing on this page can be edited or deleted.</b> If an entry looks
    wrong, the record behind it is corrected, and that correction writes the next line.
</p>

<div class="stat-row">
    <div class="stat"><small>Lines on the trail</small><b>{{ number_format($total) }}</b></div>
    <div class="stat"><small>Showing</small><b>{{ number_format($entries->total()) }}</b></div>
    <div class="stat"><small>On this page</small><b>{{ $entries->count() }}</b></div>
</div>

<form method="GET" action="{{ route('admin.activity.index') }}" class="filter-bar">
    <div>
        <label for="action">Action</label>
        <select name="action" id="action">
            <option value="all">Every action</option>
            @foreach ($actions as $key => $label)
                <option value="{{ $key }}" @selected($action === $key)>{{ $label }}</option>
            @endforeach
        </select>
    </div>
    <div>
        <label for="record">Kind of record</label>
        <select name="record" id="record">
            <option value="all">Any record</option>
            @foreach ($records as $class => $label)
                <option value="{{ $class }}" @selected($subject === $class)>{{ $label }}</option>
            @endforeach
        </select>
    </div>
    <div>
        <label for="user">Person</label>
        <select name="user" id="user">
            <option value="0">Anyone on the staff</option>
            @foreach ($people as $p)
                <option value="{{ $p->id }}" @selected($user === $p->id)>{{ $p->name }}</option>
            @endforeach
        </select>
    </div>
    <div style="flex:1;min-width:180px;">
        <label for="q">Search</label>
        <input type="search" name="q" id="q" value="{{ $search }}" placeholder="Reference, name or what changed">
    </div>
    <button class="btn btn-sm btn-outline" type="submit">Filter</button>
    @if ($action !== 'all' || $user || $subject !== 'all' || $search !== '')
        <a class="btn btn-sm btn-ghost" href="{{ route('admin.activity.index') }}">Clear</a>
    @endif
</form>

<table class="data">
    <thead><tr><th>When</th><th>Who</th><th>Did</th><th>To which record</th><th>What changed</th></tr></thead>
    <tbody>
        @forelse ($entries as $a)
            <tr>
                <td>{{ $a->whenLabel() }}</td>
                <td><b>{{ $a->whoLabel() }}</b>
                    @if ($a->actor->exists && $a->actor->role === 'superadmin')
                        <div class="hint">programme executive</div>
                    @endif
                </td>
                <td>{{ $a->actionLabel() }}</td>
                <td>{{ $a->subjectName() }}</td>
                <td><span class="hint">{{ $a->detail ?: '—' }}</span></td>
            </tr>
        @empty
            <tr><td colspan="5" style="text-align:center;color:var(--muted);padding:22px;">
                Nothing recorded against these filters yet. The trail starts from the first change made after
                Module 12 was opened, so older records show only what has been touched since.
            </td></tr>
        @endforelse
    </tbody>
</table>

{{ $entries->links() }}
@endsection
