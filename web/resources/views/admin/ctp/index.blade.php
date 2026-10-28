@extends('layouts.admin')
@section('title', 'CTP profiles')

@php
    use App\Models\CtpRegistration;
    use App\Models\FundiRegistration;
    $tabs = ['all' => 'All'] + collect(CtpRegistration::STATUSES)->map(fn ($l) => $l)->all();
    $certClass = fn (?string $s) => $s === 'approved' ? 'active' : ($s === 'recommended' ? 'pending_review' : 'closed');
@endphp

@section('content')
<h1>CTP profiles</h1>
<p class="page-lede">Every community technical promoter and where each one stands.</p>

<div class="stat-row">
    <div class="stat"><small>Active CTPs</small><b>{{ $counts['active'] ?? 0 }}</b></div>
    <div class="stat"><small>Waiting on a payment</small><b>{{ $counts['pending_payment'] ?? 0 }}</b></div>
    <div class="stat"><small>Awaiting review</small><b>{{ $counts['pending_review'] ?? 0 }}</b></div>
    <div class="stat"><small>Deployed</small><b>{{ ($counts['deployed'] ?? 0) + ($counts['graduated'] ?? 0) }}</b></div>
    <div class="stat"><small>Alumni</small><b>{{ $alumniTotal }}</b></div>
</div>

<p style="margin:0 0 14px;">
    <a class="btn btn-lime btn-sm" href="{{ route('admin.promoters.create') }}">+ Add a CTP from the office</a>
    <span class="hint" style="margin-left:10px;">
        For someone who cannot reach the website — the same application and review steps.
    </span>
</p>

<form method="GET" class="filter-bar">
    <input type="search" name="q" value="{{ $search }}" placeholder="Search ID, name, phone or county" aria-label="Search ID, name, phone or county">
    <label class="field">
        <span>CTP status</span>
        <select name="status" onchange="this.form.submit()">
            @foreach ($tabs as $s => $lbl)
                <option value="{{ $s }}" @selected($status === $s)>{{ $lbl }}@if ($s !== 'all') ({{ $counts[$s] ?? 0 }})@endif</option>
            @endforeach
        </select>
    </label>
    <button class="btn btn-sm btn-outline" type="submit">Search</button>
    @if ($search)<a class="btn btn-sm btn-ghost" href="{{ route('admin.promoters.index', ['status' => $status]) }}">Clear</a>@endif
</form>

<table class="data">
    <thead>
        <tr>
            <th>CTP ID</th><th>Name</th><th>Track</th><th>County</th>
            <th title="People this CTP mobilised who paid and were approved">Mobilised</th>
            <th>Payment</th><th>Status</th><th>Joined</th><th></th>
        </tr>
    </thead>
    <tbody>
        @forelse ($ctps as $c)
            <tr>
                <td><b>{{ $c->ctp_code ?: '—' }}</b>@if (! $c->ctp_code)<div class="hint">not issued yet</div>@endif</td>
                <td>
                    <a href="{{ route('admin.promoters.show', $c) }}"><b>{{ $c->full_name }}</b></a>
                    <div class="hint">{{ $c->phone }} · {{ $c->email }}</div>
                </td>
                <td><span class="tag">{{ Str::headline($c->track) }}</span>
                    @if ($c->fundi_tier)<div class="hint">reads as {{ $c->gradeLabel() }}</div>@endif</td>
                <td>{{ $c->county }}<div class="hint">{{ collect([$c->sub_county, $c->ward])->filter()->join(', ') }}</div></td>
                <td>{{ $c->mobilised_count }}</td>
                <td><span class="fee-tag {{ $c->fee_status }}">{{ CtpRegistration::FEE_STATUSES[$c->fee_status] ?? $c->fee_status }}</span></td>
                <td><span class="status {{ $c->status }}">{{ $c->statusLabel() }}</span></td>
                <td>{{ $c->created_at->format('d M Y') }}</td>
                <td class="actions"><a class="btn btn-sm btn-outline" href="{{ route('admin.promoters.show', $c) }}">Open profile</a></td>
            </tr>
        @empty
            <tr><td colspan="9" class="empty-cell">No CTPs in this view yet.</td></tr>
        @endforelse
    </tbody>
</table>

<div class="pagination">{{ $ctps->links() }}</div>

<div class="section-head">
    <h3>Alumni — CTPs who finished their training</h3>
    <span class="tag">{{ $alumniTotal }} {{ Str::plural('alumnus', $alumniTotal) }}</span>
