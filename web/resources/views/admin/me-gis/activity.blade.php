@extends('layouts.admin')
@section('title', 'Activity trail')

@section('content')
<h1>Who did what, when</h1>
<p class="page-lede">Read-only log of who changed each record and when.</p>

<div class="stat-row">
    <div class="stat"><small>Lines on the trail</small><b>{{ number_format($total) }}</b></div>
    <div class="stat"><small>Account lines (sign-ins, codes, links)</small><b>{{ number_format($accountLines) }}</b></div>
    <div class="stat"><small>Showing</small><b>{{ number_format($entries->total()) }}</b></div>
    <div class="stat"><small>On this page</small><b>{{ $entries->count() }}</b></div>
</div>

<form method="GET" action="{{ dept_link('admin.activity.index') }}" class="filter-bar">
    <div>
        <label for="scope">Which lines</label>
        <select name="scope" id="scope">
            <option value="all" @selected($scope === 'all')>Everything</option>
            <option value="programme" @selected($scope === 'programme')>Decisions on records</option>
            <option value="account" @selected($scope === 'account')>Accounts: sign-ins, codes, reset links</option>
        </select>
    </div>
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
        <input type="search" name="q" id="q" value="{{ $search }}" placeholder="Reference, name, what changed or IP" aria-label="Search: Reference, name, what changed or IP">
    </div>
    <button class="btn btn-sm btn-outline" type="submit">Filter</button>
    @if ($action !== 'all' || $user || $subject !== 'all' || $scope !== 'all' || $search !== '')
        <a class="btn btn-sm btn-ghost" href="{{ dept_link('admin.activity.index') }}">Clear</a>
    @endif
    <a class="btn btn-sm btn-ghost" href="{{ dept_link('admin.activity.export', request()->query()) }}">Download these lines (CSV)</a>
</form>

<table class="data">
    <thead><tr><th>When</th><th>Who</th><th>Did</th><th>To which record</th><th>What changed</th><th>From where</th></tr></thead>
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
                <td><span class="hint">{{ $a->ip ?: '—' }}</span></td>
            </tr>
        @empty
            <tr><td colspan="6" class="empty-cell">
                Nothing recorded against these filters yet. The trail starts from the first change made after
                Module 12 was opened, so older records show only what has been touched since.
            </td></tr>
        @endforelse
    </tbody>
</table>

{{ $entries->links() }}
@endsection
