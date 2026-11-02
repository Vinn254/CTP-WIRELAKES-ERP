@extends('layouts.admin')
@php $inDesk = \App\Support\Departments::canSee(auth()->user(), 'training'); @endphp
@section('title', $inDesk ? 'Papers & library — Papers & requests' : 'Papers & requests')

@php
    use App\Support\Profile;

    $statusClass = ['pending' => 'pending_review', 'approved' => 'active', 'returned' => 'rejected',
        'rejected' => 'rejected', 'cancelled' => 'closed'];
    $waiting = $waitingDocs->count() + $waitingAsks->count();
@endphp

@section('content')
<div class="head-row">
    <div>
        <h1>Papers &amp; requests</h1>
        <p class="page-lede">Documents people send in for their file, and the profile edits they ask for.
            {{ $deskName }} answers them.</p>
    </div>
</div>

@include('partials.papers-desk', ['deskTab' => 'requests'])

<div class="stat-row">
    <div class="stat"><small>Waiting on you</small><b>{{ $waiting }}</b></div>
    <div class="stat"><small>Documents in</small><b>{{ $waitingDocs->count() }}</b></div>
    <div class="stat"><small>Asks to answer</small><b>{{ $waitingAsks->count() }}</b></div>
</div>

<div class="tabs">
    <a class="tab {{ $tab === 'waiting' ? 'on' : '' }}" href="{{ route('admin.papers.index') }}">
        Waiting <span class="n">{{ $waiting }}</span></a>
    <a class="tab {{ $tab === 'answered' ? 'on' : '' }}" href="{{ route('admin.papers.index', ['tab' => 'answered']) }}">
        Answered</a>
</div>

@if ($tab === 'waiting')
    @include('partials.profile-desk', [
        'scope' => true,
        'documents' => $waitingDocs,
        'changes' => $waitingAsks,
        'fileRoute' => 'admin.papers.file',
        'decideRoute' => 'admin.papers.document',
        'answerRoute' => 'admin.papers.change',
    ])
@else
    <h2 class="sec-title">Documents taken onto a file</h2>
    @if ($held->isEmpty())
        <div class="card"><p class="page-lede">Nothing has been answered from this desk yet.</p></div>
    @else
        <div class="table-scroll">
            <table class="data">
                <thead><tr><th>Person</th><th>Document</th><th>Status</th><th>Answered</th><th></th></tr></thead>
                <tbody>
                    @foreach ($held as $document)
                        <tr>
                            <td><b>{{ $document->personLabel() }}</b>
                                <div class="hint">{{ Profile::kindLabel($document->subject_type) }}</div></td>
                            <td>{{ $document->kindLabel() }}
                                <div class="hint">{{ $document->file_name }}</div></td>
                            <td><span class="status {{ $statusClass[$document->status] ?? 'closed' }}">{{ $document->statusLabel() }}</span></td>
                            <td class="nowrap">{{ $document->decidedBy?->name ?: '—' }}
                                <div class="hint">{{ $document->decided_at?->format('d M Y') }}</div></td>
                            <td class="actions">
                                <a class="btn btn-sm btn-outline" href="{{ route('admin.papers.file', $document) }}">Open</a>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif

    <h2 class="sec-title">Asks already answered</h2>
    @if ($answered->isEmpty())
        <div class="card"><p class="page-lede">No profile change has been answered from this desk yet.</p></div>
    @else
        <div class="table-scroll">
            <table class="data">
                <thead><tr><th>Person</th><th>Fields</th><th>Status</th><th>Answered</th></tr></thead>
                <tbody>
                    @foreach ($answered as $change)
                        <tr>
                            <td><b>{{ $change->personLabel() }}</b>
                                <div class="hint">{{ Profile::kindLabel($change->subject_type) }}</div></td>
                            <td>{{ collect($change->lines())->pluck('label')->join(', ') }}</td>
                            <td><span class="status {{ $statusClass[$change->status] ?? 'closed' }}">{{ $change->statusLabel() }}</span></td>
                            <td class="nowrap">{{ $change->decidedBy?->name ?: '—' }}
                                <div class="hint">{{ $change->decided_at?->format('d M Y') }}
                                    @if ($change->decision_note) · {{ $change->decision_note }}@endif</div></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
@endif

<p class="hint">A document is taken onto the record or sent back for a better copy; an ask is written onto the record
    or declined with the reason. Nothing here is ever deleted.</p>
@endsection
