@php
    /* One course, every run of it — newest academic year first, so a course that has run for years reads as history. */
    $byYear = $runs->groupBy(fn ($c) => $c->yearLabel() ?: 'year not set');
@endphp

<div class="card run-history">
    <div class="section-head">
        <h3>{{ $title }}</h3>
        <span class="hint">{{ $runs->count() }} run{{ $runs->count() === 1 ? '' : 's' }} across {{ $byYear->count() }} academic year{{ $byYear->count() === 1 ? '' : 's' }}</span>
    </div>

    @foreach ($byYear as $year => $yearRuns)
        <h4 class="dl-group">{{ $year }}</h4>
        <table class="data runs-table">
            <thead>
                <tr><th>Period</th><th>Ref</th><th>Dates</th><th>Trainer</th><th>Where</th><th>Seated</th><th>Completed</th><th>Status</th><th></th></tr>
            </thead>
            <tbody>
                @foreach ($yearRuns as $r)
                    @php
                        $seated = $r->enrollments->where('state', 'approved');
                        $here = isset($current) && $current && $r->id === $current->id;
                    @endphp
                    <tr class="{{ $here ? 'run-current' : '' }}">
                        <td><b>{{ $r->termLabel() ?: '—' }}</b></td>
                        <td>{{ $r->reference }}</td>
                        <td>{{ $r->dateLabel() }}</td>
                        <td>{{ $r->trainer?->fundi?->full_name ?: '—' }}</td>
                        <td>{{ $r->venue?->name ?: $r->placeLabel() }}</td>
                        <td>{{ $seated->count() }} / {{ $r->seats }}</td>
                        <td>{{ $seated->where('stage', 'completed')->count() }}</td>
                        <td><span class="status {{ $r->status }}">{{ $r->statusLabel() }}</span></td>
                        <td style="text-align:right;">
                            @if ($here)
                                <span class="tag gold">you are here</span>
                            @else
                                <a class="btn btn-sm btn-outline" href="{{ $url($r) }}">Open</a>
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endforeach

    <p class="hint">{{ $note }}</p>
</div>
