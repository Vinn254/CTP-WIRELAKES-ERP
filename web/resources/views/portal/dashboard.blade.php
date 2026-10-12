@extends('layouts.member')
@section('title', 'Available projects')

@php
    use App\Models\FundiRegistration;
    use App\Models\Project;
    use App\Models\ProjectApplication;
    use Illuminate\Support\Str;
    $isFundi = $member instanceof FundiRegistration;
    $code = $isFundi ? $member->ef_code : $member->ctp_code;
    $levelLabel = $isFundi
        ? FundiRegistration::tierLabel($member->tier)
        : ($member->fundi_tier ? $member->poolLabel() : 'CTP ('.Str::headline($member->track ?? '').')');
    $openApps = $applications->where('status', 'pending_review');
    $won = $applications->where('status', 'approved');
@endphp

@section('content')
<div class="member-hero">
 <div>
 <span class="tag">{{ $isFundi ? 'E-Fundi' : 'CTP' }}@if ($code) · {{ $code }}@endif</span>
 <h1>Hello, {{ Str::before($member->full_name, ' ') }}.</h1>
 <p class="sub">You are registered as <b>{{ $levelLabel }}</b>.
 Only projects at your level appear here.</p>
 </div>
 <div class="member-stats">
 <div class="stat"><small>Open for you</small><b>{{ $projects->count() }}</b></div>
 <div class="stat"><small>Waiting review</small><b>{{ $openApps->count() }}</b></div>
 <div class="stat"><small>Awarded</small><b>{{ $won->count() }}</b></div>
 </div>
</div>

<h2>Available projects <span class="tag">{{ $projects->count() }}</span></h2>
@if ($projects->isEmpty())
 <div class="card"><p style="margin:0;color:var(--muted);">No projects are open at your level right now. Check back soon — the programme office posts new work here every week.</p></div>
@else
 <div class="proj-list">
 @foreach ($projects as $p)
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
 <a class="btn btn-sm btn-outline" href="{{ route('portal.project', $a->project) }}">Open</a>
 @if ($a->status === 'pending_review')
 <form method="POST" action="{{ route('portal.withdraw', $a) }}" data-confirm="Withdraw your application for {{ $a->project->title }}?">
 @csrf
 <button class="btn btn-sm btn-ghost" type="submit">Withdraw</button>
 </form>
 @endif
 </div>
 </div>
@empty
 <div class="card"><p style="margin:0;color:var(--muted);">You have not applied for any project yet.</p></div>
@endforelse
@endsection
