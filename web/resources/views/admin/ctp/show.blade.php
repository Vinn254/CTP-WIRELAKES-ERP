@extends('layouts.admin')
@section('title', $ctp->full_name)

@php
    use App\Models\CtpRegistration;
    $paid = $ctp->verifiedReferrals();
    $target = $ctp->nextMilestone() ?? 30;
    $share = round(min(100, $paid / $target * 100));
    $place = collect([$ctp->ward, $ctp->sub_county, $ctp->county])->filter()->join(', ');
@endphp

@section('content')
<p class="back"><a href="{{ route('admin.promoters.index') }}">&larr; CTP profiles</a></p>

<div class="applicant-head card">
    <span class="avatar-lg">{{ $ctp->initials() }}</span>
    <div class="ah-main">
        <h1>{{ $ctp->full_name }} <span class="status {{ $ctp->status }}">{{ $ctp->statusLabel() }}</span></h1>
        <p class="ah-meta">
            <span class="tag">{{ $ctp->ctp_code ?: 'ID not issued yet' }}</span>
            <span>{{ $ctp->trackLabel() }}</span>
            @if ($place)<span>{{ $place }}</span>@endif
            <span>Applied {{ $ctp->created_at->format('d M Y') }}</span>
            @if ($ctp->approved_at)<span>Approved {{ $ctp->approved_at->format('d M Y') }}</span>@endif
        </p>
    </div>
    <div class="ah-contact">
        <a href="tel:{{ $ctp->phone }}">{{ $ctp->phone }}</a>
        <a href="mailto:{{ $ctp->email }}">{{ $ctp->email }}</a>
        <a class="btn btn-sm btn-primary" href="{{ route('admin.promoters.edit', $ctp) }}">Edit profile</a>
    </div>
</div>

@if ($ctp->profileGaps())
    <div class="card gaps">
        <b>Still to capture:</b>
        <span class="chips">@foreach ($ctp->profileGaps() as $g)<span class="chip">{{ $g }}</span>@endforeach</span>
        <a class="btn btn-sm btn-outline" href="{{ route('admin.promoters.edit', $ctp) }}">Fill them in</a>
    </div>
@endif

