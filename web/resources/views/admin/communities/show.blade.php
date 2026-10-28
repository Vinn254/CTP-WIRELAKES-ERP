@extends('layouts.admin')
@section('title', $community->name)

@php
    use App\Models\Community;
    use App\Models\Need;
    $lead = $community->lead();
    $counts = $needs->groupBy('status')->map->count();
    $investment = $projects->sum(fn ($p) => (float) $p->budget);
@endphp

@section('content')
<p class="back"><a href="{{ route('admin.communities.index') }}">&larr; Community register</a></p>

<div class="applicant-head card">
    <span class="avatar-lg">{{ $community->initials() }}</span>
    <div class="ah-main">
        <h1>{{ $community->name }} <span class="status {{ $community->status }}">{{ $community->statusLabel() }}</span></h1>
        <p class="ah-meta">
            <span class="tag">{{ $community->community_code ?: 'ID issued on approval' }}</span>
            <span>{{ $community->shortLocation() }}</span>
            <span>{{ $needs->count() }} needs · {{ $beneficiaries->count() }} beneficiaries · {{ $projects->count() }} projects</span>
            <span>Logged {{ $community->created_at->format('d M Y') }}</span>
            @if ($community->approved_at)<span>Confirmed {{ $community->approved_at->format('d M Y') }}</span>@endif
        </p>
    </div>
    <div class="ah-contact">
        <a class="btn btn-sm btn-outline" href="{{ route('admin.communities.edit', $community) }}">Edit profile</a>
        <a class="btn btn-sm btn-ghost" href="{{ route('admin.needs.index', ['q' => $community->name]) }}">Needs queue</a>
    </div>
</div>

@if ($community->profileGaps())
    <div class="card gaps">
        <b>Still to capture:</b>
        <span class="chips">@foreach ($community->profileGaps() as $g)<span class="chip">{{ $g }}</span>@endforeach</span>
        <a class="btn btn-sm btn-outline" href="{{ route('admin.communities.edit', $community) }}">Fill them in</a>
    </div>
@endif

