@extends('layouts.admin')
@section('title', 'Week of '.$sheet['label'])

@php
    use App\Models\TimeLog;
    use App\Support\Departments;

    $read = Departments::hosted();
    $weekOf = $sheet['start']->toDateString();
    $link = fn ($w) => Departments::inside('admin.timelogs.week', [$worker, $w]);
    $waiting = $sheet['status'] === 'submitted';
@endphp

@section('content')
<div class="head-row">
    <div>
        <h1>{{ $worker->name }}</h1>
        <p class="ah-meta">
            <span><b>{{ $sheet['label'] }}</b></span>
            <span>{{ \Illuminate\Support\Carbon::parse($sheet['month'].'-01')->format('F Y') }} · week
                {{ $sheet['week_no'] }} of {{ count($sheet['weeks']) }}</span>
            <span>{{ $sheet['logs']->count() }} {{ Str::plural('day', $sheet['logs']->count()) }} written</span>
            <span>{{ number_format($sheet['hours'], 2) }} hours</span>
            <span>{{ (int) $sheet['units'] }} units</span>
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
    <p class="flash-error" style="margin:0 0 18px"><b>Returned to the worker.</b>
        {{ $sheet['logs']->pluck('approval_note')->filter()->first() }}</p>
@endif

@include('admin.timelogs.sheet', ['sheet' => $sheet, 'editing' => false])

@if (! $read && $canDecide && $waiting)
    <div class="card">
        <h3>Answer this week</h3>
        <p class="hint">{{ $sheet['logs']->count() }} {{ Str::plural('day', $sheet['logs']->count()) }} signed by
            {{ $sheet['logs']->first()->signedBy?->name ?: $worker->name }} ·
            {{ number_format($sheet['hours'], 2) }} hours · {{ (int) $sheet['units'] }} units.</p>
        <button class="btn btn-primary" type="button" data-sheet="decide">Approve or return the week</button>
    </div>
    @include('partials.sheet-start', ['name' => 'decide', 'title' => 'Week of '.$sheet['label']])
    <form method="POST" action="{{ route('admin.timelogs.decide', [$worker, $weekOf]) }}" class="fee-form"
          data-confirm="Approve or return the whole week of {{ $sheet['label'] }}?" data-title="Week decision">
        @csrf
        <div class="form-grid">
            <div class="field">
                <label>Decision <span class="req">*</span></label>
                <select name="decision" required>
                    <option value="approved">Approve the week</option>
                    <option value="returned">Return it to the worker</option>
                </select>
            </div>
            <div class="field">
                <label>What the worker must correct</label>
                <input type="text" name="note" maxlength="500" placeholder="Needed only when returning">
            </div>
        </div>
        <div class="sheet-actions">
            <button class="btn btn-ghost" type="button" data-close-sheet>Cancel</button>
            <button class="btn btn-primary" type="submit">Send the decision</button>
        </div>
    </form>
    <p class="hint">Approving signs the hours off for pay. Human Resources keeps the only say over a week.</p>
    @include('partials.sheet-end')
@elseif (! $read)
    <p class="hint">A week is answered once it is signed. This one is
        <b>{{ TimeLog::STATUSES[$sheet['status']] }}</b>@if ($sheet['status'] === 'approved') — approved
            by {{ $sheet['logs']->first()->approvedBy?->name }} on
            {{ $sheet['logs']->first()->approved_at?->format('d M Y') }}@endif.</p>
@else
    <p class="hint">Read-only here: the same sheet Human Resources signs, as proof of the work before pay.</p>
@endif
@endsection
