@extends('layouts.admin')
@section('title', $project->reference)

@php
    use App\Models\Project;
    $pill = ['draft' => 'pending_review', 'open' => 'active', 'awarded' => 'awarded', 'closed' => 'rejected'];
    $pending = $project->applications->where('status', 'pending_review');
    $award = $project->award;
@endphp

@section('content')
<p class="back"><a href="{{ route('admin.projects.index') }}">&larr; Projects</a></p>

<div class="proj-head">
 <div>
 <span class="status {{ $pill[$project->status] }}">{{ Project::STATUSES[$project->status] }}</span>
 <span class="tag {{ $project->category === 'senior_fundi' ? 'gold' : '' }}">{{ Project::CATEGORIES[$project->category]['label'] }}</span>
 <h1 style="margin:8px 0 4px;">{{ $project->title }}</h1>
 <div class="hint">{{ $project->reference }} · posted {{ $project->created_at->format('d M Y') }}@if ($project->poster) by {{ $project->poster->name }}@endif</div>
 </div>
 <div class="page-actions">
 <a class="btn btn-primary btn-sm" href="{{ route('admin.projects.edit', $project) }}">Edit project</a>
 <form method="POST" action="{{ route('admin.projects.delete', $project) }}" data-danger data-confirm="Delete {{ $project->reference }} and all its applications?">
 @csrf @method('DELETE')
 <button class="btn btn-ghost btn-sm" type="submit">Delete</button>
 </form>
 </div>
</div>

<div class="proj-grid">
 <div class="card">
 <h3>Details</h3>
 <dl class="kv">
 <div><dt>Hirer</dt><dd>{{ $project->hirer->organisation ?: '—' }}@if ($project->hirer->contact_name)<div class="hint">{{ $project->hirer->contact_name }} · {{ $project->hirer->email }}</div>@endif</dd></div>
 <div><dt>Trade</dt><dd>{{ $project->trade ?: 'Any trade' }}</dd></div>
 <div><dt>Place</dt><dd>{{ collect([$project->county, $project->ward])->filter()->implode(', ') ?: '—' }}</dd></div>
 <div><dt>Budget</dt><dd>{{ $project->budget !== null ? 'KSh '.number_format((float) $project->budget, 2) : 'Not set' }}</dd></div>
 <div><dt>Duration</dt><dd>{{ $project->duration ?: '—' }}</dd></div>
 <div><dt>Deadline</dt><dd>{{ $project->deadline ? $project->deadline->format('d M Y') : 'Open until closed' }}</dd></div>
 <div><dt>Who can apply</dt><dd>{{ Project::CATEGORIES[$project->category]['hint'] }}</dd></div>
 @if ($award)
 <div><dt>Awarded to</dt><dd><b>{{ $award->full_name }}</b>
 <span class="tag gray">{{ $award->memberCode() }}</span> <span class="hint">{{ $award->levelLabel() }}</span>
 @if ($award->skillsLabel())<div class="hint">Skills: {{ $award->skillsLabel() }}</div>@endif
 <div class="hint">{{ $award->phone }} · {{ $award->email }}</div>
 <div class="hint"><a href="{{ route('admin.registrations.show', [$award->applicant_type, $award->applicant_id]) }}">Open their full record →</a></div></dd></div>
 @endif
 </dl>
 @if ($project->status === 'awarded')
 <div class="worker-swap">
 <label class="swap-label" for="workerSelect">Change worker</label>
 <form method="POST" action="{{ route('admin.projects.assign', $project) }}" class="inline-form"
       data-confirm="Hand {{ $project->reference }} to the selected member? {{ $award->full_name }} will be told they were not selected." data-title="Change worker">
 @csrf
 <input type="hidden" name="applicant_type" id="swapType" value="{{ $award->applicant_type }}">
 <select name="applicant_id" id="workerSelect" required>
 @foreach ($candidates as $c)
 <option value="{{ $c['id'] }}" data-type="{{ $c['type'] }}" @selected($c['type'] === $award->applicant_type && (int) $c['id'] === (int) $award->applicant_id)>{{ $c['code'] }} · {{ $c['name'] }}</option>
 @endforeach
 </select>
 <button class="btn btn-sm btn-lime" type="submit">Change worker</button>
 </form>
 <div class="hint">Every approved member can take this job — {{ count($candidates) }} on the list.</div>
 </div>
 @endif
 <p style="white-space:pre-line;margin:14px 0 0;">{{ $project->description }}</p>
 @if ($project->materials)
 <h3 style="margin:18px 0 0;">Materials required</h3>
 <p style="white-space:pre-line;margin:8px 0 0;">{{ $project->materials }}</p>
 @endif
 </div>

 <div class="card">
 <h3>Status</h3>
 @if ($project->status === 'awarded')
 <p class="hint">Awarded {{ $project->awarded_at?->format('d M Y, H:i') }}. Members can no longer apply. To swap the worker, use <b>Change worker to this member</b> on any applicant below — no need to release first.</p>
 <form method="POST" action="{{ route('admin.projects.release', $project) }}" data-confirm="Release the award and reopen {{ $project->reference }} for applications?">
 @csrf
 <button class="btn btn-outline btn-sm" type="submit">Release award &amp; reopen</button>
 </form>
 @else
 <div class="inline-form">
 @foreach (['open' => 'Open for applications', 'closed' => 'Close', 'draft' => 'Move to draft'] as $s => $lbl)
 @if ($project->status !== $s)
 <form method="POST" action="{{ route('admin.projects.status', $project) }}">
 @csrf
 <input type="hidden" name="status" value="{{ $s }}">
 <button class="btn btn-sm {{ $s === 'open' ? 'btn-lime' : 'btn-ghost' }}" type="submit">{{ $lbl }}</button>
 </form>
 @endif
 @endforeach
 </div>
 <div class="hint" style="margin-top:10px;">Closing a project hides it from every member portal immediately.</div>
 @endif
 </div>
