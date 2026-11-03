@extends('layouts.admin')
@section('title', 'Teaching paper')

@php
    use App\Models\AttachmentLog;
    use App\Models\CohortSession;
    use App\Models\LessonPlan;
    use App\Models\Timetable;
    use App\Models\UnitMarkSheet;

    $cohort = $paper->cohort;

    // An attachee writes their own week, so the desk reads this paper as coming from the trainee, not a trainer.
    $isAtt = $paper instanceof AttachmentLog;
    $author = $isAtt ? 'the attachee' : 'the trainer';
    $order = $isAtt ? $paper->workOrder : null;

    $heading = match (true) {
        $paper instanceof CohortSession => 'Attendance register — ' . $paper->dateLabel(),
        $paper instanceof UnitMarkSheet => 'Mark sheet — ' . $paper->unitLabel(),
        $paper instanceof Timetable => 'Timetable — ' . ($cohort?->reference ?? 'a cohort'),
        $isAtt => 'Attachment log — ' . e($paper->weekLabel()),
        default => 'Lesson plan — ' . ($cohort?->reference ?? 'a cohort'),
    };

    // Who sits on the paper, in the order the desk reads them.
    $seats = match (true) {
        $paper instanceof CohortSession => $paper->marks,
        $paper instanceof UnitMarkSheet => $paper->marks,
        default => collect(),
    };
@endphp

@section('content')
<div class="head-row">
    <div>
        <h1>{{ $heading }}</h1>
        <p class="ah-meta">
            @if ($isAtt)
                <span><b>{{ $paper->ctp?->full_name }}</b> · {{ $paper->ctp?->ctp_code }}</span>
                <span>{{ $order?->project?->title ?: 'No site on the order' }}</span>
                <span>{{ collect([$order?->project?->county, $order?->project?->ward])->filter()->join(' · ') ?: 'Site not named' }}</span>
                <span>{{ $cohort?->courseLabel() ?: 'Attachment' }}</span>
            @else
                <span>{{ $cohort?->reference }} — {{ $cohort?->courseLabel() }}</span>
                <span>{{ $cohort?->venue?->name ?: 'No venue set' }}</span>
                <span>{{ $cohort?->periodLabel() }}</span>
            @endif
            <span>handed in {{ $paper->submitted_at?->format('d M Y') ?? 'not yet' }}</span>
            <span>{{ $isAtt ? ($order?->assignee_name ?: 'the attachee') : ($cohort?->trainer?->personName() ?: 'the office') }}</span>
        </p>
    </div>
    <div class="head-acts">
        <span class="status {{ $paper->statusClass() }}">{{ $paper->statusLabel() }}</span>
    </div>
</div>

<a class="back" href="{{ $back }}">Back to the queue</a>

<a class="back" href="{{ $back }}">Back to the queue</a>

