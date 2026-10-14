@extends('layouts.member')
@section('title', 'My E-Fundi record')

@php
    use App\Models\FundiRegistration;

    $held = collect($member->specialisations ?? []);
    $served = collect($member->service_counties ?? []);
    $oldSkills = (array) old('specialisations', []);
    $oldCounties = (array) old('service_counties', []);
    $approved = $sites->where('status', 'approved');
    $openApplication = in_array($member->status, ['pending_payment', 'pending_review'], true);
    $place = collect([$member->ward, $member->sub_county, $member->county])->filter()->join(', ');
@endphp

@section('content')
<div class="page-intro">
    <p class="sub">This is your E-Fundi record — the one the programme office, the project matching and the training
        cohorts all read. Keep your <b>availability</b> and <b>skills</b> current here; your grade, membership and
        documents are held by the office and corrected by them.</p>
    <div class="member-stats">
        <div class="stat"><small>E-Fundi ID</small><b>{{ $member->ef_code ?: 'Issued on approval' }}</b></div>
        <div class="stat"><small>Grade</small><b>{{ \Illuminate\Support\Str::limit(FundiRegistration::tierLabel($member->tier), 18) }}</b></div>
        <div class="stat"><small>Availability</small><b>{{ $member->availabilityLabel() }}</b></div>
        <div class="stat"><small>Skills</small><b>{{ $held->count() }}</b></div>
        @if ($member->canTrain())
            <div class="stat"><small>Sites you train at</small><b>{{ $approved->count() }}</b></div>
        @else
            <div class="stat"><small>Open projects</small><b>{{ $projects->count() }}</b></div>
        @endif
    </div>
</div>

@if (! $member->isMatchable())
    <div class="card">
        <h3>You are not in the work pool right now</h3>
        <p class="help">
            @if ($openApplication)
                Your application is <b>{{ $member->statusLabel() }}</b>. Once the programme office approves it your EF ID is
                issued and you can apply for open projects.
            @elseif ($member->status === 'active' && $member->availability !== 'available')
                You have marked yourself <b>{{ $member->availabilityLabel() }}</b>. Set it back to
                <b>Available for work</b> below as soon as you can take a job — matching only looks at available fundis.
            @else
                Your standing is <b>{{ $member->statusLabel() }}</b>. Contact the programme office — a suspended or
                rejected record is reopened by them, and nothing on this record is deleted.
            @endif
        </p>
    </div>
@endif

<div class="card">
    <div class="section-head">
        <h3>What your grade carries</h3>
        <span class="tag">{{ FundiRegistration::tierLabel($member->tier) }}</span>
    </div>
    <p class="hint">Your grade decides which of these your own portal opens. The programme office moves a fundi up when
        they are ready to carry more.</p>
    @foreach ($member->capabilities() as $ability)
        <div class="row-line">
            <b>{{ FundiRegistration::CAPABILITY_LABELS[$ability][0] }}</b>
            <span class="hint">{{ FundiRegistration::CAPABILITY_LABELS[$ability][1] }}</span>
        </div>
    @endforeach
    <div class="row-line">
        <b>Not open to your grade</b>
        <span class="hint">{{ collect(array_keys(FundiRegistration::CAPABILITY_LABELS))->reject(fn ($a) => in_array($a, $member->capabilities(), true))
            ->map(fn ($a) => FundiRegistration::CAPABILITY_LABELS[$a][0])->join(' · ') ?: 'Nothing — a senior fundi carries the whole list.' }}</span>
    </div>
</div>

<div class="card">
    <div class="section-head">
        <h3>Availability</h3>
        <span class="status {{ $member->isMatchable() ? 'active' : 'pending_review' }}">{{ $member->availabilityLabel() }}</span>
    </div>
    <p class="hint">Say it here as it changes. A fundi marked <b>Available for work</b> is the one the office and the
        matching see for a job; <b>On a job</b> and <b>On leave</b> take you out of it until you say otherwise.</p>
    <form method="POST" action="{{ route('portal.efundi.availability') }}" class="fee-form"
          data-confirm="Save your availability?" data-title="Availability">
        @csrf
        <div class="form-grid-2">
            <div class="field">
                <label>I am <span class="req">*</span></label>
                <select name="availability" required>
                    @foreach ($availabilities as $key => $label)
                        <option value="{{ $key }}" @selected(old('availability', $member->availability) === $key)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="field">
                <label>Note for the office</label>
                <input type="text" name="availability_note" maxlength="300"
                       value="{{ old('availability_note', $member->availability_note) }}"
                       placeholder="e.g. Free from 5 December — the Kisumu job ends on the 30th">
            </div>
        </div>
        <button class="btn btn-primary" type="submit" style="margin-top:12px;">Save availability</button>
    </form>
</div>