</div>
<p class="hint">
    A CTP comes to this list when the cohort marks the end of their track, and stays on it whether or not an
    executive has signed the certificate yet. Moving one into a fundi pool <b>is</b> the transition: the record stays
    this same row, the grade written on it reads Junior or Senior Fundi from then on, and that is the grade they show
    as on their own portal, on their profile and on every office screen. Nothing is re-registered and nobody is
    added twice.
</p>

<table class="data">
    <thead>
        <tr>
            <th>CTP ID</th><th>Name</th><th>County</th><th>Training finished</th><th>Certificate</th>
            <th title="Attachment work this CTP has handed over on a project">Attachment work</th>
            <th>Mobilised</th><th>Move on as</th><th></th>
        </tr>
    </thead>
    <tbody>
        @forelse ($alumni as $a)
            @php
                $seats = $a->finishedTrainings();
                $cert = $a->liveCertification();
                $forwarded = $seats->map->certification->filter()->first();
                $led = $alumniLedger[$a->id] ?? null;
            @endphp
            <tr>
                <td><b>{{ $a->ctp_code ?: '—' }}</b>@if (! $a->ctp_code)<div class="hint">not issued yet</div>@endif</td>
                <td>
                    <a href="{{ route('admin.promoters.show', $a) }}"><b>{{ $a->full_name }}</b></a>
                    <div class="hint">{{ $a->statusLabel() }} · {{ Str::headline($a->track) }} track</div>
                </td>
                <td>{{ $a->county ?: '—' }}
                    <div class="hint">{{ collect([$a->sub_county, $a->ward])->filter()->join(', ') }}</div></td>
                <td>
                    @forelse ($seats as $s)
                        <div>{{ $s->cohort?->courseLabel() ?? 'A training run' }}</div>
                        <div class="hint">{{ $s->cohort?->ends_on ? 'ended '.$s->cohort->ends_on->format('d M Y') : 'the cohort carries no end date' }}</div>
                    @empty
                        <span class="hint">no finished seat on record</span>
                    @endforelse
                </td>
                <td>
                    @if ($cert)
                        <span class="status approved">{{ $cert->statusLabel() }}</span>
                        <div class="hint">{{ $cert->certificate_code }}@if ($cert->approved_on) · {{ $cert->approved_on->format('d M Y') }}@endif</div>
                        <div class="hint">theory {{ $cert->theory_score ?? '—' }} · practical {{ $cert->practical_score ?? '—' }} · attendance {{ $cert->attendance_pct ?? '—' }}%</div>
                    @elseif ($forwarded)
                        <span class="status {{ $certClass($forwarded->status) }}">{{ $forwarded->statusLabel() }}</span>
                        <div class="hint">{{ $forwarded->status === 'recommended' ? 'waiting on an executive to approve it' : $forwarded->reason }}</div>
                    @else
                        <span class="status closed">Not put forward</span>
                        <div class="hint">no certificate recommended for this training</div>
                    @endif
                </td>
                <td>{{ $led['delivered'] ?? 0 }} delivered
                    <div class="hint">{{ $led['verdict'] ?? 'No work handed over yet' }}</div></td>
                <td>{{ $a->mobilised_count }}</td>
                <td>
                    <form method="POST" action="{{ route('admin.promoters.tier', $a) }}" class="inline-form"
                          data-confirm="Change what {{ $a->full_name }} reads as? Their record stays this row."
                          data-title="Move on as">
                        @csrf
                        <label class="hint" for="pool-{{ $a->id }}">Grade</label>
                        <select name="fundi_tier" id="pool-{{ $a->id }}">
                            <option value="">CTP trainee</option>
                            @foreach ($tiers as $key => $desc)
                                <option value="{{ $key }}" @selected($a->fundi_tier === $key)>{{ FundiRegistration::tierLabel($key) }}</option>
                            @endforeach
                        </select>
                        <button class="btn btn-sm btn-primary" type="submit">Save</button>
                    </form>
                    <div class="hint">Shows as <b>{{ $a->gradeLabel() }}</b>@if ($a->fundi_tier) · takes their projects @else · attachment work only @endif</div>
                </td>
                <td class="actions"><a class="btn btn-sm btn-outline" href="{{ route('admin.promoters.show', $a) }}">Open profile</a></td>
            </tr>
        @empty
            <tr><td colspan="9" class="empty-cell">
                Nobody has finished a training run yet. A CTP appears here when the cohort marks the end of their
                track, or when an executive approves a certificate for them — then this row is where the office moves
                them on into a fundi pool.
            </td></tr>
        @endforelse
    </tbody>
</table>

<div class="pagination">{{ $alumni->links() }}</div>
@endsection
