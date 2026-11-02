@extends('layouts.member')
@section('title', 'My attachment')

@php
    use App\Models\AttachmentLog;

    $site = $placement?->project;
    $weekOf = $sheet['start']->toDateString();
    $friday = $sheet['start']->copy()->addDays(4)->toDateString();
    $today = now()->toDateString();
    $pickedMonth = substr($sheet['month'], 5);
    $pickedYear = substr($sheet['month'], 0, 4);
    $canEdit = $sheet['canEdit'];
    $written = collect($sheet['days'])->filter(fn (array $d) => $d['row'])->values();
@endphp

@section('content')
<div class="head-row">
    <div>
        <h1>My attachment</h1>
        @if ($placement)
            <p class="ah-meta">
                <span><b>{{ $sheet['label'] }}</b></span>
                <span>{{ \Illuminate\Support\Carbon::parse($sheet['month'].'-01')->format('F Y') }} · week
                    {{ $sheet['week_no'] }} of {{ count($sheet['weeks']) }}</span>
                <span>{{ $written->count() }} of 5 {{ Str::plural('day', $written->count()) }} written</span>
                <span>{{ number_format($sheet['hours'], 2) }} hours</span>
                <span><b>{{ AttachmentLog::STATUSES[$sheet['status']] }}</b></span>
            </p>
        @endif
    </div>
</div>

@if (! $placement)
    <div class="card">
        <h3>No site yet</h3>
        <p class="hint">The office places a trainee at an attachment site once the course is finished and the
            attachment applied for has been approved. Until then there is nothing to log here — keep to
            <a href="{{ route('portal.training') }}">My training</a> and
            <a href="{{ route('portal.applications') }}">My applications</a>.</p>
    </div>