<div class="card">
    <div class="section-head">
        <h3>Skills and counties you cover</h3>
        <span class="tag">{{ $held->count() }} skill{{ $held->count() === 1 ? '' : 's' }}</span>
        <span class="tag">{{ $served->count() }} count{{ $served->count() === 1 ? 'y' : 'ies' }}</span>
    </div>
    <p class="hint">Only the trades the programme runs are on this list. Pick at least one — a job is matched to a fundi
        by trade first, then by the counties you said you will travel to.</p>
    <form method="POST" action="{{ route('portal.efundi.skills') }}" class="fee-form"
          data-confirm="Update your skills and counties?" data-title="Skills">
        @csrf
        <div class="field">
            <label>What you can do <span class="req">*</span></label>
            <div style="display:flex;flex-wrap:wrap;gap:4px 18px;margin:8px 0;">
                @foreach ($specialisations as $s)
                    <label class="check-row" style="margin:0 14px 0 0;">
                        <input type="checkbox" name="specialisations[]" value="{{ $s }}"
                               @checked(in_array($s, array_merge($oldSkills, $held->all()), true))> {{ $s }}</label>
                @endforeach
            </div>
        </div>
        <div class="field" style="margin-top:12px;">
            <label>Counties you will work in</label>
            <select name="service_counties[]" multiple size="6">
                @foreach ($counties as $c)
                    <option value="{{ $c }}" @selected(in_array($c, array_merge($oldCounties, $served->all()), true))>{{ $c }}</option>
                @endforeach
            </select>
            <div class="help">Hold Ctrl (or Cmd) to pick several. Leave empty for your home county only.</div>
        </div>
        <button class="btn btn-primary" type="submit" style="margin-top:12px;">Save skills</button>
    </form>
</div>

<div class="grid-2">
    <div class="card">
        <h3>Your record as the office holds it</h3>
        <dl class="kv">
            <dt>Name</dt><dd>{{ $member->full_name }}</dd>
            <dt>National ID</dt><dd>{{ $member->national_id }}</dd>
            <dt>Phone</dt><dd>{{ $member->phone }}</dd>
            <dt>Email</dt><dd>{{ $member->email }}</dd>
            <dt>Grade</dt><dd>{{ FundiRegistration::TIERS[$member->tier] ?? $member->tier }}</dd>
            <dt>Where you work</dt><dd>{{ $place ?: '—' }}</dd>
            <dt>Business</dt><dd>{{ $member->business_name ?: 'I trade as myself' }}
                @if ($member->business_reg_no)<span class="hint">· {{ $member->business_reg_no }}</span>@endif</dd>
            <dt>Years trading</dt><dd>{{ $member->years_in_business !== null ? $member->years_in_business : '—' }}</dd>
            <dt>Standing</dt><dd><span class="status {{ $member->status === 'active' ? 'active' : ($openApplication ? 'pending_review' : 'closed') }}">{{ $member->statusLabel() }}</span></dd>
            <dt>Membership fee</dt><dd><span class="fee-tag {{ $member->fee_status }}">{{ FundiRegistration::FEE_STATUSES[$member->fee_status] ?? $member->fee_status }}</span></dd>
        </dl>
        <p class="help">A grade, a document or a standing is the office's decision — ask them and they edit this record.
            Nothing is deleted: a suspended or rejected fundi is reopened on the same row.</p>
    </div>

    <div class="stack">
        @if ($member->canTrain())
            <div class="card">
                <div class="section-head">
                    <h3>Sites you train CTPs at</h3>
                    <span class="tag">{{ $sites->count() }}</span>
                </div>
                <p class="hint">The office approves a trainer <b>site by site</b>. Applying for another site, and the marks you
                    put in, are on <a href="{{ route('portal.trainer') }}">Training CTPs</a>.</p>
                @foreach ($sites as $s)
                    <div class="row-line">
                        <b>{{ $s->venue->name ?? 'A site' }}</b>
                        <span class="status {{ $s->status === 'approved' ? 'active' : ($s->status === 'applied' ? 'pending_review' : 'closed') }}">{{ $s->statusLabel() }}</span>
                        <span class="hint">{{ $s->teachingLabel() ?: 'no trainings listed' }}</span>
                    </div>
                @endforeach
                @if ($sites->isEmpty())
                    <p class="hint">You are not registered to train anywhere yet. Put a site forward on
                        <a href="{{ route('portal.trainer') }}">Training CTPs</a> — the office approves a trainer
                        <b>site by site</b> before a cohort is handed over.</p>
                @endif
            </div>
        @endif

        <div class="card">
            <h3>Projects you can apply for</h3>
            <p class="hint">You apply for the jobs your grade covers, from <a href="{{ route('portal.projects') }}">My projects</a>.
                @unless ($member->can('supervise'))
                    A junior fundi, an intern or an attachment applicant is taken onto a job <b>under a senior fundi</b>.
                @endunless</p>
            <span class="chips">
                @foreach (\App\Models\Project::CATEGORIES as $key => $rule)
                    @if (in_array($member->tier, $rule['tiers'] ?? [], true))
                        <span class="chip">{{ $rule['label'] }}</span>
                    @endif
                @endforeach
            </span>
            @if ($applications->isNotEmpty())
                <h4 class="dl-group">Your applications</h4>
                @foreach ($applications->take(5) as $a)
                    <div class="row-line">
                        <b>{{ $a->project->title ?? 'Project' }}</b>
                        <span class="hint">{{ $a->project->reference ?? '' }}</span>
                        <span class="status {{ $a->status === 'approved' ? 'active' : ($a->status === 'pending_review' ? 'pending_review' : 'closed') }}">{{ str_replace('_', ' ', $a->status) }}</span>
                    </div>
                @endforeach
            @endif
        </div>

        <div class="card">
            <h3>Documents on file ({{ count($docs) }})</h3>
            @forelse ($docs as $slot => $doc)
                <div class="row-line">
                    <b>{{ $doc['label'] }}</b>
                    <a class="btn btn-sm btn-outline" href="{{ route('portal.efundi.file', $slot) }}" target="_blank">Open</a>
                </div>
            @empty
                <p class="hint">Nothing was uploaded with your application. Ask the programme office to add a trade
                    certificate or portfolio — they are recorded on your E-Fundi file.</p>
            @endforelse
        </div>
    </div>
</div>
@endsection