@if ($paper instanceof CohortSession)
    <div class="card">
        <div class="section-head"><h3>The class that sat</h3></div>
        <dl class="kv">
            <dt>Class</dt><dd>{{ $paper->kindLabel() }}</dd>
            <dt>Day</dt><dd>{{ $paper->dateLabel() }}</dd>
            <dt>Hours taught</dt><dd>{{ $paper->hours ?: '—' }}</dd>
            <dt>What was covered</dt><dd>{{ $paper->topic ?: '—' }}</dd>
            <dt>Note on the day</dt><dd>{{ $paper->note ?: '—' }}</dd>
            <dt>Signed sheet</dt><dd>
                @if ($paper->sheet_path)
                    <a href="{{ route('admin.teaching.day.sheet', ['cohort' => $cohort?->id, 'session' => $paper->id]) }}">
                        {{ $paper->sheet_name ?: 'Open the sheet' }}</a>
                    · <a href="{{ download_href(route('admin.teaching.day.sheet', ['cohort' => $cohort?->id, 'session' => $paper->id])) }}">Download</a>
                @else
                    <span class="hint">None attached — the ticks below are the register.</span>
                @endif</dd>
        </dl>

        <h4 class="dl-group">Who was in class</h4>
        @if ($seats->isEmpty())
            <p class="help">No trainee is ticked on this day. Send it back and ask the trainer to tick the roll.</p>
        @else
            <div class="table-scroll">
                <table class="data">
                    <thead><tr><th>CTP</th><th>On the roll</th><th>Present</th><th>Reason given</th></tr></thead>
                    <tbody>
                        @foreach ($seats as $mark)
                            <tr>
                                <td><b>{{ $mark->enrollment?->ctp?->full_name }}</b>
                                    <div class="hint">{{ $mark->enrollment?->ctp?->ctp_code }}</div></td>
                                <td>{{ $mark->enrollment?->stateLabel() }}</td>
                                <td><span class="status {{ $mark->present ? 'active' : 'closed' }}">
                                        {{ $mark->present ? 'Present' : 'Absent' }}</span></td>
                                <td>{{ $mark->note ?: '—' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <p class="hint">{{ $paper->presentCount() }} of {{ $seats->count() }}
                on the day — {{ round($paper->presentCount() / max(1, $seats->count()) * 100) }}% attendance.</p>
        @endif
    </div>
@endif

@if ($paper instanceof UnitMarkSheet)
    <div class="card">
        <div class="section-head">
            <h3>{{ $paper->unitLabel() }}</h3>
            <span class="tag">{{ $paper->dateLabel() }}</span>
        </div>
        <p class="hint">{{ $paper->note ?: 'No note with the sheet.' }}</p>

        @php $weights = $paper->module ? \App\Support\Grading::for($paper->module) : null; @endphp
        @if ($weights)
            <p class="hint">Weighed here as {{ \App\Support\Grading::weightLine($weights) }}
                — a trainee passes the unit at {{ $weights->pass_pct }}.
                <a href="{{ route('admin.cohorts.results', ['cohort' => $paper->cohort_id]) }}">Set the weights on the cohort’s results</a>
            </p>
        @endif

        @if ($seats->isEmpty())
            <p class="help">Nothing is written on this sheet yet. Send it back to the trainer.</p>
        @else
            @php $bySeat = $seats->groupBy(fn ($m) => (int) $m->enrollment_id); @endphp
            <div class="table-scroll">
                <table class="data">
                    <thead>
                        <tr>
                            <th>CTP</th>
                            @foreach (\App\Models\UnitMarkSheet::KINDS as $label)
                                <th>{{ $label }}</th>
                            @endforeach
                            <th>Written by the trainer</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($bySeat as $marks)
                            @php $seat = $marks->first()->enrollment; @endphp
                            <tr>
                                <td><b>{{ $seat?->ctp?->full_name }}</b>
                                    <div class="hint">{{ $seat?->ctp?->ctp_code }}</div></td>
                                @foreach (array_keys(\App\Models\UnitMarkSheet::KINDS) as $kind)
                                    @php $mark = $marks->firstWhere('kind', $kind); @endphp
                                    <td>{{ $mark?->score !== null ? $mark->score . '/100' : '—' }}
                                        @if ($mark?->note)<div class="hint">{{ $mark->note }}</div>@endif</td>
                                @endforeach
                                <td>{{ $seat?->stateLabel() }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
@endif

@if ($paper instanceof Timetable)
    <div class="card">
        <div class="section-head"><h3>The week as handed in</h3></div>
        <p class="hint">{{ $paper->note ?: 'No note with the week.' }}</p>
        @if ($week === [])
            <p class="help">This timetable holds no classes. Send it back to the trainer.</p>
        @else
            <div class="table-scroll">
                @php $named = collect($week)->pluck('slots')->flatten()->contains(fn ($s) => $s->trainer_id); @endphp
                <table class="data">
                    <thead><tr><th>Day</th><th>Time</th><th>Class</th><th>Unit</th>
                        @if ($named)<th>Lecturer</th>@endif<th>Room</th></tr></thead>
                    <tbody>
                        @foreach ($week as $dayRow)
                            @foreach ($dayRow['slots'] as $slot)
                                <tr>
                                    <td>{{ $dayRow['label'] }}</td>
                                    <td>{{ $slot->timeLabel() }}</td>
                                    <td>{{ $slot->kindLabel() }}</td>
                                    <td>{{ $slot->module?->title ?: '—' }}</td>
                                    @if ($named)<td>{{ $slot->lecturerLabel() }}</td>@endif
                                    <td>{{ $slot->room ?: '—' }}</td>
                                </tr>
                            @endforeach
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
@endif

@if ($isAtt)
    @php $written = collect($days)->filter(fn (array $d) => $d['row'])->values(); @endphp
    <div class="card">
        <div class="section-head">
            <h3>The week as handed in</h3>
            <span class="tag">{{ $written->count() }} {{ Str::plural('day', $written->count()) }} written</span>
        </div>
        <p class="hint">{{ $paper->note ?: 'No note with the week.' }}</p>
        @if ($written->isEmpty())
            <p class="help">No day is written on this week. Send it back and ask the attachee to fill the days they
                sat at the site.</p>
        @else
            <div class="table-scroll">
                <table class="data">
                    <thead>
                        <tr><th>Day</th><th>Time in</th><th>Time out</th><th>What they did</th>
                            <th>Supervisor’s initials</th><th class="num">Hours</th></tr>
                    </thead>
                    <tbody>
                        @foreach ($written as $day)
                            <tr>
                                <td class="nowrap"><b>{{ $day['day'] }}</b>
                                    <small>{{ \Illuminate\Support\Carbon::parse($day['date'])->format('d/m/Y') }}</small></td>
                                <td>{{ $day['row']->clockIn() }}</td>
                                <td>{{ $day['row']->clockOut() }}</td>
                                <td>{{ $day['row']->task }}</td>
                                <td>{{ $day['row']->initials ?: '—' }}</td>
                                <td class="num">{{ $day['row']->hours !== null ? number_format((float) $day['row']->hours, 2) : '—' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot>
                        <tr><th colspan="5">Hours on the week</th>
                            <th class="num">{{ number_format($paper->hoursTotal(), 2) }}</th></tr>
                    </tfoot>
                </table>
            </div>
            <p class="hint">An attachment is training, not a job — this week is never paid. Approving it keeps the
                days on the trainee’s record; it writes nothing onto a payout.</p>
        @endif
    </div>
@endif

@if ($paper instanceof LessonPlan)
    <div class="card">
        <div class="section-head">
            <h3>The plan as handed in</h3>
            <span class="tag">{{ $lines->count() }} {{ Str::plural('lesson', $lines->count()) }}</span>
        </div>
        @forelse ($lines as $line)
            <h4 class="dl-group">Lesson {{ $line->position }} — {{ $line->topic }}</h4>
            <p class="hint">
                <span class="tag">{{ $line->module?->title ?: 'No unit named' }}</span>
                @if ($line->planned_on)<span class="tag">{{ $line->dateLabel() }}</span>@endif
                @if ($line->timeLabel())<span class="tag">{{ $line->timeLabel() }}</span>@endif
            </p>
            <p>{!! nl2br(e($line->summary)) !!}</p>
            @foreach (['Objectives' => $line->objectives, 'Activities' => $line->activities,
                       'Resources' => $line->resources, 'How the class is assessed' => $line->assessment_note] as $label => $text)
                @if ($text)
                    <p class="hint"><b>{{ $label }}</b> {!! nl2br(e($text)) !!}</p>
                @endif
            @endforeach
        @empty
            <p class="help">This plan holds no lessons yet. Send it back and ask the trainer to write them.</p>
        @endforelse
    </div>
@endif

<div class="card">
    <div class="section-head"><h3>The Registrar’s answer</h3></div>

    @if ($paper->status === 'approved')
        <p class="hint">{{ $paper->statusLabel() }} {{ $paper->reviewed_at?->format('d M Y') ?? 'on record' }}
            by {{ $paper->reviewer?->name ?? 'this desk' }}
            @if ($paper->review_note) · “{{ $paper->review_note }}” @endif</p>
        <p class="help">
            {{ $isAtt ? 'The week stands on the attachee’s record.'
                : ($paper instanceof Timetable || $paper instanceof LessonPlan
                    ? 'The trainees see this paper on their own screens now.'
                    : 'These lines are written onto the trainees’ records.') }}
            A corrected paper is handed in again by {{ $author }} on their own desk and answered here again.</p>
        <form method="POST" action="{{ route('admin.registers.decide', ['kind' => $kind, 'id' => $paper->id]) }}"
              class="fee-form"
              data-confirm="Send this approved paper back to {{ $author }}? What it wrote comes off the records.">
            @csrf
            <input type="hidden" name="decision" value="return">
            <div class="field">
                <label>Why it is going back <span class="req">*</span></label>
                <textarea name="note" rows="3" maxlength="500" required
                          placeholder="{{ $isAtt ? 'Thursday has no supervisor’s initials. Fill it in and send the week again.' : 'The Wednesday ticks were wrong. Correct them and forward the day again.' }}"></textarea>
            </div>
            <div class="sheet-actions">
                <button class="btn btn-outline" type="submit">Send back to {{ $author }}</button>
            </div>
        </form>
    @elseif ($paper->status === 'returned')
        <p class="hint">{{ $paper->statusLabel() }} {{ $paper->reviewed_at?->format('d M Y') ?? 'on record' }}
            by {{ $paper->reviewer?->name ?? 'this desk' }}
            @if ($paper->review_note) · “{{ $paper->review_note }}” @endif</p>
        <p class="help">{{ ucfirst($author) }} fixes it on their own desk and forwards it again. Nothing of theirs
            reached the records while it was back.</p>
    @elseif ($paper->status === 'draft')
        <p class="help">Not handed in yet — this paper is still with {{ $author }}. Nothing to answer here.</p>
    @else
        <p class="hint">{{ $isAtt ? 'Approving keeps this week on the attachee’s record — it is never paid. Sending it back returns it with what to fix.' : 'Approving writes this paper onto every trainee it names. Sending it back writes nothing and returns it to the trainer with what to fix.' }}</p>
        <form method="POST" action="{{ route('admin.registers.decide', ['kind' => $kind, 'id' => $paper->id]) }}"
              class="fee-form" data-confirm="Approve this paper onto the records?">
            @csrf
            <input type="hidden" name="decision" value="approve">
            <div class="field">
                <label>Note on the approval</label>
                <input type="text" name="note" maxlength="500" placeholder="Optional — read back with the paper.">
            </div>
            <div class="sheet-actions">
                <button class="btn btn-primary" type="submit">Approve</button>
            </div>
        </form>
        <form method="POST" action="{{ route('admin.registers.decide', ['kind' => $kind, 'id' => $paper->id]) }}"
              class="fee-form" data-confirm="Send this paper back to {{ $author }}?">
            @csrf
            <input type="hidden" name="decision" value="return">
            <div class="field">
                <label>What to fix <span class="req">*</span></label>
                <textarea name="note" rows="3" maxlength="500" required
                          placeholder="{{ $isAtt ? 'Write Friday and add the supervisor’s initials, then send the week again.' : 'Tick the two CTPs missing from Wednesday, then forward it again.' }}"></textarea>
            </div>
            <div class="sheet-actions">
                <button class="btn btn-outline" type="submit">Send back</button>
            </div>
        </form>
    @endif
</div>
@endsection
