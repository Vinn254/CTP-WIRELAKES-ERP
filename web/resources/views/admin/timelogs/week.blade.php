@extends('layouts.admin')
@section('title', 'Week of '.$sheet['label'])

@php
    use App\Models\TimeLog;
    use App\Support\Departments;

    $trainer = $sheet['trainer'];
    $read = Departments::hosted();
    $here = ['department' => Departments::currentSlug()];
    $weekOf = $sheet['start']->toDateString();
    $link = fn ($w) => Departments::inside('admin.timelogs.week', [$trainer, $w]);
    $waiting = $sheet['status'] === 'submitted';
@endphp

@section('content')
<div class="head-row">
    <div>
        <h1>{{ $trainer->personName() }}</h1>
        <p class="ah-meta">
            <span><b>{{ $sheet['label'] }}</b></span>
            <span>{{ $trainer->holderLabel() }}@if ($trainer->holderCode()) · {{ $trainer->holderCode() }} @endif</span>
            <span>{{ $trainer->placeLabel() ?: 'no site named' }}</span>
            <span>{{ count($sheet['days']) }} ruled days</span>
            <span>{{ $sheet['logs']->count() }} {{ Str::plural('day', $sheet['logs']->count()) }} written</span>
        </p>
    </div>
    <div class="head-actions">
        <a class="btn btn-sm btn-outline" href="{{ $link($back) }}">Previous week</a>
        <a class="btn btn-sm btn-outline" href="{{ $link($today) }}">This week</a>
        <a class="btn btn-sm btn-outline" href="{{ $link($onward) }}">Next week</a>
        <button class="btn btn-sm btn-ghost" type="button" onclick="window.print()">Print</button>
    </div>
</div>

@if ($sheet['status'] === 'returned')
    <p class="flash-error" style="margin:0 0 18px"><b>Returned by the Training desk.</b>
        {{ $sheet['logs']->pluck('approval_note')->filter()->first() }}</p>
@endif

@include('admin.timelogs.sheet', ['sheet' => $sheet, 'editing' => false])

@if (! $read)
    <div class="card">
        <h3>{{ $waiting ? 'Answer this week' : 'The desk\'s decision' }}</h3>
        @if ($waiting)
            <p class="hint">{{ $sheet['logs']->count() }} {{ Str::plural('day', $sheet['logs']->count()) }} signed by
                {{ $sheet['logs']->first()->signedBy?->name ?: 'the trainer' }} ·
                {{ number_format($sheet['hours'], 2) }} hours · {{ (int) $sheet['units'] }} units.</p>
            <form method="POST" action="{{ route('admin.timelogs.decide', [$trainer, $weekOf]) }}" class="fee-form"
                  data-confirm="Approve or return the whole week of {{ $sheet['label'] }}?" data-title="Week decision">
                @csrf
                <div class="form-grid">
                    <div class="field">
                        <label>Decision <span class="req">*</span></label>
                        <select name="decision" required>
                            <option value="approved">Approve the week</option>
                            <option value="returned">Return it to the trainer</option>
                        </select>
                    </div>
                    <div class="field">
                        <label>What the trainer must correct</label>
                        <input type="text" name="note" maxlength="500" placeholder="Needed only when returning">
                    </div>
                </div>
                <button class="btn btn-primary" type="submit">Send the decision</button>
            </form>
        @else
            <p class="hint">A week is answered once it is signed. This one is
                <b>{{ TimeLog::STATUSES[$sheet['status']] }}</b>@if ($sheet['status'] === 'approved') — approved
                    by {{ $sheet['logs']->first()->approvedBy?->name }} on
                    {{ $sheet['logs']->first()->approved_at?->format('d M Y') }}@endif.</p>
        @endif
    </div>
@else
    <p class="hint">Read-only here: Human Resources sees the same sheet the Training desk signed, as proof of the work
        before pay. The decision stays with Training.</p>
@endif
@endsection