@else
    <div class="card">
        <div class="section-head">
            <h3>My placement</h3>
            <span class="tag">{{ $placement->reference }}</span>
        </div>
        <dl class="kv">
            <dt>Site</dt><dd>{{ $site?->title ?: 'The site on the order' }}
                @if ($site)<div class="hint">{{ collect([$site->county, $site->ward])->filter()->join(' · ') }}</div>@endif</dd>
            <dt>With</dt><dd>{{ $site?->hirer?->organisation ?: 'the organisation the office placed you with' }}</dd>
            <dt>Day placed</dt><dd>{{ $placement->start_on?->format('d M Y') ?? '—' }}</dd>
            <dt>Running until</dt><dd>{{ $placement->deadline?->format('d M Y') ?? 'the office has not set an end day' }}</dd>
            <dt>What you are doing there</dt><dd>{{ $placement->scope ?: '—' }}</dd>
        </dl>
        <p class="hint">An attachment is training, not a job — it is not paid. What you hand in is this week: the days
            you sat at the site and what you did, which the Academic Registrar approves.</p>
    </div>

    @if ($sheet['status'] === 'returned')
        <p class="flash-error" style="margin:0 0 18px"><b>Sent back by the Registrar.</b>
            {{ $sheet['log']?->review_note }}</p>
    @endif

    <form method="GET" action="{{ route('portal.attachment') }}" class="filter-bar">
        @include('partials.month-pick', ['month' => $pickedMonth, 'year' => $pickedYear, 'years' => $years])
        <div class="field">
            <label>Week of the month</label>
            <select name="week">
                @foreach ($sheet['weeks'] as $n => $w)
                    <option value="{{ $n + 1 }}" @selected($sheet['week_no'] === $n + 1)>
                        {{ $w['label'] }} ({{ $w['dates'] }})</option>
                @endforeach
            </select>
        </div>
        <button class="btn btn-sm btn-outline" type="submit">Open the week</button>
    </form>

    <div class="table-scroll">
        <table class="data">
            <caption>{{ $sheet['label'] }} at {{ $site?->title ?: 'the site' }}</caption>
            <thead>
                <tr><th>Day</th><th>Time in</th><th>Time out</th><th>What you did</th>
                    <th>Supervisor’s initials</th><th class="num">Hours</th></tr>
            </thead>
            <tbody>
                @foreach ($sheet['days'] as $day)
                    @continue ($day['date'] < $weekOf || $day['date'] > $friday)
                    @php $row = $day['row']; @endphp
                    <tr>
                        <td class="nowrap"><b>{{ $day['day'] }}</b>
                            <small>{{ \Illuminate\Support\Carbon::parse($day['date'])->format('d/m/Y') }}</small></td>
                        <td>{{ $row?->clockIn() ?? '—' }}</td>
                        <td>{{ $row?->clockOut() ?? '—' }}</td>
                        <td>{{ $row?->task ?: '—' }}</td>
                        <td>{{ $row?->initials ?: '—' }}</td>
                        <td class="num nowrap">{{ $row && $row->hours !== null ? number_format((float) $row->hours, 2) : '—' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    @if ($canEdit)
        <div class="card">
            <h3>Write {{ $sheet['label'] }}</h3>
            <p class="hint">Fill the days you sat at the site and keep the week as a draft to finish later, or send it
                when every day is in. Hours count themselves from the two clock times — an hour off for lunch, eight
                hours a day at most. A week already sent is locked until the Registrar answers it.</p>
            <form method="POST" action="{{ route('portal.attachment.save') }}" class="fee-form" data-ask
                  data-title="Attachment week">
                @csrf
                <input type="hidden" name="month" value="{{ $sheet['month'] }}">
                <input type="hidden" name="week" value="{{ $sheet['week_no'] }}">
                <div class="table-scroll">
                    <table class="data">
                        <thead>
                            <tr><th>Day</th><th>Time in</th><th>Time out</th><th>What you did</th>
                                <th>Supervisor’s initials</th><th class="num">Hours on file</th></tr>
                        </thead>
                        <tbody>
                            @foreach ($sheet['days'] as $day)
                                @continue ($day['date'] < $weekOf || $day['date'] > $friday)
                                @php $row = $day['row']; @endphp
                                <tr>
                                    <td class="nowrap"><b>{{ $day['day'] }}</b>
                                        <small>{{ \Illuminate\Support\Carbon::parse($day['date'])->format('d/m/Y') }}</small></td>
                                    @if ($day['date'] <= $today)
                                        <td><input type="time" name="days[{{ $day['date'] }}][time_in]" max="{{ $today }}"
                                                   value="{{ old('days.'.$day['date'].'.time_in',
                                                       $row && $row->time_in ? substr($row->time_in, 0, 5) : '') }}"></td>
                                        <td><input type="time" name="days[{{ $day['date'] }}][time_out]" max="{{ $today }}"
                                                   value="{{ old('days.'.$day['date'].'.time_out',
                                                       $row && $row->time_out ? substr($row->time_out, 0, 5) : '') }}"></td>
                                        <td><input type="text" name="days[{{ $day['date'] }}][task]" maxlength="500"
                                                   placeholder="The work you were given that day"
                                                   value="{{ old('days.'.$day['date'].'.task', $row?->task) }}"></td>
                                        <td><input type="text" name="days[{{ $day['date'] }}][initials]" maxlength="10"
                                                   placeholder="JM" style="width:5rem"
                                                   value="{{ old('days.'.$day['date'].'.initials', $row?->initials) }}"></td>
                                    @else
                                        <td colspan="4"><span class="hint">A day that has not come yet.</span></td>
                                    @endif
                                    <td class="num nowrap">{{ $row && $row->hours !== null ? number_format((float) $row->hours, 2) : '—' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="field" style="margin-top:14px">
                    <label>Anything to say with the week</label>
                    <textarea name="note" rows="2" maxlength="500"
                              placeholder="Optional — the Registrar reads it with the log.">{{ old('note', $sheet['log']?->note) }}</textarea>
                </div>
                <div class="sheet-actions">
                    <button class="btn btn-outline" type="submit" name="action" value="draft">Save as draft</button>
                    <button class="btn btn-primary" type="submit" name="action" value="submit"
                            data-confirm="Send the week of {{ $sheet['label'] }} to the Academic Registrar? Its days lock until they answer it.">
                        Submit to the Registrar</button>
                </div>
            </form>
        </div>
    @else
        <p class="hint">The week is with the Registrar. A submitted or approved week is corrected by the desk that
            answered it, never rewritten here.</p>
    @endif

    @if ($history->isNotEmpty())
        <div class="table-scroll">
            <table class="data">
                <caption>Every week you have handed in</caption>
                <thead>
                    <tr><th>Week</th><th class="num">Days</th><th class="num">Hours</th><th>Status</th><th></th></tr>
                </thead>
                <tbody>
                    @foreach ($history as $past)
                        <tr>
                            <td class="nowrap"><b>{{ $past['label'] }}</b>
                                <small>{{ \Illuminate\Support\Carbon::parse($past['month'].'-01')->format('F Y') }}
                                    · week {{ $past['week_no'] }}</small></td>
                            <td class="num">{{ $past['days'] }}</td>
                            <td class="num">{{ number_format((float) $past['hours'], 2) }}</td>
                            <td><span class="status {{ ['draft' => 'draft', 'submitted' => 'pending_review',
                                    'approved' => 'active', 'returned' => 'rejected'][$past['status']] ?? 'draft' }}">
                                    {{ AttachmentLog::STATUSES[$past['status']] ?? $past['status'] }}</span></td>
                            <td class="actions">
                                <a class="btn btn-sm btn-outline"
                                   href="{{ route('portal.attachment', ['month' => $past['month'], 'week' => $past['week_no']]) }}">Open</a>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
@endif
@endsection
