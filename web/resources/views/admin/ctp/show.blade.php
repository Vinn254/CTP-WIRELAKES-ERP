@extends('layouts.admin')
@section('title', $ctp->full_name)

@php
    use App\Models\CtpRegistration;
    $broughtCount = $ctp->peopleBrought();
    $target = $ctp->nextMilestone() ?? 30;
    $share = round(min(100, $broughtCount / $target * 100));
    $place = collect([$ctp->ward, $ctp->sub_county, $ctp->county])->filter()->join(', ');
@endphp

@section('content')
<p class="back"><a href="{{ route('admin.promoters.index') }}">&larr; CTP profiles</a></p>

<div class="applicant-head card">
    @include('partials.avatar', ['member' => $ctp, 'src' => route('admin.promoters.file', [$ctp, 'passport'])])
    <div class="ah-main">
        <h1>{{ $ctp->full_name }} <span class="status {{ $ctp->status }}">{{ $ctp->statusLabel() }}</span></h1>
        <p class="ah-meta">
            <span class="tag">{{ $ctp->ctp_code ?: 'ID not issued yet' }}</span>
            <span>{{ $ctp->trackLabel() }}</span>
            @if ($ctp->fundi_tier)
                <span class="tag gold">Moved on as a {{ $ctp->gradeLabel() }}</span>
            @endif
            @if ($place)<span>{{ $place }}</span>@endif
            <span>Applied {{ $ctp->created_at->format('d M Y') }}</span>
            @if ($ctp->source === 'office')
                <span>Added from the office by {{ $ctp->addedBy->name ?? 'a staff member' }}</span>
            @endif
            @if ($ctp->sponsor)
                <span class="tag gold">Sponsored by {{ $ctp->sponsor->name }}</span>
            @endif
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
                    <b>{{ $broughtCount }} people brought</b>
                    <span class="hint">toward {{ $target }}</span>
                </div>
                <div class="ms-bar"><i style="width: {{ $share }}%"></i></div>
                <p class="hint">{{ $ctp->discountLabel() }}</p>
            </div>

            @if ($ctp->ctp_code)
                <div class="link-box">
                    <label class="hint" for="referral-link">Referral link — the CTP shares this in WhatsApp and community chats</label>
                    <input type="text" readonly id="referral-link" value="{{ $ctp->referralLink() }}" onclick="this.select()">
                    <button class="btn btn-sm btn-outline" type="button" onclick="copyText('{{ $ctp->referralLink() }}', this)">Copy link</button>
                </div>
            @endif

            @if ($ctp->referral_code && ! $ctp->referred_by)
                <p class="help"><b>Code “{{ $ctp->referral_code }}”</b> matched no CTP — they may have mistyped it. Link the right mobiliser below.</p>
            @endif

            <h4 class="dl-group">People brought into the programme</h4>
            <p class="hint">A trainee CTP or a registered E-Fundi who joined through this CTP — both count toward the
                same 15 / 30 term credit.</p>
            @forelse ($brought as $p)
                <div class="row-line">
                    <b>{{ $p['name'] }}</b>
                    <span class="tag">{{ $p['kind'] }}</span>
                    <span class="hint">{{ $p['code'] }}{{ $p['place'] ? ' · '.$p['place'] : '' }}</span>
                    <span class="status {{ $p['status'] }}">{{ $p['label'] }}</span>
                </div>
            @empty
                <p class="hint">Nobody yet. Only people who paid and were approved count.</p>
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
                    <a href="{{ dept_link('admin.projects.show', ['project' => $a->project_id]) }}"><b>{{ $a->project->title ?? 'Project deleted' }}</b></a>
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
            <h3>Payment &amp; membership</h3>
            <dl class="kv">
                <dt>Payment</dt><dd><span class="fee-tag {{ $ctp->fee_status }}">{{ CtpRegistration::FEE_STATUSES[$ctp->fee_status] ?? $ctp->fee_status }}</span></dd>
                @if ($ctp->fee_status === 'sponsored')
                    <dt>Covered by</dt><dd>{{ $ctp->sponsor->name ?? 'a partner' }}</dd>
                @endif
                @if ($ctp->fee_status !== 'awaiting_payment')
                    <dt>Amount</dt><dd>{{ $ctp->fee_amount ? 'KSh '.number_format((float) $ctp->fee_amount) : '—' }}</dd>
                    <dt>Method</dt><dd>{{ $ctp->fee_method ? Str::headline($ctp->fee_method) : '—' }}</dd>
                    @if ($ctp->fee_reference)<dt>Reference</dt><dd>{{ $ctp->fee_reference }}</dd>@endif
                    <dt>Recorded</dt><dd>{{ $ctp->fee_paid_at?->format('d M Y H:i') ?? '—' }}</dd>
                @endif
                <dt>CTP ID</dt><dd>{{ $ctp->ctp_code ?: 'Issued on approval' }}</dd>
            </dl>
            @if ($ctp->fee_status === 'awaiting_payment' && ! in_array($ctp->status, ['rejected', 'suspended'], true))
                <p class="hint">{{ \App\Support\Finance::paybillLine() }} Nothing waits on a payment — record the money
                    here only when it reaches us so it lands on the ledger.</p>
                <form method="POST" action="{{ route('admin.promoters.fee', $ctp) }}" class="fee-form"
                      data-confirm="Record this payment for {{ $ctp->full_name }}?" data-title="Payment">
                    @csrf
                    <div class="form-grid-2">
                        <div class="field">
                            <label>Status <span class="req">*</span></label>
                            <select name="fee_status" required>
                                <option value="paid">Paid</option>
                                <option value="waived">Waived by the office</option>
                            </select>
                        </div>
                        <div class="field">
                            <label>Paid through</label>
                            <select name="fee_method">
                                <option value="mpesa">M-Pesa</option>
                                <option value="bank">Bank transfer</option>
                                <option value="cash">Cash at office</option>
                                <option value="other">Other</option>
                            </select>
                        </div>
                        <div class="field">
                            <label>Amount (KSh)</label>
                            <input type="number" step="0.01" min="0" name="fee_amount" placeholder="e.g. 1000">
                        </div>
                        <div class="field">
                            <label>M-Pesa / transaction code</label>
                            <input type="text" name="fee_reference" maxlength="60" placeholder="e.g. SLK4H29PQ1">
                        </div>
                    </div>
                    <button class="btn btn-outline btn-block" type="submit">Record payment</button>
                </form>
            @endif
        </div>

        <div class="card">
            <h3>Sponsorship</h3>
            @if ($ctp->fee_status === 'paid')
                <p class="hint">KSh {{ number_format((float) $ctp->fee_amount) }} was received{{ $ctp->fee_reference ? ' ('.$ctp->fee_reference.')' : '' }}
                    on {{ $ctp->fee_paid_at?->format('d M Y') ?? 'record' }} — money in is never rewritten. A sponsor can still be
                    recorded on the profile without changing that.</p>
            @endif
            <form method="POST" action="{{ route('admin.promoters.sponsor', $ctp) }}"
                  data-confirm="Set the sponsoring partner for {{ $ctp->full_name }}?" data-title="Sponsorship">
                @csrf
                <div class="field">
                    <label>Sponsoring partner</label>
                    <select name="sponsor_id">
                        <option value="">No sponsor</option>
                        @foreach ($partners as $p)
                            <option value="{{ $p->id }}" @selected($ctp->sponsor_id === $p->id)>{{ $p->name }}</option>
                        @endforeach
                    </select>
                    <div class="help">Partners are kept in <b>Website &amp; Membership → Partners</b>; only the sponsorship link lives here.</div>
                </div>
                <button class="btn btn-outline btn-sm" type="submit">Save sponsorship</button>
            </form>
        </div>

        <div class="card">
            <h3>Application decision</h3>
            @if (in_array($ctp->status, ['pending_review', 'pending_payment'], true))
                <form method="POST" action="{{ route('admin.promoters.approve', $ctp) }}"
                      data-confirm="Approve {{ $ctp->full_name }}? Their CTP ID is issued for good and an activation link is created."
                      data-title="Approve application">
                    @csrf
                    <button class="btn btn-lime btn-block" type="submit">Approve and issue CTP ID</button>
                </form>
                <form method="POST" action="{{ route('admin.promoters.reject', $ctp) }}" class="reject-form"
                      data-confirm="Reject this application? The reason is sent to the applicant." data-title="Reject application">
                    @csrf
                    <div class="field">
                        <label>Rejection reason (sent to applicant) <span class="req">*</span></label>
                        <textarea name="rejection_reason" rows="2" required placeholder="e.g. National ID scan is unreadable — please re-upload."></textarea>
                    </div>
                    <button class="btn btn-danger btn-block" type="submit">Reject application</button>
                </form>
            @elseif ($ctp->status === 'rejected')
                <p>Reason sent to the applicant:<br><em>&ldquo;{{ $ctp->rejection_reason }}&rdquo;</em></p>
                <form method="POST" action="{{ route('admin.promoters.approve', $ctp) }}"
                      data-confirm="Reverse the rejection and approve {{ $ctp->full_name }}?" data-title="Approve instead">
                    @csrf
                    <button class="btn btn-outline btn-sm" type="submit">Approve instead</button>
                </form>
            @else
                <p class="hint">Approved on {{ $ctp->approved_at?->format('d M Y') ?? 'record approval' }} as
                    <b>{{ $ctp->ctp_code }}</b>. Rejections are only open while the application is unpaid or waiting for review.</p>
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
            <p class="hint">Suspension locks their portal login at once; graduation and deployment decide which
                projects they can see.</p>
        </div>

        <div class="card">
            <h3>Deployment</h3>
            <dl class="kv">
                <dt>Sees projects for</dt><dd><span class="chip">{{ $ctp->gradeLabel() }}</span></dd>
                <dt>Service counties</dt>
                <dd>@if (count((array) $ctp->service_counties))<span class="chips">@foreach ($ctp->service_counties as $sc)<span class="chip">{{ $sc }}</span>@endforeach</span>@else<span class="hint">Home county only (not set)</span>@endif</dd>
                <dt>Available for mobilisation</dt><dd>{{ $ctp->available_for_mobilisation ? 'Yes' : 'No' }}</dd>
                <dt>Opted in as Fundi-Trainer</dt><dd>{{ $ctp->trainer_opt_in ? 'Yes' : 'No' }}</dd>
            </dl>
            <form method="POST" action="{{ route('admin.promoters.tier', $ctp) }}" class="mini-form"
                  data-confirm="Change which projects {{ $ctp->full_name }} can see?" data-title="Project level">
                @csrf
                <div class="field">
                    <label>Sees projects for</label>
                    <select name="fundi_tier">
                        <option value="">CTP attachment only</option>
                        @foreach (\App\Models\Project::CTP_POOL_TIERS as $key => $desc)
                            <option value="{{ $key }}" @selected($ctp->fundi_tier === $key)>{{ $desc }}</option>
                        @endforeach
                    </select>
                </div>
                <button class="btn btn-sm btn-primary" type="submit">Save project level</button>
            </form>
        </div>

        <div class="card">
            <h3>Documents ({{ count($docs) }})</h3>
            <form method="POST" action="{{ route('admin.promoters.photo', $ctp) }}" enctype="multipart/form-data" class="photo-form">
                @csrf
                <div class="field">
                    <label>Profile photo</label>
                    <input type="file" name="passport_photo" accept=".jpg,.jpeg,.png" required>
                </div>
                <button class="btn btn-sm btn-lime" type="submit">{{ $ctp->photoPath() ? 'Replace photo' : 'Capture photo' }}</button>
                <p class="help">The picture shown on this profile and in their portal. Take it at the counter when the
                    application came in without one.</p>
            </form>
            @forelse ($docs as $slot => $doc)
                <a class="doc-card" href="{{ route('admin.promoters.file', [$ctp, $slot]) }}" target="_blank"
                    @if (! str_ends_with(strtolower($doc['path']), '.pdf')) data-zoom @endif>
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
                <div class="hint">Login is {{ $ctp->user->active ? 'active' : 'locked' }} — they see only their own trainee interface at <b>/portal</b>.</div>
            @elseif ($invite)
                <div class="link-box">
                    <label class="hint" for="ctp-activation-link">Activation link — send this to the CTP</label>
                    <input type="text" readonly id="ctp-activation-link" value="{{ route('activate.show', $invite->token) }}" onclick="this.select()">
                    <button class="btn btn-sm btn-outline" type="button" onclick="copyText('{{ route('activate.show', $invite->token) }}', this)">Copy link</button>
                </div>
                @if ($invite->status === 'pending')
                    <form method="POST" action="{{ route('admin.promoters.reissue', $ctp) }}" class="mini-form"
                          data-confirm="Issue a fresh activation link? The old one stops working." data-title="New activation link">
                        @csrf
                        <button class="btn btn-sm btn-ghost" type="submit">New link</button>
                    </form>
                @endif
            @else
                <p class="hint">No account yet — approve this CTP above to issue the ID and an activation link.</p>
            @endif
        </div>
    </div>
</div>
@endsection
