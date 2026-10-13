@php
    use App\Models\Project;
    use Illuminate\Support\Str;
    $shown = isset($limit) ? $projects->take($limit) : $projects;
@endphp

@if ($shown->isEmpty())
    <div class="card"><p style="margin:0;color:var(--muted);">No projects are open at your level right now. Check back soon — the programme office posts new work here every week.</p></div>
@else
    <div class="proj-list">
        @foreach ($shown as $p)
            <div class="proj-item">
                <div>
                    <b><a href="{{ route('portal.project', $p) }}">{{ $p->title }}</a></b>
                    <span class="tag gray">{{ Project::CATEGORIES[$p->category]['label'] }}</span>
                    <div class="hint">
                        {{ $p->trade ?: 'Any trade' }}@if ($p->county) · {{ $p->county }}@if ($p->ward), {{ $p->ward }}@endif @endif
                        @if ($p->hirer->organisation)· {{ $p->hirer->organisation }}@endif
                        @if ($p->budget) · KSh {{ number_format((float) $p->budget) }}@endif
                        @if ($p->duration) · {{ $p->duration }}@endif
                    </div>
                    <div class="hint">{{ Str::limit($p->description, 130) }}</div>
                </div>
                <div class="proj-side">
                    @if ($p->deadline)<div class="hint">Apply by {{ $p->deadline->format('d M Y') }}</div>@endif
                    <a class="btn btn-sm {{ $applications->contains('project_id', $p->id) ? 'btn-ghost' : 'btn-lime' }}" href="{{ route('portal.project', $p) }}">
                        {{ $applications->contains('project_id', $p->id) ? 'View / manage' : 'View & apply' }}
                    </a>
                </div>
            </div>
        @endforeach
    </div>
    @isset($limit)
        @if ($projects->count() > $limit)
            <p class="more"><a href="{{ route('portal.projects') }}">All {{ $projects->count() }} projects open for you &rarr;</a></p>
        @endif
    @endisset
@endif
