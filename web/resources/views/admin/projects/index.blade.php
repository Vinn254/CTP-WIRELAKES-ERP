@extends('layouts.admin')
@section('title', 'Projects')

@php
    use App\Models\Project;
    $tabs = ['all' => 'All', 'open' => 'Open', 'draft' => 'Draft', 'awarded' => 'Awarded', 'closed' => 'Closed'];
    $pill = ['draft' => 'pending_review', 'open' => 'active', 'awarded' => 'awarded', 'closed' => 'rejected'];
@endphp

@section('content')
<h1>Projects</h1>
<p class="page-lede">Post and manage projects for members to apply on.</p>

<div class="page-actions">
 <button class="btn btn-primary" type="button" data-sheet="post-project">＋ Post a project</button>
 <a class="btn btn-outline btn-sm" href="{{ route('admin.hirers.index') }}">Hirers &amp; organisations</a>
</div>

<div class="stat-row">
 <div class="stat"><small>Open for applications</small><b>{{ $counts['open'] ?? 0 }}</b></div>
 <div class="stat"><small>Awarded</small><b>{{ $counts['awarded'] ?? 0 }}</b></div>
 <div class="stat"><small>Applications waiting</small><b>{{ $pendingApplications }}</b></div>
</div>

<form method="GET" action="{{ route('admin.projects.index') }}" class="filter-bar inline-filter">
 <label class="field">
 <span>Project status</span>
 <select name="status" onchange="this.form.submit()">
 @foreach ($tabs as $s => $lbl)
 <option value="{{ $s }}" @selected($status === $s)>{{ $lbl }}@if ($s !== 'all') ({{ $counts[$s] ?? 0 }})@endif</option>
 @endforeach
 </select>
 </label>
 <noscript><button class="btn btn-sm btn-outline" type="submit">Apply</button></noscript>
</form>

<table class="data">
 <thead>
 <tr><th>Reference</th><th>Project</th><th>For</th><th>Hirer</th><th>Place</th><th>Deadline</th><th>Apps</th><th>Worker</th><th>Status</th><th></th></tr>
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
 <td>
 @if ($p->award)
 <form method="POST" action="{{ route('admin.projects.assign', $p) }}" class="worker-form" data-confirm="Change the worker on {{ $p->reference }}? {{ $p->award->full_name }} will be told they were not selected." data-title="Change worker">
 @csrf
 <input type="hidden" name="applicant_type" value="{{ $p->award->applicant_type }}">
 <select name="applicant_id" aria-label="Worker for {{ $p->reference }}">
 @foreach ($candidates as $c)
 <option value="{{ $c['id'] }}" data-type="{{ $c['type'] }}" @selected($c['type'] === $p->award->applicant_type && (int) $c['id'] === (int) $p->award->applicant_id)>{{ $c['code'] }} · {{ $c['name'] }}</option>
 @endforeach
 </select>
 </form>
 @else
 <span class="hint">Not awarded</span>
 @endif
 </td>
 <td><span class="status {{ $pill[$p->status] ?? 'pending_review' }}">{{ Project::STATUSES[$p->status] ?? $p->status }}</span></td>
 <td class="actions">
 <a class="btn btn-sm btn-outline" href="{{ route('admin.projects.show', $p) }}">Review</a>
 <a class="btn btn-sm btn-ghost" href="{{ route('admin.projects.edit', $p) }}">Edit</a>
 </td>
 </tr>
 @empty
 <tr><td colspan="10" class="empty-cell">No projects in this view yet.</td></tr>
 @endforelse
 </tbody>
</table>

<div class="pagination">{{ $projects->links() }}</div>

@once
<script>
document.querySelectorAll('.worker-form select').forEach(function (select) {
 select.addEventListener('change', function () {
 select.form.applicant_type.value = select.selectedOptions[0].dataset.type;
 select.form.requestSubmit();
 });
});
</script>
@endonce

@include('partials.sheet-start', ['name' => 'post-project', 'title' => 'Post a project',
 'sub' => 'Set the project details and who can apply.'])
 <form method="POST" action="{{ route('admin.projects.store') }}" data-confirm="Post this project for members to apply on?" data-title="Post project">
 @csrf
 <input type="hidden" name="_sheet" value="post-project">
 @include('admin.projects.fields', [
 'project' => $newProject,
 'editing' => false,
 'counties' => $counties,
 'hirers' => $hirers,
 'communities' => $communities,
 ])
 <div class="sheet-actions">
 <button class="btn btn-sm btn-ghost" type="button" data-close-sheet>Cancel</button>
 <button class="btn btn-primary" type="submit">Post project</button>
 </div>
 </form>
@include('partials.sheet-end')
@endsection
