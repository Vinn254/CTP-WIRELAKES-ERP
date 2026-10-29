@extends('layouts.admin')
@section('title', $ctp->full_name.' — student record')

@php
    use App\Support\Departments;

    $place = collect([$ctp->ward, $ctp->sub_county, $ctp->county])->filter()->join(', ');
    $seatRow = function ($e, array $modules): array {
        return [
            'attendance' => $e->attendancePct(),
            'theory' => $e->avgScore('theory'),
            'practical' => $e->avgScore('practical'),
            'assessment' => $e->avgScore(['assessment', 'cat', 'assignment']),
            'modules' => $modules['modules']->isEmpty() ? null
                : $modules['marked']->count().' / '.$modules['modules']->count(),
            'track' => $e->trackPct(),
        ];
    };
@endphp

@section('content')
<p class="back"><a href="{{ $back }}">&larr; Back to the trainees</a></p>

<div class="applicant-head card">
    @include('partials.avatar', ['member' => $ctp, 'src' => route('admin.promoters.file', [$ctp, 'passport'])])
    <div class="ah-main">
        <h1>{{ $ctp->full_name }} <span class="status {{ $ctp->status }}">{{ $ctp->statusLabel() }}</span></h1>
        <p class="ah-meta">
            <span class="tag">{{ $ctp->ctp_code ?: 'ID not issued yet' }}</span>
            <span>{{ $ctp->trackLabel() }}</span>
            @if ($place)<span>{{ $place }}</span>@endif
            <span>Applied {{ $ctp->created_at->format('d M Y') }}</span>
            <span>{{ $seats->count() }} {{ Str::plural('course', $seats->count()) }} on the record</span>
        </p>
    </div>
    <div class="ah-contact">
        <a href="tel:{{ $ctp->phone }}">{{ $ctp->phone }}</a>
        <a href="mailto:{{ $ctp->email }}">{{ $ctp->email }}</a>
        <a class="btn btn-sm btn-primary" href="{{ route('admin.students.transcript', $ctp) }}">Download transcript</a>
        @if (Departments::reachable(auth()->user(), 'admin.promoters.edit'))
            <a class="btn btn-sm btn-outline" href="{{ route('admin.promoters.edit', $ctp) }}">Edit profile</a>
        @endif
    </div>
</div>

<div class="stat-row">
    <div class="stat"><small>Courses taken</small><b>{{ $seats->count() }}</b></div>
    <div class="stat"><small>Modules signed off</small><b>{{ collect($modules)->flatMap(fn ($b) => $b['marked'])->count() }}</b></div>
    <div class="stat"><small>Average track</small>
        <b>{{ $seats->isEmpty() ? '—' : round($seats->avg(fn ($e) => $e->trackPct()), 1).'%' }}</b></div>
    <div class="stat"><small>Certificates issued</small>
        <b>{{ $seats->filter(fn ($e) => $e->certification && $e->certification->status === 'issued')->count() }}</b></div>
</div>

