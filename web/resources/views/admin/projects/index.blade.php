@extends('layouts.admin')
@section('title', 'Projects')

@php
    use App\Models\Project;
    $tabs = ['all' => 'All', 'open' => 'Open', 'draft' => 'Draft', 'awarded' => 'Awarded', 'closed' => 'Closed'];
    $pill = ['draft' => 'pending_review', 'open' => 'active', 'awarded' => 'awarded', 'closed' => 'rejected'];
@endphp

@section('content')
<p class="back"><a href="{{ route('admin.dashboard') }}">&larr; Departments</a></p>
<h1>Projects</h1>
<p class="sub" style="color:var(--muted);margin:6px 0 16px;">
 Post projects here — they are <b>never shown on the public site</b>. Approved CTPs and E-Fundis see only the projects
 that match their level, apply from their portal, and you assign one worker per project.
</p>

<div class="page-actions">
 <a class="btn btn-primary" href="{{ route('admin.projects.create') }}">＋ Post a project</a>
 <a class="btn btn-outline btn-sm" href="{{ route('admin.hirers.index') }}">Hirers &amp; organisations</a>
</div>

<div class="stat-row">
 <div class="stat"><small>Open for applications</small><b>{{ $counts['open'] ?? 0 }}</b></div>
 <div class="stat"><small>Awarded</small><b>{{ $counts['awarded'] ?? 0 }}</b></div>
 <div class="stat"><small>Applications waiting</small><b>{{ $pendingApplications }}</b></div>
</div>

<div class="tabs">
 @foreach ($tabs as $s => $lbl)
 <a class="tab {{ $status === $s ? 'on' : '' }}" href="{{ route('admin.projects.index', ['status' => $s]) }}">
 {{ $lbl }}@if ($s !== 'all')<span class="n">{{ $counts[$s] ?? 0 }}</span>@endif
 </a>
 @endforeach
</div>

<table class="data">
 <thead>
 <tr><th>Reference</th><th>Project</th><th>For</th><th>Hirer</th><th>Place</th><th>Deadline</th><th>Apps</th><th>Status</th><th></th></tr>
 </thead>
 <tbody>
 @forelse ($projects as $p)
 <tr>
 <td><b>{{ $p->reference }}</b></td>
 <td>
 <a href="{{ route('admin.projects.show', $p) }}"><b>{{ $p->title }}</b></a>
 <div class="hint">{{ $p->trade ?: 'Any trade' }}@if ($p->duration) · {{ $p->duration }}@endif</div>
 </td>
 <td><span class="tag {{ $p->category === 'senior_fundi' ? 'gold' : '' }}">{{ Project::CATEGORIES[$p->category]['label'] ?? $p->category }}</span></td>
 <td>{{ $p->hirer->organisation ?: '—' }}</td>
 <td>{{ collect([$p->county, $p->ward])->filter()->implode(', ') ?: '—' }}</td>
 <td>{{ $p->deadline ? $p->deadline->format('d M Y') : '—' }}</td>
 <td>{{ $p->applications->count() }}@if ($p->applications->where('status', 'pending_review')->count())<span class="nav-badge">{{ $p->applications->where('status', 'pending_review')->count() }}</span>@endif</td>
 <td><span class="status {{ $pill[$p->status] ?? 'pending_review' }}">{{ Project::STATUSES[$p->status] ?? $p->status }}</span></td>
 <td style="text-align:right;white-space:nowrap;">
 <a class="btn btn-sm btn-outline" href="{{ route('admin.projects.show', $p) }}">Review</a>
 <a class="btn btn-sm btn-ghost" href="{{ route('admin.projects.edit', $p) }}">Edit</a>
 </td>
 </tr>
 @empty
 <tr><td colspan="9" style="text-align:center;color:var(--muted);padding:28px;">No projects in this view yet.</td></tr>
 @endforelse
 </tbody>
</table>

<div class="pagination">{{ $projects->links() }}</div>
@endsection