<div class="grid-2 review-grid">
    <div class="stack">
        <div class="card">
            <h3>Profile</h3>
            <dl class="kv">
                <dt>County</dt><dd>{{ $community->county }}</dd>
                <dt>Sub-county</dt><dd>{{ $community->sub_county }}</dd>
                <dt>Ward</dt><dd>{{ $community->ward }}</dd>
                <dt>Village</dt><dd>{{ $community->village ?: '—' }}</dd>
                <dt>Population</dt><dd>{{ $community->population ? number_format($community->population) : '—' }}</dd>
                <dt>Households</dt><dd>{{ $community->households ? number_format($community->households) : '—' }}</dd>
                <dt>GPS pin</dt>
                <dd>
                    @if ($community->hasPin())
                        <a href="https://www.google.com/maps?q={{ $community->gps_lat }},{{ $community->gps_lng }}" target="_blank" rel="noopener">
                            {{ number_format($community->gps_lat, 5) }}, {{ number_format($community->gps_lng, 5) }} ↗
                        </a>
                    @else
                        <span class="hint">Not captured — the community cannot be mapped yet.</span>
                    @endif
                </dd>
                @if ($community->summary)<dt>In their words</dt><dd>{{ $community->summary }}</dd>@endif
            </dl>
        </div>

        <div class="card">
            <h3>CTPs mobilising here ({{ $community->ctps->count() }})</h3>
            <p class="hint">An area is worked by as many CTPs as the county puts there — they all mobilise and all
                get credit for the people they bring. One of them is named lead so the record has an owner.</p>
            @forelse ($community->ctps as $ctp)
                <div class="row-line">
                    <a href="{{ dept_link('admin.promoters.show', ['ctp' => $ctp]) }}"><b>{{ $ctp->full_name }}</b></a>
                    <span class="hint">{{ $ctp->ctp_code }} · {{ $ctp->county }}</span>
                    <span class="fee-tag {{ $ctp->pivot->role === 'lead' ? 'paid' : '' }}">{{ Community::ROLES[$ctp->pivot->role] ?? Str::headline($ctp->pivot->role) }}</span>
                    <form method="POST" action="{{ route('admin.communities.ctp.remove', [$community, $ctp]) }}" class="mini-form"
                          data-confirm="Remove {{ $ctp->full_name }} from {{ $community->name }}?" data-title="Remove CTP">
                        @csrf
                        <button class="btn btn-sm btn-ghost" type="submit">Remove</button>
                    </form>
                </div>
            @empty
                <p class="hint">Nobody is on the roster yet.</p>
            @endforelse

            <form method="POST" action="{{ route('admin.communities.ctp', $community) }}" class="mini-form"
                  data-confirm="Add this CTP to the community roster?" data-title="Assign CTP">
                @csrf
                <div class="form-grid-2">
                    <div class="field">
                        <label>CTP <span class="req">*</span></label>
                        <select name="ctp_id" required>
                            <option value="">Choose a CTP</option>
                            @foreach ($candidates as $c)
                                <option value="{{ $c->id }}" @selected(! $lead && $community->created_by_ctp === $c->id)>
                                    {{ $c->ctp_code }} · {{ $c->full_name }} · {{ $c->county }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="field">
                        <label>Role</label>
                        <select name="role">
                            <option value="mobilizer">Mobilizer — brings people from this area</option>
                            <option value="lead">Lead — owns this community record</option>
                        </select>
                    </div>
                </div>
                <button class="btn btn-sm btn-primary" type="submit">Add to roster</button>
            </form>
        </div>

        <div class="card">
            <h3>People brought from this area <span class="tag gray">{{ $brought->count() }}</span></h3>
            <p class="hint">Anyone a rostered CTP mobilised who then joined the programme here — as a trainee CTP or
                a registered E-Fundi. This is what each CTP's 15 / 30 mobilisation credit is measured on.</p>
            @forelse ($brought as $p)
                <div class="row-line">
                    <b>{{ $p['name'] }}</b>
                    <span class="tag">{{ $p['kind'] }}</span>
                    <span class="status {{ $p['status'] }}">{{ $p['label'] }}</span>
                    <span class="hint">brought by {{ $p['mobiliser'] }} · {{ $p['on'] ? $p['on']->format('d M Y') : '—' }}</span>
                </div>
            @empty
                <p class="hint">Nobody registered from this area through a rostered CTP yet.</p>
            @endforelse
        </div>

        <div class="card">
            <h3>Needs flagged here ({{ $needs->count() }})</h3>
            <p class="hint">
                {{ $counts['proposed'] ?? 0 }} awaiting review · {{ $counts['prioritised'] ?? 0 }} prioritised ·
                {{ $counts['converted'] ?? 0 }} converted to a project
            </p>
            @forelse ($needs as $need)
                <div class="need-row">
                    <div class="row-line">
                        <b>{{ $need->title }}</b>
                        <span class="tag">{{ Need::typeLabel($need->type) }}</span>
                        <span class="status {{ $need->status }}">{{ $need->statusLabel() }}</span>
                        <span class="hint">{{ $need->priorityLabel() }} priority · flagged by
                            {{ $need->assessor->full_name ?? 'staff' }}
                            {{ $need->created_at->format('d M Y') }}</span>
                        @if ($need->hasPin())
                            <a class="doc-link" href="https://www.google.com/maps?q={{ $need->gps_lat }},{{ $need->gps_lng }}" target="_blank" rel="noopener">pin ↗</a>
                        @endif
                    </div>
                    @if ($need->detail)<p class="help">{{ $need->detail }}</p>@endif
                    @include('admin.needs.actions', ['need' => $need])
                </div>
            @empty
                <p class="hint">No needs recorded. A CTP on the roster can flag them from <b>/portal &rarr; My communities</b>.</p>
            @endforelse
        </div>

        <div class="card">
            <h3>Beneficiaries ({{ $beneficiaries->count() }})</h3>
            @forelse ($beneficiaries as $b)
                <div class="row-line">
                    <b>{{ $b->name }}</b>
                    <span class="tag">{{ $b->typeLabel() }}</span>
                    <span class="hint">{{ $b->age ? $b->age.' yrs · ' : '' }}{{ $b->gender ? ucfirst($b->gender) : '' }}</span>
                    <span class="hint">benefited {{ $b->benefited_on->format('d M Y') }}</span>
                    @if ($b->project)
                        <a class="hint" href="{{ dept_link('admin.projects.show', ['project' => $b->project_id]) }}">{{ $b->project->reference }}</a>
                    @endif
                    <form method="POST" action="{{ route('admin.beneficiaries.delete', $b) }}" class="mini-form"
                          data-confirm="Remove {{ $b->name }} from the beneficiary count?" data-title="Remove beneficiary">
                        @csrf
                        @method('DELETE')
                        <button class="btn btn-sm btn-ghost" type="submit">Remove</button>
                    </form>
                </div>
            @empty
                <p class="hint">Nobody registered yet.</p>
            @endforelse

            <form method="POST" action="{{ route('admin.communities.beneficiary', $community) }}" class="fee-form"
                  data-confirm="Record this beneficiary?" data-title="Register beneficiary">
                @csrf
                <div class="form-grid-2">
                    <div class="field">
                        <label>Name <span class="req">*</span></label>
                        <input type="text" name="name" required maxlength="150" placeholder="Who received the benefit">
                    </div>
                    <div class="field">
                        <label>Beneficiary type <span class="req">*</span></label>
                        <select name="type" required>
                            @foreach (\App\Models\Beneficiary::TYPES as $key => $label)
                                <option value="{{ $key }}">{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="field">
                        <label>Age</label>
                        <input type="number" name="age" min="1" max="120">
                    </div>
                    <div class="field">
                        <label>Gender</label>
                        <select name="gender">
                            <option value="">Not recorded</option>
                            <option value="female">Female</option>
                            <option value="male">Male</option>
                            <option value="other">Other</option>
                        </select>
                    </div>
                    <div class="field">
                        <label>Date of benefit <span class="req">*</span></label>
                        <input type="date" name="benefited_on" required value="{{ now()->toDateString() }}">
                    </div>
                    <div class="field">
                        <label>Reached by project</label>
                        <select name="project_id">
                            <option value="">Not tied to a project</option>
                            @foreach ($openProjects as $p)
                                <option value="{{ $p->id }}">{{ $p->reference }} · {{ $p->title }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <label class="check-row">
                    <input type="checkbox" name="id_consent" value="1">
                    <span>Consent given to hold their ID number for follow-up programmes</span>
                </label>
                <div class="field">
                    <label>ID number (only with consent)</label>
                    <input type="text" name="id_number" maxlength="20">
                </div>
                <button class="btn btn-lime btn-block" type="submit">Record beneficiary</button>
            </form>
        </div>

        <div class="card">
            <h3>Timeline &amp; investment</h3>
            <p class="hint">KSh {{ number_format($investment, 2) }} committed here across {{ $projects->count() }} project{{ $projects->count() === 1 ? '' : 's' }}.</p>
            @forelse ($projects as $p)
                <div class="row-line">
                    <a href="{{ dept_link('admin.projects.show', ['project' => $p]) }}"><b>{{ $p->title }}</b></a>
                    <span class="hint">{{ $p->reference }} · {{ $p->categoryLabel() }}</span>
                    <span class="status {{ $p->status }}">{{ str_replace('_', ' ', $p->status) }}</span>
                    <span class="hint">{{ $p->budget ? 'KSh '.number_format((float) $p->budget) : 'no budget set' }}</span>
                </div>
            @empty
                <p class="hint">No projects here yet.</p>
            @endforelse
        </div>

        <div class="card">
            <h3>Training here</h3>
            <p class="hint">A cohort is booked into a community as its site — the people trained here are the CTPs on this roster.</p>
            @forelse ($community->cohorts as $c)
                <div class="row-line">
                    <a href="{{ dept_link('admin.cohorts.show', ['cohort' => $c]) }}"><b>{{ $c->courseLabel() }}</b></a>
                    <span class="hint">{{ $c->reference }} · {{ $c->training?->name }}</span>
                    <span class="hint">{{ $c->dateLabel() }}</span>
                    <span class="status {{ $c->status }}">{{ $c->statusLabel() }}</span>
                    <span class="hint">{{ $c->seatsUsed() }} of {{ $c->seats }} seats</span>
                </div>
            @empty
                <p class="hint">No cohort has been booked in this community yet. Prioritised needs are what the office
                    uses to choose one — post it under {!! dept_ref('admin.cohorts.index', 'Training & Certification') !!}.</p>
            @endforelse
        </div>
    </div>

    <div class="review-side">
        <div class="card">
            <h3>Proposal decision</h3>
            @if ($community->status === 'pending_review')
                <p class="hint">Proposed by
                    @if ($community->creator)
                        <a href="{{ dept_link('admin.promoters.show', ['ctp' => $community->created_by_ctp]) }}"><b>{{ $community->creator->full_name }}</b></a>
                        ({{ $community->creator->ctp_code }}) on {{ $community->created_at->format('d M Y') }}
                    @else
                        a removed record
                    @endif
                    — {{ $needs->count() }} need{{ $needs->count() === 1 ? '' : 's' }} already flagged with it.</p>
                <form method="POST" action="{{ route('admin.communities.approve', $community) }}"
                      data-confirm="Confirm {{ $community->name }}? Its COM ID is issued for good and the CTP who proposed it becomes its lead."
                      data-title="Confirm community">
                    @csrf
                    <button class="btn btn-lime btn-block" type="submit">Confirm and issue COM ID</button>
                </form>
                <form method="POST" action="{{ route('admin.communities.reject', $community) }}" class="reject-form"
                      data-confirm="Reject this proposal? The reason is shown to the CTP who sent it." data-title="Reject proposal">
                    @csrf
                    <div class="field">
                        <label>Reason (shown to the CTP) <span class="req">*</span></label>
                        <textarea name="rejection_reason" rows="2" required placeholder="e.g. This ward is already registered as Nyanda cluster — use that community."></textarea>
                    </div>
                    <button class="btn btn-danger btn-block" type="submit">Reject proposal</button>
                </form>
            @elseif ($community->status === 'rejected')
                <p>Reason given to the CTP:<br><em>&ldquo;{{ $community->rejection_reason }}&rdquo;</em></p>
                <form method="POST" action="{{ route('admin.communities.approve', $community) }}"
                      data-confirm="Reverse the rejection and confirm {{ $community->name }}?" data-title="Confirm instead">
                    @csrf
                    <button class="btn btn-outline btn-sm" type="submit">Confirm instead</button>
                </form>
            @else
                <p class="hint">Confirmed by {{ $community->approvedBy->name ?? 'staff' }} on
                    {{ $community->approved_at?->format('d M Y') ?? '—' }} as <b>{{ $community->community_code }}</b>.</p>
                @if ($community->creator)
                    <p class="hint">Proposed by {{ $community->creator->full_name }}{{ $lead ? ' — now its lead CTP' : '' }}.</p>
                @endif
            @endif
        </div>

        <div class="card">
            <h3>Needs by type</h3>
            @forelse ($needs->groupBy('type') as $type => $group)
                <div class="row-line">
                    <span>{{ Need::typeLabel($type) }}</span>
                    <span class="hint">{{ $group->count() }} flagged · {{ $group->whereIn('status', ['prioritised', 'converted'])->count() }} taken forward</span>
                </div>
            @empty
                <p class="hint">Nothing recorded.</p>
            @endforelse
        </div>

    </div>
</div>
@endsection