<div class="grid-2 review-grid">
    <div class="stack">
        <div class="card">
            <h3>As they registered</h3>
            <dl class="kv">
                <dt>CTP ID</dt><dd>{{ $ctp->ctp_code ?: 'not issued yet' }}</dd>
                <dt>National ID</dt><dd>{{ $ctp->national_id ?: '—' }}</dd>
                <dt>Date of birth</dt>
                <dd>{{ $ctp->dob?->format('d M Y') ?: '—' }}@if ($ctp->dob) <span class="hint">({{ $ctp->dob->age }} years)</span>@endif</dd>
                <dt>Gender</dt><dd>{{ $ctp->gender ? ucfirst($ctp->gender) : '—' }}</dd>
                <dt>Phone</dt><dd>{{ $ctp->phone ?: '—' }}</dd>
                <dt>Email</dt><dd>{{ $ctp->email ?: '—' }}</dd>
                <dt>Where they live</dt><dd>{{ collect([$ctp->village, $ctp->ward, $ctp->sub_county, $ctp->county])->filter()->join(', ') ?: '—' }}</dd>
                <dt>Highest education</dt><dd>{{ $ctp->education_level ?: '—' }}</dd>
                <dt>Year left school</dt><dd>{{ $ctp->year_left_school ?: '—' }}</dd>
                <dt>Certificates held</dt><dd>{{ $ctp->certificates_held ? implode(', ', $ctp->certificates_held) : '—' }}</dd>
                <dt>Track</dt><dd>{{ $ctp->trackLabel() }}</dd>
                <dt>Registered on</dt><dd>{{ $ctp->created_at->format('d M Y') }}</dd>
                @if ($ctp->approved_at)<dt>Approved</dt><dd>{{ $ctp->approved_at->format('d M Y') }}</dd>@endif
            </dl>
            <p class="hint">{!! dept_ref('admin.promoters.show', 'The CTP profile', ['ctp' => $ctp]) !!}
                carries the ID scan, certificates and the rest of the file.</p>
        </div>
    </div>

    <div class="stack">
        @if ($seats->isEmpty())
            <div class="card">
                <h3>No course yet</h3>
                <p class="help">This trainee has never held a seat, so there is no curriculum history or transcript to
                    read. The office gives a seat, or the trainee applies for one.</p>
            </div>
        @else
            <div class="card">
                <h3>Certificates on the record</h3>
                <table class="data">
                    <thead><tr><th>Course</th><th>Period</th><th>Certificate</th><th>Status</th></tr></thead>
                    <tbody>
                        @foreach ($seats->filter(fn ($e) => $e->certification) as $e)
                            <tr>
                                <td>{{ $e->cohort?->courseLabel() ?: '—' }}</td>
                                <td>{{ $e->cohort?->periodLabel() ?: '—' }}</td>
                                <td>{{ $e->certification->certificate_code ?: 'no code until an executive signs' }}</td>
                                <td><span class="status {{ $e->certification->status }}">{{ $e->certification->statusLabel() }}</span></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</div>

