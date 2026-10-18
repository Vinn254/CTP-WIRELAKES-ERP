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
 <div><dt>Place</dt><dd>{{ collect([$project->county, $project->ward])->filter()->implode(', ') ?: '—' }}
 @if ($project->community->exists)<div class="hint">Community <a href="{{ dept_link('admin.communities.show', ['community' => $project->community_id]) }}">{{ $project->community->community_code ?: $project->community->name }}</a></div>@endif</dd></div>
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
 <form method="POST" action="{{ route('admin.projects.assign', $project) }}" class="inline-form"
       data-confirm="Hand {{ $project->reference }} to the selected member? {{ $award->full_name }} will be told they were not selected." data-title="Change worker">
 @csrf
 <input type="hidden" name="applicant_type" id="swapType" value="{{ $award->applicant_type }}">
 <span class="fld"><label for="workerSelect">New worker <span class="req">*</span></label>
 <select name="applicant_id" id="workerSelect" required>
 @foreach ($candidates as $c)
 <option value="{{ $c['id'] }}" data-type="{{ $c['type'] }}" @selected($c['type'] === $award->applicant_type && (int) $c['id'] === (int) $award->applicant_id)>{{ $c['code'] }} · {{ $c['name'] }}</option>
 @endforeach
 </select>
 </span>
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

<h2 style="margin-top:26px;">Materials the programme provides <span class="tag">{{ $project->providedMaterials->count() }}</span></h2>

