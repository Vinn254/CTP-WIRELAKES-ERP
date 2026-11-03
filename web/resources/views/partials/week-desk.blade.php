@php
    use App\Models\TimeLog;
    use App\Support\TimeSheet;

    /** @var array $sheet */
    $carry = $carry ?? [];
    $paper = $paper ?? null;
    $weekOptions = $sheet['weeks'];
    $written = $sheet['logs']->count();
    $pickedMonth = substr($sheet['month'], 5);
    $pickedYear = substr($sheet['month'], 0, 4);
    $weekOf = $sheet['start']->toDateString();
    $office = $returnedTo ?? 'Human Resources';
@endphp
<div class="head-row">
    <div>
        <h1>{{ $heading }}</h1>
        <p class="ah-meta">
            <span><b>{{ $sheet['label'] }}</b></span>
            <span>{{ \Illuminate\Support\Carbon::parse($sheet['month'].'-01')->format('F Y') }} · week
                {{ $sheet['week_no'] }} of {{ count($weekOptions) }}</span>
            <span>{{ $written }} of 5 {{ Str::plural('day', $written) }} written</span>
            <span>{{ number_format($sheet['hours'], 2) }} hours</span>
            <span>{{ (int) $sheet['units'] }} units</span>
            <span><b>{{ TimeLog::STATUSES[$sheet['status']] }}</b></span>
        </p>
    </div>
    <div class="head-actions">
        @if ($paper)
            <a class="btn btn-sm btn-outline" href="{{ $paper($weekOf) }}">Print or download the sheet</a>
        @endif
        <button class="btn btn-sm btn-ghost" type="button" onclick="window.print()">Print</button>
    </div>
</div>

@if ($sheet['status'] === 'returned')
    <p class="flash-error" style="margin:0 0 18px"><b>Returned by {{ $returnedTo ?? 'the office' }}.</b>
        {{ $sheet['logs']->pluck('approval_note')->filter()->first() }}</p>
@endif

<form method="GET" action="{{ $self }}" class="filter-bar">
    @include('partials.month-pick', ['month' => $pickedMonth, 'year' => $pickedYear,
        'years' => $years ?? TimeSheet::years()])
    <div class="field">
        <label>Week of the month</label>
        <select name="week">
            @foreach ($weekOptions as $n => $w)
                <option value="{{ $n + 1 }}" @selected($sheet['week_no'] === $n + 1)>
                    {{ $w['label'] }} ({{ $w['dates'] }})
                </option>
            @endforeach
        </select>
    </div>
    @foreach ($carry as $key => $value)
        <input type="hidden" name="{{ $key }}" value="{{ $value }}">
    @endforeach
    <button class="btn btn-sm btn-outline" type="submit">Open the week</button>
</form>

@include('admin.timelogs.sheet', ['sheet' => $sheet, 'editing' => $canEdit])

@if ($canEdit)
    <form method="POST" action="{{ $weekAction }}" class="fee-form" data-ask data-title="Send the week">
        @csrf
        <input type="hidden" name="month" value="{{ $sheet['month'] }}">
        <input type="hidden" name="week" value="{{ $sheet['week_no'] }}">
        @foreach ($carry as $key => $value)
            <input type="hidden" name="{{ $key }}" value="{{ $value }}">
        @endforeach
        <p class="hint">Write each day from its own <b>Write</b> button on the sheet — the hours come off the two clock
            times, an hour off for lunch, and the units count themselves at two an hour. The week stays
            {{ TimeLog::STATUSES['draft'] }} until you send it to {{ $office }}, and a sent week locks until they
            answer it.</p>
        <div class="sheet-actions">
            <button class="btn btn-primary" type="submit" name="action" value="submit"
                    data-confirm="Send the week of {{ $sheet['label'] }} to {{ $office }}? Its days lock until the office answers them.">
                Submit for approval</button>
        </div>
    </form>
@else
    <p class="hint">The week is with the office. A signed or approved week is corrected by the desk that answered it,
        never rewritten here — open the sheet above to print or download what you sent.</p>
@endif

@if ($canEdit)
    @foreach ($sheet['days'] as $day)
        @include('partials.week-day', ['day' => $day, 'action' => $dayAction])
    @endforeach
@endif
