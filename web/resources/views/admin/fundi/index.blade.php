@extends('layouts.admin')
@section('title', 'E-Fundi register')

@php
    use App\Models\FundiRegistration;
    $tabs = ['all' => 'All'] + FundiRegistration::STATUSES;
    $filters = ['tier' => $tier, 'availability' => $availability, 'county' => $county, 'skill' => $skill, 'q' => $search];
@endphp

@section('content')
<h1>E-Fundi register</h1>
<p class="page-lede">Every registered fundi, their skills and whether they are free for work.</p>

<div class="stat-row">
    <div class="stat"><small>On the register</small><b>{{ $counts->sum() }}</b></div>
    <div class="stat"><small>Available for work</small><b>{{ $availableNow }}</b></div>
    <div class="stat"><small>Waiting on a payment</small><b>{{ $counts['pending_payment'] ?? 0 }}</b></div>
    <div class="stat"><small>Awaiting review</small><b>{{ $counts['pending_review'] ?? 0 }}</b></div>
    <div class="stat"><small>Blocked</small><b>{{ ($counts['suspended'] ?? 0) + ($counts['blacklisted'] ?? 0) }}</b></div>
</div>

<p style="margin:0 0 14px;">
    <button class="btn btn-lime btn-sm" type="button" data-sheet="add-fundi">+ Add an E-Fundi from the office</button>
    <span class="hint" style="margin-left:10px;">
        For someone who cannot reach the website — the same application and review steps.
        A person already on the register is reopened on their own record, never added a second time.
    </span>
</p>

