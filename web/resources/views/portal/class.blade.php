@extends('layouts.member')
@section('title', 'My class')

@php
    use App\Models\TrainingMaterial;

    $many = $mine->count() > 1;
    $myLecturers = $units->flatMap(fn ($u) => $unitHands[$u->id] ?? collect())
        ->unique(fn ($t) => $t->id)->values();
@endphp

@section('content')
<div class="head-row">
    <div>
        <h1>{{ $cohort->reference }} — {{ $programmeName ?: $cohort->courseLabel() }}</h1>
        <p class="ah-meta">
            <span>{{ $cohort->venue?->name ?: 'No venue set' }}</span>
            <span>{{ $cohort->periodLabel() }}</span>
            <span>{{ $cohort->dateLabel() }}</span>
            <span>{{ match (true) {
                $myLecturers->count() === 1 => $myLecturers->first()->personName(),
                $myLecturers->count() > 1 => $myLecturers->count().' lecturers on your units',
                default => $cohort->trainer?->personName() ?: 'the office',
            } }}</span>
        </p>
    </div>
    @if ($many)
        <div class="head-acts">
            <label class="field">
                <span>Class</span>
                <select onchange="location.href=this.value">
                    @foreach ($mine as $seat)
                        @php $c = $seat->cohort; @endphp
                        <option value="{{ route('portal.class.cohort', $c?->id) }}"
                                @selected($c?->id === $cohort->id)>{{ $c?->reference }} — {{ $c?->courseLabel() }}</option>
                    @endforeach
                </select>
            </label>
        </div>
    @endif
</div>

<div class="card">
    <div class="section-head">
        <h3>The week of classes</h3>
        @if ($timetable)
            <span class="tag">{{ $timetable->cohort_id ? 'your cohort' : 'set for the whole programme' }}</span>
            <span class="tag">approved {{ $timetable->reviewed_at?->format('d M Y') }}</span>
        @endif
    </div>

    @if ($week === [])
        <p class="help">No timetable is up for this class yet. The Registrar lays the week out for the programme and
            the trainer writes their own cohort's week; it shows here as soon as either is approved.</p>
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

<div class="card">
    <div class="section-head">
        <h3>The units you do</h3>
        <span class="tag">{{ $units->count() }} {{ Str::plural('unit', $units->count()) }}</span>
        @if ($programmeName)<span class="tag">{{ $programmeName }}</span>@endif
        <span class="tag">{{ round($seat->trackPct(), 1) }}% of the record</span>
        @if ($overall !== null)
            <span class="tag gold">overall {{ $overall }} · {{ \App\Support\Grading::letter($overall) }}</span>
        @endif
    </div>

    @if ($units->isEmpty())
        <p class="help">The office has not put units under {{ $programmeName ?: $cohort->courseLabel() }} on this
            intake yet, so there is nothing to follow for this class.</p>
    @else
        @if ($offProgramme)
            <p class="hint" style="margin:0 0 10px;">No units are registered for
                {{ $programmeName ?: 'your programme' }} on this intake yet, so these are the units it teaches. Tell the
                academic office if your course should have its own list.</p>
        @endif
        <div class="table-scroll">
            <table class="data">
                <thead>
                    <tr>
                        <th>Unit</th>
                        <th>Lecturers on the unit</th>
                        @foreach (\App\Models\UnitGrading::PARTS as $label)
                            <th class="num">{{ $label }}</th>
                        @endforeach
                        <th class="num">Weighed grade</th>
                        <th class="num">Pass mark</th>
                        <th>Remark</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($units as $unit)
                        @php $row = $marksByUnit[$unit->id] ?? ['on' => null, 'marks' => []];
                               $grade = $grades[$unit->id] ?? null;
                               $hands = $unitHands[$unit->id] ?? collect(); @endphp
                        <tr>
                            <td><b>{{ $unit->position }}. {{ $unit->title }}</b>
                                @if ($row['on'])<div class="hint">Marked on {{ $row['on'] }}.</div>@endif
                                @if ($unit->hasSyllabus())
                                    <a class="hint" href="{{ route('portal.class.scheme', ['cohort' => $cohort->id,
                                        'module' => $unit->id]) }}">{{ $unit->syllabusLabel() }}</a>
                                @endif</td>
                            <td>{{ $hands->map->personName()->filter()->join(', ') ?: 'Not named yet' }}
                                @if ($first = $hands->first())
                                    <div class="hint">{{ $first->placeLabel() }}</div>
                                @endif</td>
                            @foreach ($grade['parts'] ?? [] as $part)
                                <td class="num">
                                    {{ $part['score'] === null ? '—' : $part['score'] }}
                                    <small>{{ $part['weight'] }}%</small>
                                </td>
                            @endforeach
                            <td class="num"><b>{{ $grade && $grade['marked'] ? $grade['pct'] : '—' }}</b>
                                @if ($grade && $grade['marked'])
                                    <div class="hint">{{ $grade['letter'] }}</div>
                                @endif</td>
                            <td class="num">{{ $grade['pass_pct'] ?? '—' }}</td>
                            <td class="{{ $grade && $grade['marked'] ? ($grade['passed'] ? 'flag-good' : 'flag-bad') : '' }}">
                                {{ $grade['remark'] ?? '—' }}
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <p class="help">The figures under each heading are what you were marked out of 100, and the small number is what
            that mark is worth on the unit — the Registrar sets those weights. Your weighed grade is the marks added up
            on those weights, and you pass at the pass mark shown. Your full record, part by part, is on
            <a href="{{ route('portal.training') }}">My training</a>.</p>
    @endif
</div>

<div class="card">
    <div class="section-head">
        <h3>Material the trainer has given you</h3>
        <span class="tag">{{ $materials->count() }} {{ Str::plural('handout', $materials->count()) }}</span>
    </div>

    @if ($materials->isEmpty())
        <p class="help">Nothing has been handed out for this class yet. Notes, slides, assignments and past papers
            the trainer puts up for a unit or a lesson appear here.</p>
    @else
        @foreach ($materials as $m)
            <div class="row-line">
                <div>
                    <b>{{ $m->title }}</b>
                    <div class="hint">
                        {{ $m->kindLabel() }}
                        @if ($m->module) · {{ $m->module->title }} @endif
                        @if ($m->lesson) · Lesson {{ $m->lesson->topic }} @endif
                        · {{ $m->dateLabel() }} · {{ $m->sizeLabel() }}
                        @if ($m->uploaderFundi) · given by {{ $m->uploaderFundi->full_name }} @endif
                    </div>
                    @if ($m->note)<p class="hint">{{ $m->note }}</p>@endif
                </div>
                <div class="btn-row">
                    <a class="btn btn-sm btn-outline" href="{{ route('portal.class.material', ['cohort' => $cohort->id, 'material' => $m->id]) }}">
                        Open</a>
                    <a class="btn btn-sm btn-ghost"
                       href="{{ download_href(route('portal.class.material', ['cohort' => $cohort->id, 'material' => $m->id])) }}">Download</a>
                </div>
            </div>
        @endforeach
    @endif
</div>
@endsection