<h4 class="dl-group">Curriculum history — term by term</h4>
@foreach ($terms as $term)
    <h4 class="dl-group">{{ $term['label'] }} — {{ $term['seats']->count() }}
        {{ Str::plural('course', $term['seats']->count()) }}</h4>
    @foreach ($term['seats'] as $e)
        @php
            $board = $modules[$e->id];
            $r = $seatRow($e, $board);
            $unitMarks = $marks[$e->id] ?? [];
        @endphp
        <div class="card">
            <div class="section-head">
                <h3>{{ $e->cohort?->courseLabel() ?: 'Cohort removed' }}</h3>
                <span class="tag gold">{{ $e->cohort?->reference }}</span>
                <span class="status {{ $e->stage }}">{{ $e->stageLabel() }}</span>
                @if ($e->state !== 'approved')<span class="tag">{{ $e->stateLabel() }}</span>@endif
            </div>
            <p class="hint">{{ $e->cohort?->venue?->name ?: 'no venue set' }} ·
                trainer {{ $e->cohort?->trainer?->personName() ?: 'not assigned' }} ·
                {{ $e->cohort?->dateLabel() ?? 'dates not set' }}</p>

            <div class="table-scroll">
                <table class="data">
                    <thead>
                        <tr>
                            <th>Attendance</th><th>Modules</th><th>Theory</th><th>Practical</th>
                            <th>Assessment</th><th>Track</th><th>Certificate</th><th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td @if ($e->isBelowAttendance()) class="flag-bad" @endif>
                                {{ $r['attendance'] === null ? 'not marked' : $r['attendance'].'%' }}</td>
                            <td>{{ $r['modules'] ?? '—' }}</td>
                            <td>{{ $r['theory'] === null ? '—' : $r['theory'] }}</td>
                            <td>{{ $r['practical'] === null ? '—' : $r['practical'] }}</td>
                            <td>{{ $r['assessment'] === null ? '—' : $r['assessment'] }}</td>
                            <td class="{{ $r['track'] >= 100 ? 'flag-good' : 'flag-partial' }}"><b>{{ $r['track'] }}%</b></td>
                            <td>@if ($e->certification)
                                    <span class="status {{ $e->certification->status }}">{{ $e->certification->statusLabel() }}</span>
                                @else<span class="hint">not put forward</span>@endif</td>
                            <td class="actions">
                                <a class="btn btn-sm btn-outline" href="{{ route('admin.track.show', $e) }}">Open track</a>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            @if ($board['modules']->isNotEmpty())
                <h4 class="dl-group">Units expected</h4>
                <div class="table-scroll">
                    <table class="data">
                        <thead><tr><th>Unit</th><th>Signed off</th><th>Marked by</th></tr></thead>
                        <tbody>
                            @foreach ($board['modules'] as $m)
                                @php $line = $board['marked']->get($m->id); @endphp
                                <tr>
                                    <td>{{ $m->title }}</td>
                                    <td>@if ($line)<span class="status active">{{ $line->done_on?->format('d M Y') ?? 'marked' }}</span>
                                        @else<span class="hint">still to come</span>@endif</td>
                                    <td>{{ $line?->recorderLabel() ?? '—' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif

            @if ($unitMarks)
                <h4 class="dl-group">Marks on the approved sheets</h4>
                <div class="table-scroll">
                    <table class="data">
                        <thead><tr><th>Unit</th><th>Day taken</th><th>Type</th><th>Mark</th></tr></thead>
                        <tbody>
                            @foreach ($unitMarks as $row)
                                <tr>
                                    <td>{{ $row['unit'] }}</td>
                                    <td>{{ $row['on'] }}</td>
                                    <td>{{ $row['kind'] }}</td>
                                    <td class="num">{{ $row['score'] === null ? '—' : $row['score'] }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    @endforeach
@endforeach

@if ($papers->isNotEmpty())
    <h4 class="dl-group">Papers on the file</h4>
    <div class="table-scroll">
        <table class="data">
            <thead><tr><th>Paper</th><th>Type</th><th>Covers</th><th>Course</th><th>Size</th><th></th></tr></thead>
            <tbody>
                @foreach ($papers as $d)
                    <tr>
                        <td><b>{{ $d->title }}</b>@if ($d->note)<p>{{ $d->note }}</p>@endif</td>
                        <td>{{ $d->kindLabel() }}</td>
                        <td>{{ $d->dated_on->format('d M Y') }}</td>
                        <td>{{ $d->module?->title ?: 'Not tied to one unit' }}</td>
                        <td class="num">{{ $d->sizeLabel() }}</td>
                        <td class="actions">
                            <a class="btn btn-sm btn-outline"
                               href="{{ route('admin.enrollments.document.show', [$d->enrollment_id, $d]) }}">Read</a>
                            <a class="btn btn-sm btn-ghost"
                               href="{{ download_href(route('admin.enrollments.document.show', [$d->enrollment_id, $d])) }}">Download</a>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@endif

@if ($reading->isNotEmpty())
    <h4 class="dl-group">Library reading set for their courses</h4>
    <div class="table-scroll">
        <table class="data">
            <thead><tr><th>Title</th><th>Type</th><th>Filed under</th></tr></thead>
            <tbody>
                @foreach ($reading as $item)
                    <tr>
                        <td>{{ $item->title }}</td>
                        <td>{{ $item->kindLabel() }}</td>
                        <td>{{ $item->courseLabel() }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@endif

<p class="hint">The transcript prints this record as it reads here — the courses, the marks the Registrar approved and
    the certificates issued. {{ $ctp->ctp_code ? 'Quoting '.$ctp->ctp_code.' identifies it.' : '' }}</p>
@endsection
