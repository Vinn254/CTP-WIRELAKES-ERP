@php
    use App\Models\Project;
    use App\Models\ProjectApplication;
    use Illuminate\Support\Str;
    // Staff previewing a trainee cannot post through the member portal, so links stay inside the preview.
    $open = fn ($p) => isset($previewBase) ? $previewBase.'/project/'.$p->id : route('portal.project', $p);
@endphp

<h2>Available projects <span class="tag">{{ $projects->count() }}</span></h2>
@if ($projects->isEmpty())
    <div class="card"><p style="margin:0;color:var(--muted);">No projects are open at your level right now. Check back soon — the programme office posts new work here every week.</p></div>
@else
    <div class="proj-list">
        @foreach ($projects as $p)
            <div class="proj-item">
                <div>
                    <b><a href="{{ $open($p) }}">{{ $p->title }}</a></b>
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
                    <a class="btn btn-sm {{ $applications->contains('project_id', $p->id) ? 'btn-ghost' : 'btn-lime' }}" href="{{ $open($p) }}">
                        {{ $applications->contains('project_id', $p->id) ? 'View / manage' : 'View & apply' }}
                    </a>
                </div>
            </div>
        @endforeach
    </div>
@endif

<h2 style="margin-top:28px;">My applications <span class="tag gray">{{ $applications->count() }}</span></h2>
@forelse ($applications as $a)
    <div class="invite-row {{ $a->status === 'rejected' ? 'revoked' : '' }}">
        <div class="invite-main">
            <b>{{ $a->project->title }}</b>
            <span class="status {{ $a->status === 'approved' ? 'awarded' : ($a->status === 'rejected' ? 'rejected' : 'pending_review') }}">
                {{ ProjectApplication::STATUSES[$a->status] ?? $a->status }}
            </span>
            <div class="hint">{{ $a->project->reference }}@if ($a->project->hirer->organisation) · {{ $a->project->hirer->organisation }}@endif · applied {{ $a->created_at->format('d M Y') }}</div>
            @if ($a->status === 'approved')
                <div class="hint">Assigned to you — the programme office will contact you on {{ $a->phone }}.</div>
            @elseif ($a->rejection_reason)
                <div class="hint">Feedback: {{ $a->rejection_reason }}</div>
            @endif
        </div>
        <div class="invite-actions">
            <a class="btn btn-sm btn-outline" href="{{ $open($a->project) }}">Open</a>
            @if ($a->status === 'pending_review' && empty($previewBase))
                <form method="POST" action="{{ route('portal.withdraw', $a) }}" data-confirm="Withdraw your application for {{ $a->project->title }}?">
                    @csrf
                    <button class="btn btn-sm btn-ghost" type="submit">Withdraw</button>
                </form>
            @endif
        </div>
    </div>
@empty
    <div class="card"><p style="margin:0;color:var(--muted);">You have not applied for any project yet. Open a project above and send your application.</p></div>
@endforelse