<form method="GET" class="filter-bar">
    <input type="search" name="q" value="{{ $search }}" placeholder="Search EF ID, name, phone or business" aria-label="Search EF ID, name, phone or business">
    <button class="btn btn-sm btn-outline" type="submit">Filter</button>
    @if (collect($filters)->filter(fn ($v) => $v !== 'all' && $v !== '')->isNotEmpty())
        <a class="btn btn-sm btn-ghost" href="{{ route('admin.fundis.index', ['status' => $status]) }}">Clear</a>
    @endif

    <div class="form-grid-2">
        <div>
            <label for="status">Fundi status</label>
            <select name="status" id="status" onchange="this.form.submit()">
                @foreach ($tabs as $s => $lbl)
                    <option value="{{ $s }}" @selected($status === $s)>{{ $lbl }}@if ($s !== 'all') ({{ $counts[$s] ?? 0 }})@endif</option>
                @endforeach
            </select>
        </div>
        <div>
            <label for="tier">Grade</label>
            <select name="tier" id="tier">
                <option value="all">Every grade</option>
                @foreach ($tiers as $key => $label)
                    <option value="{{ $key }}" @selected($tier === $key)>{{ \Illuminate\Support\Str::before($label, '—') }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label for="availability">Availability</label>
            <select name="availability" id="availability">
                <option value="all">Any availability</option>
                @foreach ($availabilities as $key => $label)
                    <option value="{{ $key }}" @selected($availability === $key)>{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label for="skill">Trade skill</label>
            <select name="skill" id="skill">
                <option value="all">Any skill</option>
                @foreach ($specialisations as $s)
                    <option value="{{ $s }}" @selected($skill === $s)>{{ $s }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label for="county">County</label>
            <select name="county" id="county">
                <option value="all">Any county</option>
                @foreach ($counties as $c)
                    <option value="{{ $c }}" @selected($county === $c)>{{ $c }}</option>
                @endforeach
            </select>
        </div>
    </div>
</form>

<table class="data">
    <thead>
        <tr>
            <th>E-Fundi ID</th><th>Fundi</th><th>Grade</th><th>Skills</th><th>Works in</th>
            <th>Availability</th><th title="Sites this fundi is registered to train CTPs at">Training sites</th>
            <th>Payment</th><th>Status</th><th></th>
        </tr>
    </thead>
    <tbody>
        @forelse ($fundis as $f)
            <tr>
                <td><b>{{ $f->ef_code ?: '—' }}</b>@if (! $f->ef_code)<div class="hint">{{ $f->reference }}</div>@endif</td>
                <td>
                    <a href="{{ route('admin.fundis.show', $f) }}"><b>{{ $f->full_name }}</b></a>
                    <div class="hint">{{ $f->business_name ?: $f->phone }}@if ($f->mobiliser) · brought by {{ $f->mobiliser->ctp_code }}@endif</div>
                </td>
                <td><span class="tag">{{ FundiRegistration::tierLabel($f->tier) }}</span></td>
                <td>{{ collect($f->specialisations ?? [])->take(3)->join(', ') ?: '—' }}
                    @if (count($f->specialisations ?? []) > 3)<span class="hint">+{{ count($f->specialisations) - 3 }}</span>@endif</td>
                <td>{{ $f->county ?: '—' }}
                    <div class="hint">{{ collect($f->service_counties ?? [])->take(2)->join(', ') }}</div></td>
                <td><span class="status {{ $f->isMatchable() ? 'active' : 'pending_review' }}">{{ $f->availabilityLabel() }}</span></td>
                <td>{{ $f->trainers_count ?: '—' }}</td>
                <td><span class="fee-tag {{ $f->fee_status }}">{{ FundiRegistration::FEE_STATUSES[$f->fee_status] ?? $f->fee_status }}</span></td>
                <td><span class="status {{ in_array($f->status, ['active'], true) ? 'active' : (in_array($f->status, ['pending_payment', 'pending_review'], true) ? 'pending_review' : 'closed') }}">{{ $f->statusLabel() }}</span></td>
                <td class="actions"><a class="btn btn-sm btn-outline" href="{{ route('admin.fundis.show', $f) }}">Open record</a></td>
            </tr>
        @empty
            <tr><td colspan="10" class="empty-cell">
                No E-Fundi matches this view. Fundis arrive here from the public E-Fundi application and from the CTPs who mobilise them.
            </td></tr>
        @endforelse
    </tbody>
</table>

<div class="pagination">{{ $fundis->links() }}</div>

<div class="section-head">
    <h3>What each E-Fundi has delivered</h3>
    <span class="tag">{{ $fundis->total() }} in this view</span>
</div>
<p class="hint">
    Read straight off the work orders for the fundis this page shows, following the same search and filters as
    the register above: the projects each was awarded, whether the work landed inside the deadline written on the
    order, and what a Director had to decide where a hirer raised an issue. The programme holds no review score — this
    delivery record is the standing the matching desk reads, and every figure on a row is the order behind it.
</p>

<table class="data">
    <thead>
        <tr>
            <th>E-Fundi</th><th>Grade</th><th>Works in</th>
            <th class="num" title="Orders the hirer has confirmed or a Director has closed off">Projects delivered</th>
            <th title="Orders still open: issued, on site, awaiting inspection or in dispute">Running</th>
            <th>Value delivered</th><th>How they got on</th><th></th>
        </tr>
    </thead>
    <tbody>
        @forelse ($fundis as $f)
            @php $led = $ledger[$f->id] ?? null; @endphp
            <tr>
                <td><a href="{{ route('admin.fundis.show', $f) }}"><b>{{ $f->full_name }}</b></a>
                    <div class="hint">{{ $f->ef_code ?: $f->reference }}@if ($f->business_name) · {{ $f->business_name }}@endif</div></td>
                <td><span class="tag">{{ $f->gradeLabel() }}</span></td>
                <td>{{ $f->county ?: '—' }}
                    <div class="hint">{{ collect($f->service_counties ?? [])->take(2)->join(', ') }}</div></td>
                <td>{{ $led['delivered'] ?? 0 }}</td>
                <td>{{ $led['running'] ?? 0 }}</td>
                <td>{{ \App\Support\Finance::ksh($led['value'] ?? 0) }}</td>
                <td>{{ $led['verdict'] ?? 'No work handed over yet' }}</td>
                <td class="actions"><a class="btn btn-sm btn-outline" href="{{ route('admin.fundis.show', $f) }}">Open record</a></td>
            </tr>
            @if ($led && $led['orders']->isNotEmpty())
                <tr>
                    <td colspan="8">
                        @foreach ($led['orders'] as $o)
                            <div class="row-line">
                                <b>{{ $o->reference }}</b>
                                <span>{{ $o->project->title ?? 'a project no longer on the register' }}</span>
                                <span class="hint">{{ $o->agreedLabel() }}@if ($o->deadline) · due {{ $o->deadline->format('d M Y') }}@endif</span>
                                <span class="status {{ $o->status === 'cancelled' ? 'closed' : ($o->completed_on ? 'active' : 'pending_review') }}">
                                    {{ $o->completed_on
                                        ? 'delivered '.$o->completed_on->format('d M Y').($o->deadline && $o->completed_on->gt($o->deadline) ? ' — past the deadline' : '')
                                        : $o->statusLabel() }}
                                </span>
                            </div>
                        @endforeach
                    </td>
                </tr>
            @endif
        @empty
            <tr><td colspan="8" class="empty-cell">
                No E-Fundi matches this view. Delivery builds up here as the matching desk awards projects and the
                hirer confirms the work.
            </td></tr>
        @endforelse
    </tbody>
</table>

<p class="hint" style="margin-top:14px;">
    Availability is what the fundi says themselves on their own portal; the office can correct it here when someone
    stops answering or finishes a job without telling us. Grade changes what they can be handed to train — see
    <b>Training &amp; Certification &rsaquo; Trainers</b> for the site approvals themselves.
</p>

@include('partials.sheet-start', ['name' => 'add-fundi', 'title' => 'Add an E-Fundi from the office',
    'sub' => 'Enter an E-Fundi application at the counter for someone offline.'])
    <form method="POST" action="{{ route('admin.fundis.store') }}" enctype="multipart/form-data"
          data-confirm="Add this E-Fundi application from the office?" data-title="Add an E-Fundi from the office">
        @csrf
        <input type="hidden" name="_sheet" value="add-fundi">
        @include('admin.fundi.fields')
        <div class="sheet-actions">
            <button class="btn btn-sm btn-ghost" type="button" data-close-sheet>Cancel</button>
            <button class="btn btn-primary" type="submit">Add E-Fundi application</button>
        </div>
    </form>
@include('partials.sheet-end')
@endsection