<div class="card">
 <p class="help">What the organisation hands over for this job, with a cost on every line. The fundi sees exactly this table on the project. Once a line is recorded as handed over, its value is what the payout deducts (§14.6 step 6) — so the worker is never paid the full agreed amount for a job whose materials the programme bought.</p>

 @if ($project->providedMaterials->isEmpty())
 <p class="hint">Nothing is listed yet. Add the cable, boards and fittings the programme is supplying before the work starts.</p>
 @else
 <table class="data">
 <thead>
 <tr><th>Material</th><th>From the store</th><th>Quantity</th><th>Cost each</th><th>Value</th><th>Handed over</th><th></th></tr>
 </thead>
 <tbody>
 @foreach ($project->providedMaterials as $m)
 <tr>
 <td><b>{{ $m->name }}</b>@if ($m->note)<div class="hint">{{ $m->note }}</div>@endif</td>
 <td>@if ($m->item->exists)<a class="hint" href="{{ dept_link('admin.inventory.show', ['item' => $m->item]) }}">{{ $m->item->sku }}</a>@else <span class="hint">bought for the job</span>@endif</td>
 <td>{{ $m->quantityLabel() }}</td>
 <td>{{ $m->unitCostLabel() }}</td>
 <td><b>{{ $m->totalLabel() }}</b></td>
 <td>
 @if ($m->isSupplied())
 <span class="status active">Handed over {{ $m->supplied_on->format('d M Y') }}</span>
 <div class="hint">by {{ $m->provider->name ?: 'the office' }}</div>
 @else
 <span class="status pending_review">Not yet given</span>
 @endif
 </td>
 <td>
 @unless ($m->isSupplied())
 <details class="need-form">
 <summary>Hand over / correct</summary>
 <form method="POST" action="{{ route('admin.projects.materials.supply', $m) }}" class="fee-form"
  data-confirm="Record {{ $m->name }} as handed over on this date?" data-title="Hand over material">
  @csrf
  <div class="field"><label>Handed over on <span class="req">*</span></label>
   <input type="date" name="supplied_on" value="{{ now()->toDateString() }}" required></div>
  <div class="field"><label>What to note about it</label>
   <input type="text" name="note" maxlength="1000" placeholder="e.g. Two coils left at the site store with the caretaker."></div>
  <button class="btn btn-lime btn-block" type="submit">Record as handed over</button>
 </form>
 <form method="POST" action="{{ route('admin.projects.materials.update', $m) }}" class="fee-form"
  data-confirm="Correct this line?" data-title="Correct the line">
  @csrf @method('PATCH')
  <div class="form-grid-2">
   <div class="field"><label>Quantity</label><input type="number" name="quantity" step="0.01" min="0.01" value="{{ $m->quantity }}"></div>
   <div class="field"><label>Cost each (KSh)</label><input type="number" name="unit_cost_ksh" step="0.01" min="0" value="{{ $m->unit_cost_ksh }}"></div>
  </div>
  <div class="field"><label>Note</label><input type="text" name="note" maxlength="1000" value="{{ $m->note }}"></div>
  <button class="btn btn-outline btn-block" type="submit">Save the correction</button>
 </form>
 </details>
 @endunless
 </td>
 </tr>
 @endforeach
 </tbody>
 <tfoot>
 <tr>
 <td colspan="4"><b>Planned {{ \App\Support\Finance::ksh($project->materialsPlannedKsh()) }} · handed over {{ \App\Support\Finance::ksh($project->materialsSuppliedKsh()) }}</b></td>
 <td colspan="3" class="hint">The handed-over figure is the deduction the payout screen uses.</td>
 </tr>
 </tfoot>
 </table>
 @endif

 <div class="two-col" style="margin-top:14px;">
 <details class="need-form">
 <summary>Add a material the programme is supplying</summary>
 <form method="POST" action="{{ route('admin.projects.materials.store', $project) }}" class="fee-form" data-title="Add material">
  @csrf
  <div class="field"><label>Material <span class="req">*</span></label>
   <input type="text" name="name" maxlength="190" required placeholder="e.g. Cable, 2.5 mm single core — 90 m coil"></div>
  <div class="form-grid-2">
   <div class="field"><label>Quantity <span class="req">*</span></label><input type="number" name="quantity" step="0.01" min="0.01" required></div>
   <div class="field"><label>Unit</label>
    <select name="unit">@foreach ($units as $key => $label)<option value="{{ $key }}">{{ $label }}</option>@endforeach</select></div>
  </div>
  <div class="field"><label>Cost each (KSh) <span class="req">*</span></label><input type="number" name="unit_cost_ksh" step="0.01" min="0" required></div>
  <div class="field"><label>Note</label><input type="text" name="note" maxlength="1000" placeholder="Where it comes from, who fits it."></div>
  <button class="btn btn-primary btn-block" type="submit">Add the line</button>
 </form>
 </details>

 <details class="need-form">
 <summary>Take it from a stock card</summary>
 <form method="POST" action="{{ route('admin.projects.materials.store', $project) }}" class="fee-form" data-title="Issue from the store">
  @csrf
  <div class="field"><label>Stock card <span class="req">*</span></label>
   <select name="inventory_item_id" required>
    <option value="">Select a card</option>
    @foreach ($cards as $c)<option value="{{ $c->id }}">{{ $c->sku }} — {{ $c->name }} ({{ \App\Support\Finance::ksh((float) $c->unit_cost_ksh) }} each)</option>@endforeach
   </select>
   <div class="help">The name, unit and cost come off the card. Booking the goods out on the card itself is what moves the store quantity (§14.5) — this line only says the job was given it.</div>
  </div>
  <div class="field"><label>Quantity <span class="req">*</span></label><input type="number" name="quantity" step="0.01" min="0.01" required></div>
  <div class="field"><label>Cost each (KSh)</label><input type="number" name="unit_cost_ksh" step="0.01" min="0" placeholder="Leave empty to use the card's cost"></div>
  <div class="field"><label>Note</label><input type="text" name="note" maxlength="1000"></div>
  <button class="btn btn-lime btn-block" type="submit">Add from the store</button>
 </form>
 </details>
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
 <input type="text" name="rejection_reason" maxlength="300" placeholder="Optional reason" aria-label="Reason for declining (optional)">
 <button class="btn btn-sm btn-danger" type="submit">Decline</button>
 </form>
 @else
 <span class="hint">Already reviewed.</span>
 @endif
 </div>
 </div>
@empty
 <div class="card"><p class="page-lede">No member has applied yet. Share the activation link with eligible members so they can sign in and apply.</p></div>
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
