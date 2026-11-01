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
    $friday = $sheet['start']->copy()->addDays(4)->toDateString();
    $today = now()->toDateString();
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
    <div class="card">
        <h3>Write {{ $sheet['label'] }}</h3>
        <p class="hint">Fill the days you worked and keep the week as a draft to finish later, or send it when every
            day is in. Hours and units count themselves from the two clock times — an hour off for lunch, eight hours
            a day at most. A week already sent is locked until {{ $office }} answers it.</p>
        <form method="POST" action="{{ $weekAction }}" class="fee-form" data-ask data-title="Week sheet">
            @csrf
            <input type="hidden" name="month" value="{{ $sheet['month'] }}">
            <input type="hidden" name="week" value="{{ $sheet['week_no'] }}">
            @foreach ($carry as $key => $value)
                <input type="hidden" name="{{ $key }}" value="{{ $value }}">
            @endforeach
            @if (count($defaults['departments'] ?? []) > 1)
                <div class="field">
                    <label>Department for the days written here</label>
                    <select name="department">
                        @foreach ($defaults['departments'] as $option)
                            <option value="{{ $option }}" @selected($option === $defaults['department'])>{{ $option }}</option>
                        @endforeach
                    </select>
                    <div class="help">Only the desks your account sits at. One day at a time, from the row's own
                        button, can carry a programme or cohort.</div>
                </div>
            @endif
            <div class="table-scroll">
                <table class="data">
                    <thead>
                        <tr>
                            <th>Day</th><th>Time in</th><th>Time out</th><th>Tasks accomplished</th>
                            <th class="num">Hours on file</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($sheet['days'] as $day)
                            @continue ($day['date'] < $weekOf || $day['date'] > $friday)
                            @php $row = $day['row']; @endphp
                            <tr>
                                <td class="nowrap"><b>{{ $day['day'] }}</b>
                                    <small>{{ \Illuminate\Support\Carbon::parse($day['date'])->format('d/m/Y') }}</small></td>
                                <td>
                                    <input type="time" name="days[{{ $day['date'] }}][time_in]" max="{{ $today }}"
                                           value="{{ old('days.'.$day['date'].'.time_in',
                                               $row && $row->time_in ? substr($row->time_in, 0, 5) : '') }}">
                                </td>
                                <td>
                                    <input type="time" name="days[{{ $day['date'] }}][time_out]" max="{{ $today }}"
                                           value="{{ old('days.'.$day['date'].'.time_out',
                                               $row && $row->time_out ? substr($row->time_out, 0, 5) : '') }}">
                                </td>
                                <td>
                                    <input type="text" name="days[{{ $day['date'] }}][task]" maxlength="500"
                                           placeholder="What you did that day"
                                           value="{{ old('days.'.$day['date'].'.task', $row?->task) }}">
                                </td>
                                <td class="num nowrap">{{ $row && $row->hours !== null ? number_format((float) $row->hours, 2) : '—' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="sheet-actions">
                <button class="btn btn-outline" type="submit" name="action" value="draft">Save as draft</button>
                <button class="btn btn-primary" type="submit" name="action" value="submit"
                        data-confirm="Send the week of {{ $sheet['label'] }} to {{ $office }}? Its days lock until the office answers them.">
                    Submit for approval</button>
            </div>
        </form>
    </div>
@else
    <p class="hint">The week is with the office. A signed or approved week is corrected by the desk that answered it,
        never rewritten here — open the sheet above to print or download what you sent.</p>
@endif

@if ($canEdit)
    @foreach ($sheet['days'] as $day)
        @include('partials.week-day', ['day' => $day, 'action' => $dayAction])
    @endforeach
@endif

@if ($history->isNotEmpty())
    <div class="table-scroll">
        <table class="data">
            <caption>Every week you have filled</caption>
            <thead>
                <tr>
                    <th>Week</th><th class="num">Days</th><th class="num">Hours</th><th class="num">Units</th>
                    <th>Status</th><th></th>
                </tr>
            </thead>
            <tbody>
                @foreach ($history as $past)
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
                        <td class="actions">
                            @if ($paper)
                                <a class="btn btn-sm btn-outline" href="{{ $paper($past['start']->toDateString()) }}">Sheet</a>
                            @endif
                            <a class="btn btn-sm btn-outline"
                                href="{{ $self }}?{{ http_build_query(array_merge($carry, ['month' => $past['month'], 'week' => $past['week_no']])) }}">Open</a>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@endif
