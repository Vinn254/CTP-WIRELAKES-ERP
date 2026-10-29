@extends('layouts.member')
@section('title', 'My class')

@php
    use App\Models\TrainingMaterial;

    $many = $mine->count() > 1;
@endphp

@section('content')
<div class="head-row">
    <div>
        <h1>{{ $cohort->reference }} — {{ $cohort->courseLabel() }}</h1>
        <p class="ah-meta">
            <span>{{ $cohort->venue?->name ?: 'No venue set' }}</span>
            <span>{{ $cohort->periodLabel() }}</span>
            <span>{{ $cohort->dateLabel() }}</span>
            <span>{{ $cohort->trainer?->personName() ?: 'the office' }}</span>
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
            <span class="tag">approved {{ $timetable->reviewed_at?->format('d M Y') }}</span>
        @endif
    </div>

    @if ($week === [])
        <p class="help">No timetable is up for this class yet. The trainer writes the week and the Academic
            Registrar approves it; it shows here as soon as they do.</p>
    @else
        <div class="table-scroll">
            <table class="data">
                <thead><tr><th>Day</th><th>Time</th><th>Class</th><th>Unit</th><th>Room</th></tr></thead>
                <tbody>
                    @foreach ($week as $dayRow)
                        @foreach ($dayRow['slots'] as $slot)
                            <tr>
                                <td>{{ $dayRow['label'] }}</td>
                                <td>{{ $slot->timeLabel() }}</td>
                                <td>{{ $slot->kindLabel() }}</td>
                                <td>{{ $slot->module?->title ?: '—' }}</td>
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
        <span class="tag">{{ round($seat->trackPct(), 1) }}% of the record</span>
    </div>

    @if ($units->isEmpty())
        <p class="help">The office has not put units under {{ $cohort->courseLabel() }} yet, so there is nothing to
            follow for this class.</p>
    @else
        @foreach ($units as $unit)
            @php $row = $marksByUnit[$unit->id] ?? ['on' => null, 'marks' => []]; @endphp
            <div class="row-line">
                <b>{{ $unit->position }}. {{ $unit->title }}</b>
                @if ($row['marks'] === [])
                    <span class="hint">no marks on record yet</span>
                @else
                    @foreach ($row['marks'] as $kind => $score)
                        <span class="tag">{{ $kinds[$kind] }} {{ $score }}/100</span>
                    @endforeach
                @endif
            </div>
            @if ($row['on'])
                <p class="hint">Marked on {{ $row['on'] }}.</p>
            @endif
        @endforeach
        <p class="help">A mark only reaches your record when the Registrar approves the trainer's sheet. Your full
            record, part by part, is on <a href="{{ route('portal.training') }}">My training</a>.</p>
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
