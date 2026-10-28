@php
    use App\Models\TimeLog;

    /** @var array $sheet */
    $carry = $carry ?? [];
    $weekOptions = $sheet['weeks'];
    $written = $sheet['logs']->count();
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
        <button class="btn btn-sm btn-ghost" type="button" onclick="window.print()">Print</button>
    </div>
</div>

@if ($sheet['status'] === 'returned')
    <p class="flash-error" style="margin:0 0 18px"><b>Returned by {{ $returnedTo ?? 'the office' }}.</b>
        {{ $sheet['logs']->pluck('approval_note')->filter()->first() }}</p>
@endif

<form method="GET" action="{{ $self }}" class="filter-bar">
    <div class="field">
        <label>Month</label>
        <select name="month">
            @foreach ($months as $m)
                <option value="{{ $m }}" @selected($sheet['month'] === $m)>
                    {{ \Illuminate\Support\Carbon::parse($m.'-01')->format('F Y') }}
                </option>
            @endforeach
        </select>
    </div>
    <div class="field">
        <label>Week of the month</label>
        <select name="week">
            @foreach ($weekOptions as $n => $w)
                <option value="{{ $n + 1 }}" @selected($sheet['week_no'] === $n + 1)>
                    {{ $w['label'] }} — {{ $w['dates'] }}
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
    <div class="card">
        <h3>Sign {{ $sheet['label'] }}</h3>
        <p class="hint">Write each day worked with the button on its row, then sign the week once. After signing the
            days are locked until Human Resources answers the week.</p>
        <form method="POST" action="{{ $signAction }}" class="fee-form"
              data-confirm="Sign the week of {{ $sheet['label'] }} and send it to Human Resources?" data-title="Sign week">
            @csrf
            <input type="hidden" name="month" value="{{ $sheet['month'] }}">
            <input type="hidden" name="week" value="{{ $sheet['week_no'] }}">
            @foreach ($carry as $key => $value)
                <input type="hidden" name="{{ $key }}" value="{{ $value }}">
            @endforeach
            <button class="btn btn-primary" type="submit" @if ($written === 0) disabled @endif>Sign and send the week</button>
            @if ($written === 0)
                <span class="hint">Write at least one day first.</span>
            @endif
        </form>
    </div>
@else
    <p class="hint">The week is with the office. A signed or approved week is corrected by the desk that answered it,
        never rewritten here.</p>
@endif

@if ($canEdit)
    @foreach ($sheet['days'] as $day)
        @include('partials.week-day', ['day' => $day, 'action' => $dayAction])
    @endforeach
@endif

@if ($history->isNotEmpty())
    <div class="table-scroll">
        <table class="data">
            <caption>Weeks you have filled</caption>
            <thead>
                <tr>
                    <th>Week</th><th class="num">Days</th><th class="num">Hours</th><th class="num">Units</th>
                    <th>Status</th><th></th>
                </tr>
            </thead>
            <tbody>
                @foreach ($history->take(12) as $past)
                    <tr>
                        <td class="nowrap"><b>{{ $past['label'] }}</b>
                            <small>{{ \Illuminate\Support\Carbon::parse($past['month'].'-01')->format('F Y') }}
                                · week {{ $past['week_no'] }}</small></td>
                        <td class="num">{{ $past['days'] }}</td>
                        <td class="num">{{ number_format((float) $past['hours'], 2) }}</td>
                        <td class="num">{{ (int) $past['units'] }}</td>
                        <td><span class="status {{ ['draft' => 'closed', 'submitted' => 'pending_review',
                                'approved' => 'active', 'returned' => 'suspended'][$past['status']] ?? 'closed' }}">
                                {{ TimeLog::STATUSES[$past['status']] ?? $past['status'] }}</span></td>
                        <td class="actions"><a class="btn btn-sm btn-outline"
                            href="{{ $self }}?{{ http_build_query(array_merge($carry, ['month' => $past['month'], 'week' => $past['week_no']])) }}">Open</a></td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@endif