</div>

<h2 style="margin-top:26px;">Applications <span class="tag">{{ $project->applications->count() }}</span>@if ($pending->count()) <span class="nav-badge">{{ $pending->count() }}</span>@endif</h2>

@forelse ($project->applications as $app)
 <div class="invite-row {{ $app->status === 'rejected' ? 'revoked' : '' }}">
 <div class="invite-main">
 <b>{{ $app->full_name }}</b>
 <span class="tag gray">{{ $app->memberCode() }}</span>
 <span class="tag gray">{{ $app->levelLabel() }}</span>
 @if ($app->status === 'approved')<span class="status awarded">Awarded</span>
 @elseif ($app->status === 'rejected')<span class="status rejected">Not selected</span>
 @else<span class="status pending_review">Pending</span>@endif
 @if ($app->skillsLabel())<div class="hint">Skills: {{ $app->skillsLabel() }}</div>@endif
 <div class="hint">{{ $app->phone }} · {{ $app->email }}</div>
 @if ($app->note)<div class="hint" style="margin-top:4px;">“{{ $app->note }}”</div>@endif
 @if ($app->rejection_reason && $app->status === 'rejected')<div class="hint">Reason: {{ $app->rejection_reason }}</div>@endif
 <div class="hint">Applied {{ $app->created_at->format('d M Y, H:i') }}</div>
 </div>
 <div class="invite-actions">
 @if ($project->status === 'awarded' && $app->status !== 'approved')
 <form method="POST" action="{{ route('admin.projects.award', [$project, $app]) }}" data-confirm="Change the worker on {{ $project->reference }} to {{ $app->full_name }}? {{ $award->full_name }} will be told they were not selected." data-title="Change worker">
 @csrf
 <button class="btn btn-sm btn-outline" type="submit">Change worker to this member</button>
 </form>
 @elseif ($project->status === 'awarded')
 <span class="hint">Current worker — change them in Project Details above.</span>
 @elseif ($app->status === 'pending_review')
 <form method="POST" action="{{ route('admin.projects.award', [$project, $app]) }}" data-confirm="Assign {{ $project->reference }} to {{ $app->full_name }}? Other applicants will be declined.">
 @csrf
 <button class="btn btn-sm btn-lime" type="submit">Assign to this member</button>
 </form>
 <form method="POST" action="{{ route('admin.projects.decline', [$project, $app]) }}" class="inline-form mini-edit">
 @csrf
 <input type="text" name="rejection_reason" maxlength="300" placeholder="Optional reason">
 <button class="btn btn-sm btn-danger" type="submit">Decline</button>
 </form>
 @else
 <span class="hint">Already reviewed.</span>
 @endif
 </div>
 </div>
@empty
 <div class="card"><p style="margin:0;color:var(--muted);">No member has applied yet. Share the activation link with eligible members so they can sign in and apply.</p></div>
@endforelse

@once
<script>
const workerSelect = document.getElementById('workerSelect');
if (workerSelect) {
 workerSelect.addEventListener('change', function () {
 document.getElementById('swapType').value = workerSelect.selectedOptions[0].dataset.type;
 });
}
</script>
@endonce
@endsection