<div class="grid-2 review-grid">
    <div class="stack">
        <div class="card">
            <h3>Identity</h3>
            <dl class="kv">
                <dt>Full name</dt><dd>{{ $ctp->full_name }}</dd>
                <dt>National ID</dt><dd>{{ $ctp->national_id }}</dd>
                <dt>Date of birth</dt><dd>{{ $ctp->dob->format('d M Y') }} <span class="hint">({{ $ctp->dob->age }} years)</span></dd>
                <dt>Gender</dt><dd>{{ ucfirst($ctp->gender) }}</dd>
                <dt>Phone</dt><dd>{{ $ctp->phone }}</dd>
                <dt>Email</dt><dd>{{ $ctp->email }}</dd>
            </dl>
        </div>

        <div class="card">
            <h3>Location &amp; GPS pin</h3>
            <dl class="kv">
                <dt>County</dt><dd>{{ $ctp->county }}</dd>
                <dt>Sub-county</dt><dd>{{ $ctp->sub_county }}</dd>
                <dt>Ward</dt><dd>{{ $ctp->ward }}</dd>
                <dt>Village</dt><dd>{{ $ctp->village ?: '—' }}</dd>
                <dt>GPS pin</dt>
                <dd>
                    @if ($ctp->gps_lat && $ctp->gps_lng)
                        <a href="https://www.google.com/maps?q={{ $ctp->gps_lat }},{{ $ctp->gps_lng }}" target="_blank" rel="noopener">
                            {{ number_format($ctp->gps_lat, 5) }}, {{ number_format($ctp->gps_lng, 5) }} ↗
                        </a>
                        <div class="hint">Feeds the M&amp;E map (Module 12) and community assignment (Module 3).</div>
                    @else
                        <span class="hint">Not captured — field visits cannot be mapped without it.</span>
                    @endif
                </dd>
            </dl>
        </div>

        <div class="card">
            <h3>Education</h3>
            <dl class="kv">
                <dt>Highest level</dt><dd>{{ CtpRegistration::EDUCATION_LEVELS[$ctp->education_level] ?? $ctp->education_level }}</dd>
                <dt>Year left school</dt><dd>{{ $ctp->year_left_school ?: '—' }}</dd>
                <dt>Certificates held</dt>
                <dd>
                    @if (count((array) $ctp->certificates_held))
                        <span class="chips">@foreach ($ctp->certificates_held as $cert)<span class="chip">{{ $cert }}</span>@endforeach</span>
                    @else
                        <span class="hint">None recorded</span>
                    @endif
                </dd>
                <dt>Track</dt>
                <dd>{{ $ctp->trackLabel() }}
                    <div class="hint">{{ $ctp->track === 'junior'
                        ? 'Practical check + certification check instead of the full cohort.'
                        : 'Full cohort — theory, practical, assessment, certificate.' }}</div>
                </dd>
            </dl>
        </div>

        <div class="card">
            <h3>Mobilisation</h3>
            <div class="milestone">
                <div class="ms-top">
                    <b>{{ $paid }} verified referral{{ $paid === 1 ? '' : 's' }}</b>
                    <span class="hint">toward {{ $target }}</span>
                </div>
                <div class="ms-bar"><i style="width: {{ $share }}%"></i></div>
                <p class="hint">{{ $ctp->discountLabel() }}</p>
            </div>

            @if ($ctp->ctp_code)
                <div class="link-box">
                    <label class="hint">Referral link — the CTP shares this in WhatsApp and community chats</label>
                    <input type="text" readonly value="{{ $ctp->referralLink() }}" onclick="this.select()">
                    <button class="btn btn-sm btn-outline" type="button" onclick="copyText('{{ $ctp->referralLink() }}', this)">Copy link</button>
                </div>
            @endif

            @if ($ctp->referral_code && ! $ctp->referred_by)
                <p class="help"><b>Code “{{ $ctp->referral_code }}”</b> matched no CTP — they may have mistyped it. Link the right mobiliser below.</p>
            @endif

            <h4 class="dl-group">Brought into the programme</h4>
            @forelse ($referrals as $r)
                <div class="row-line">
                    <a href="{{ route('admin.promoters.show', $r) }}"><b>{{ $r->full_name }}</b></a>
                    <span class="hint">{{ $r->ctp_code ?: 'no ID yet' }} · {{ $r->county }}</span>
                    <span class="status {{ $r->status }}">{{ $r->statusLabel() }}</span>
                </div>
            @empty
                <p class="hint">Nobody yet. Only referrals that paid and were approved count.</p>
            @endforelse

            <form method="POST" action="{{ route('admin.promoters.referrer', $ctp) }}" class="mini-form"
                  data-confirm="Move {{ $ctp->full_name }} under the selected mobiliser? Their referral counts change for both CTPs."
                  data-title="Who mobilised them">
                @csrf
                <div class="field">
                    <label>Who mobilised this CTP</label>
                    <select name="referrer_id">
                        <option value="">Nobody — they came on their own</option>
                        @foreach ($candidates as $c)
                            <option value="{{ $c->id }}" @selected($ctp->referred_by === $c->id)>{{ $c->ctp_code }} · {{ $c->full_name }}</option>
                        @endforeach
                    </select>
                </div>
                <button class="btn btn-sm btn-primary" type="submit">Save mobiliser</button>
            </form>
        </div>

        <div class="card">
            <h3>Projects</h3>
            @forelse ($projects as $a)
                <div class="row-line">
                    <a href="{{ route('admin.projects.show', $a->project_id) }}"><b>{{ $a->project->title ?? 'Project deleted' }}</b></a>
                    <span class="hint">{{ $a->project->reference ?? '' }}</span>
                    <span class="status {{ $a->status }}">{{ str_replace('_', ' ', $a->status) }}</span>
                </div>
            @empty
                <p class="hint">No projects applied for or awarded yet.</p>
            @endforelse
        </div>
    </div>

    <div class="review-side">
        <div class="card">
            <h3>Fee &amp; membership</h3>
            <dl class="kv">
                <dt>Fee</dt><dd><span class="fee-tag {{ $ctp->fee_status }}">{{ CtpRegistration::FEE_STATUSES[$ctp->fee_status] ?? $ctp->fee_status }}</span></dd>
                @if ($ctp->fee_status !== 'awaiting_payment')
                    <dt>Amount</dt><dd>KSh {{ number_format((float) $ctp->fee_amount) }}</dd>
                    <dt>Method</dt><dd>{{ $ctp->fee_method ? Str::headline($ctp->fee_method) : '—' }}</dd>
                    @if ($ctp->fee_reference)<dt>Reference</dt><dd>{{ $ctp->fee_reference }}</dd>@endif
                    <dt>Recorded</dt><dd>{{ $ctp->fee_paid_at?->format('d M Y H:i') ?? '—' }}</dd>
                @endif
                <dt>CTP ID</dt><dd>{{ $ctp->ctp_code ?: 'Issued on approval' }}</dd>
            </dl>
            @if ($ctp->fee_status === 'awaiting_payment' && $ctp->status === 'pending_payment')
                <p class="hint">Online checkout (M-Pesa STK / bank) switches on in the finance phase. Until then, record the payment here when the money reaches us.</p>
                <a class="btn btn-sm btn-lime" href="{{ route('admin.registrations.show', ['ctp', $ctp->id]) }}">Record the fee</a>
            @endif
        </div>

        <div class="card">
            <h3>Lifecycle</h3>
            @if ($ctp->approved_at)
                <p class="hint">Approved by {{ $ctp->approvedBy->name ?? 'a previous record' }} on {{ $ctp->approved_at->format('d M Y') }}.</p>
            @endif
            @if ($ctp->status_note)
                <p class="help"><b>Note:</b> {{ $ctp->status_note }}</p>
            @endif
            <div class="lifecycle">
                @foreach ([
                    'suspended' => ['Suspend', 'btn-danger', 'Suspend {name}? Their portal login locks and they stop counting as an active mobiliser.'],
                    'active' => ['Reinstate', 'btn-outline', 'Reinstate {name} as an active CTP and unlock the portal login.'],
                    'graduated' => ['Mark graduated', 'btn-outline', 'Mark {name} as graduated — training and certification are complete.'],
                    'deployed' => ['Mark deployed', 'btn-lime', 'Mark {name} as deployed — they are working in the field.'],
                ] as $to => [$label, $class, $confirm])
                    @if ($ctp->status !== $to)
                        <form method="POST" action="{{ route('admin.promoters.status', $ctp) }}" class="mini-form"
                              data-confirm="{{ str_replace('{name}', $ctp->full_name, $confirm) }}" data-title="{{ $label }}">
                            @csrf
                            <input type="hidden" name="status" value="{{ $to }}">
                            <button class="btn btn-sm {{ $class }}" type="submit">{{ $label }}</button>
                        </form>
                    @endif
                @endforeach
            </div>
            <p class="hint">Rejected applications are handled in Module 1 — Applicants.</p>
        </div>

        <div class="card">
            <h3>Deployment</h3>
            <dl class="kv">
                <dt>Sees projects for</dt><dd><span class="chip">{{ $ctp->poolLabel() }}</span></dd>
                <dt>Service counties</dt>
                <dd>@if (count((array) $ctp->service_counties))<span class="chips">@foreach ($ctp->service_counties as $sc)<span class="chip">{{ $sc }}</span>@endforeach</span>@else<span class="hint">Home county only (not set)</span>@endif</dd>
                <dt>Available for mobilisation</dt><dd>{{ $ctp->available_for_mobilisation ? 'Yes' : 'No' }}</dd>
                <dt>Opted in as Fundi-Trainer</dt><dd>{{ $ctp->trainer_opt_in ? 'Yes' : 'No' }}</dd>
            </dl>
            <a class="btn btn-sm btn-outline" href="{{ route('admin.registrations.show', ['ctp', $ctp->id]) }}">Application record &amp; documents</a>
        </div>

        <div class="card">
            <h3>Documents ({{ count($docs) }})</h3>
            @forelse ($docs as $slot => $doc)
                <a class="doc-card" href="{{ route('admin.registrations.file', ['ctp', $ctp->id, $slot]) }}" target="_blank">
                    <span class="dc-name">{{ $doc['label'] }}</span>
                    <span class="dc-open">Open {{ str_ends_with(strtolower($doc['path']), '.pdf') ? 'PDF' : 'image' }} ↗</span>
                </a>
            @empty
                <p class="help">No documents uploaded.</p>
            @endforelse
        </div>

        <div class="card">
            <h3>Portal account</h3>
            @if ($ctp->user)
                <p>{{ $ctp->user->name }} · {{ $ctp->user->email }}</p>
                <div class="hint">Login is {{ $ctp->user->active ? 'active' : 'locked' }}.</div>
            @elseif ($invite)
                <div class="link-box">
                    <label class="hint">Activation link — send this to the CTP</label>
                    <input type="text" readonly value="{{ route('activate.show', $invite->token) }}" onclick="this.select()">
                    <button class="btn btn-sm btn-outline" type="button" onclick="copyText('{{ route('activate.show', $invite->token) }}', this)">Copy link</button>
                </div>
            @else
                <p class="hint">No account yet — approve the application in Module 1 to issue the ID and activation link.</p>
            @endif
        </div>
    </div>
</div>
@endsection
