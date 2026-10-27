@extends('layouts.admin')
@section('title', 'Construction files')

@php
    use App\Models\Project;
    use App\Models\SiteInspection;
    use App\Support\Construction;
    use App\Support\Finance;

    $pill = ['draft' => 'pending_review', 'open' => 'pending_review', 'awarded' => 'awarded', 'closed' => 'active'];
@endphp

@section('content')
<h1>Construction files</h1>
<p class="page-lede">The engineering record behind each buildable project.</p>

<div class="stat-row">
    @foreach ($queues as $label => $count)
        <div class="stat"><small>{{ $label }}</small><b>{{ $count }}</b></div>
    @endforeach
</div>

<div class="tabs">
    @foreach ($tabs as $key => $t)
        <a class="tab {{ $tab === $key ? 'on' : '' }}"
           href="{{ route('admin.construction.index', array_filter(['tab' => $key, 'q' => $search])) }}">
            {{ $t['label'] }} <span class="hint">({{ $counts[$key] ?? 0 }})</span>
        </a>
    @endforeach
</div>

<form method="GET" action="{{ route('admin.construction.index') }}" class="filter-bar">
    <input type="hidden" name="tab" value="{{ $tab }}">
    <div>
        <label for="q">Project, trade or place</label>
        <input type="search" name="q" id="q" value="{{ $search }}" placeholder="PRJ-2026-00001, masonry, Nakuru" aria-label="Search: PRJ-2026-00001, masonry, Nakuru">
    </div>
    <button class="btn btn-sm btn-outline" type="submit">Search</button>
    @if ($search)<a class="btn btn-sm btn-ghost" href="{{ route('admin.construction.index', ['tab' => $tab]) }}">Clear</a>@endif
    <span class="hint">{{ $tabs[$tab]['note'] ?? '' }}</span>
</form>

<div class="card">
    <table class="data">
        <thead>
            <tr>
                <th>Project</th><th>Trade and place</th><th>Hirer</th>
                <th>Site assessed</th><th>BoQ</th><th>Labour</th><th>Inspections</th><th>Changes</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($rows as $p)
                @php
                    $live = $p->boqItems->where('status', 'live');
                    $openFails = $p->inspections->flatMap(fn (SiteInspection $i) => $i->items)
                        ->where('result', 'fail')->whereNull('rechecked_on')->count();
                    $pending = $p->variations->where('status', 'proposed')->count();
                @endphp
                <tr>
                    <td>
                        <a href="{{ route('admin.construction.show', $p) }}"><b>{{ $p->reference }}</b></a>
                        <div>{{ str($p->title)->limit(48) }}</div>
                        <span class="status {{ $pill[$p->status] ?? 'pending_review' }}">{{ Project::STATUSES[$p->status] ?? $p->status }}</span>
                    </td>
                    <td>{{ $p->trade ?: 'trade not set' }}
                        <div class="hint">{{ $p->county ?: 'place not set' }}</div></td>
                    <td>{{ $p->hirer->organisation ?: 'no hirer named' }}</td>
                    <td>
                        @if ($p->assessments->isEmpty())
                            <span class="tag gray">not yet read</span>
                        @else
                            {{ $p->assessments->count() }} visit{{ $p->assessments->count() > 1 ? 's' : '' }}
                            <div class="hint">{{ $p->assessments->last()->recommendationLabel() }}</div>
                        @endif
                    </td>
                    <td>{{ $live->count() }} line{{ $live->count() === 1 ? '' : 's' }}
                        <div class="hint">{{ Finance::ksh(Construction::boqTotal($p)) }}
                            @if ($p->boqItems->count() > $live->count())
                                · {{ $p->boqItems->count() - $live->count() }} withdrawn
                            @endif</div></td>
                    <td>{{ $p->labourEntries_count ?? $p->labourEntries->count() }} day(s)</td>
                    <td>
                        @if ($p->inspections->isEmpty())
                            <span class="hint">none scheduled</span>
                        @else
                            {{ $p->inspections->count() }} on file
                            @if ($openFails)<div><span class="tag">{{ $openFails }} item(s) failed and open</span></div>@endif
                            <div class="hint">{{ $p->inspections->last()->statusLabel() }}</div>
                        @endif
                    </td>
                    <td>
                        @if ($p->variations->isEmpty())
                            <span class="hint">as designed</span>
                        @else
                            {{ $p->variations->count() }} raised
                            @if ($pending)<div><span class="tag gold">{{ $pending }} waiting on a Director</span></div>@endif
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="8" class="empty-cell">
                    No construction file here yet. A file opens on any project whose trade is buildable work
                    ({{ collect($trades)->take(4)->implode(', ') }}…) —
                    post or award one on {!! dept_ref('admin.projects.index', 'Projects') !!}, then read the site
                    before pricing it (§11.2).
                </td></tr>
            @endforelse
        </tbody>
    </table>
    <p class="hint">
        The trades the programme books for this module: {{ collect($trades)->implode(', ') }}.
        A project with any record on this desk stays here even if its trade reads differently — the file is not thrown away.
    </p>
</div>
@endsection
