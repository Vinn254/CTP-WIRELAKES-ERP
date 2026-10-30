@extends('layouts.admin')
@section('title', $fundi->full_name)

@php
    use App\Models\FundiRegistration;

    $initials = collect(explode(' ', trim($fundi->full_name)))->filter()->take(2)
        ->map(fn ($w) => mb_strtoupper(mb_substr($w, 0, 1)))->join('');
    $place = collect([$fundi->ward, $fundi->sub_county, $fundi->county])->filter()->join(', ');
    $isBlocked = in_array($fundi->status, FundiRegistration::BLOCKING, true);
    $openApplication = in_array($fundi->status, ['pending_payment', 'pending_review'], true);
    $held = collect($fundi->specialisations ?? []);
@endphp

@section('content')
<p class="back"><a href="{{ route('admin.fundis.index') }}">&larr; E-Fundi register</a></p>

<div class="applicant-head card">
    @include('partials.avatar', ['member' => $fundi, 'src' => route('admin.fundis.file', [$fundi, 'passport_photo'])])
    <div class="ah-main">
        <h1>{{ $fundi->full_name }}
            <span class="status {{ $fundi->status === 'active' ? 'active' : ($openApplication ? 'pending_review' : 'closed') }}">{{ $fundi->statusLabel() }}</span>
        </h1>
        <p class="ah-meta">
            <span class="tag">{{ $fundi->ef_code ?: 'ID not issued yet' }}</span>
            <span>{{ FundiRegistration::tierLabel($fundi->tier) }}</span>
            @if ($place)<span>{{ $place }}</span>@endif
            <span class="status {{ $fundi->isMatchable() ? 'active' : 'pending_review' }}">{{ $fundi->availabilityLabel() }}</span>
            <span>Applied {{ $fundi->created_at->format('d M Y') }}</span>
            @if ($fundi->mobiliser)<span>Brought by CTP {{ $fundi->mobiliser->ctp_code }} · {{ $fundi->mobiliser->full_name }}</span>@endif
        </p>
    </div>
    <div class="ah-contact">
        <a href="tel:{{ $fundi->phone }}">{{ $fundi->phone }}</a>
        <a href="mailto:{{ $fundi->email }}">{{ $fundi->email }}</a>
    </div>
</div>

@if ($fundi->rejection_reason)
    <div class="card gaps">
        <b>{{ $fundi->statusLabel() }} note on file:</b> {{ $fundi->rejection_reason }}
        @if ($isBlocked)
            <span class="hint">— the record is kept. Reopen it with the button under <i>Standing</i> when the issue is settled.</span>
        @endif
    </div>
@endif

<div class="grid-2 review-grid">
    <div class="stack">
        <div class="card">
            <h3>Identity</h3>
            <dl class="kv">
                <dt>Full name</dt><dd>{{ $fundi->full_name }}</dd>
                <dt>National ID</dt><dd>{{ $fundi->national_id }}</dd>
                <dt>Date of birth</dt><dd>{{ $fundi->dob->format('d M Y') }} <span class="hint">({{ $fundi->dob->age }} years)</span></dd>
                <dt>Gender</dt><dd>{{ ucfirst($fundi->gender) }}</dd>
                <dt>Phone</dt><dd>{{ $fundi->phone }}</dd>
                <dt>Email</dt><dd>{{ $fundi->email }}</dd>
                <dt>Application reference</dt><dd>{{ $fundi->reference }}</dd>
            </dl>
        </div>

        <div class="card">
            <h3>Grade</h3>
            <p>{{ FundiRegistration::TIERS[$fundi->tier] ?? $fundi->tier }}</p>
            <dl class="kv">
                <dt>May train CTPs</dt>
                <dd>{{ in_array($fundi->tier, \App\Models\Trainer::TEACHING_TIERS, true) ? 'Yes — only a senior fundi is approved at a site' : 'No — only a senior fundi trains' }}</dd>
                <dt>Sees projects for</dt>
                <dd>
                    <span class="chips">@foreach (\App\Models\Project::CATEGORIES as $key => $rule)
                        @if (in_array($fundi->tier, $rule['tiers'] ?? [], true))<span class="chip">{{ $rule['label'] ?? $key }}</span>@endif
                    @endforeach</span>
                    <div class="hint">Set by the grade — a fundi applies to work, a CTP trainee only watches and takes attachments.</div>
                </dd>
            </dl>
            <form method="POST" action="{{ route('admin.fundis.tier', $fundi) }}" class="mini-form"
                  data-confirm="Move {{ $fundi->full_name }} to the selected grade? It changes the projects they can apply for and whether they may train."
                  data-title="Grade">
                @csrf
                <div class="field">
                    <label>Grade <span class="req">*</span></label>
                    <select name="tier" required>
                        @foreach ($tiers as $key => $desc)
                            <option value="{{ $key }}" @selected($fundi->tier === $key)>{{ $desc }}</option>
                        @endforeach
                    </select>
                </div>
                <button class="btn btn-sm btn-primary" type="submit">Save grade</button>
            </form>
        </div>

        <div class="card">
            <div class="section-head">
                <h3>Trade skills</h3>
                <span class="tag">{{ $held->count() }} held</span>
            </div>
            @if ($held->isEmpty())
                <p class="hint">No skill recorded yet — matching cannot place a fundi without at least one.</p>
            @else
                <p><span class="chips">@foreach ($held as $s)<span class="chip">{{ $s }}</span>@endforeach</span></p>
            @endif
            <details class="need-form">
                <summary>Update skills and counties covered</summary>
                <form method="POST" action="{{ route('admin.fundis.skills', $fundi) }}" class="fee-form">
                    @csrf
                    <div class="field">
                        <label>What they can do <span class="req">*</span></label>
                        <div class="chips" style="display:flex;flex-wrap:wrap;gap:4px 16px;margin:8px 0;">
                            @foreach ($specialisations as $s)
                                <label class="check-row" style="margin:0 14px 0 0;">
                                    <input type="checkbox" name="specialisations[]" value="{{ $s }}" @checked($held->contains($s))> {{ $s }}</label>
                            @endforeach
                        </div>
                        <div class="help">Only the trades the programme recognises are on this list — the same list the public E-Fundi form uses.</div>
                    </div>
                    <div class="field" style="margin-top:12px;">
                        <label>Counties they will travel to work in</label>
                        <select name="service_counties[]" multiple size="6">
                            @foreach ($counties as $c)
                                <option value="{{ $c }}" @selected(collect($fundi->service_counties ?? [])->contains($c))>{{ $c }}</option>
                            @endforeach
                        </select>
                        <div class="help">Hold Ctrl (or Cmd) to pick several. Leave empty for their home county only.</div>
                    </div>
                    <button class="btn btn-lime btn-block" type="submit" style="margin-top:12px;">Save skills</button>
                </form>
            </details>
        </div>

        <div class="card">
            <div class="section-head"><h3>Business &amp; where they work</h3></div>
            <dl class="kv">
                <dt>Business name</dt><dd>{{ $fundi->business_name ?: 'Trades as themselves' }}</dd>
                <dt>Registration number</dt><dd>{{ $fundi->business_reg_no ?: '—' }}</dd>
                <dt>Years trading</dt><dd>{{ $fundi->years_in_business !== null ? $fundi->years_in_business.' years' : '—' }}</dd>
                <dt>County</dt><dd>{{ $fundi->county ?: '—' }}</dd>
                <dt>Sub-county</dt><dd>{{ $fundi->sub_county ?: '—' }}</dd>
                <dt>Ward</dt><dd>{{ $fundi->ward ?: '—' }}</dd>
                <dt>Referee</dt><dd>@if ($fundi->referee_name){{ $fundi->referee_name }} · {{ $fundi->referee_phone }} <span class="hint">({{ $fundi->referee_role }})</span>@else<span class="hint">None given</span>@endif</dd>
            </dl>
            <details class="need-form">
                <summary>Correct the business and location</summary>
                <form method="POST" action="{{ route('admin.fundis.place', $fundi) }}" class="fee-form">
                    @csrf
                    <div class="form-grid-2">
                        <div class="field"><label>County <span class="req">*</span></label>
                            <input type="text" name="county" list="fundi-counties" required value="{{ $fundi->county }}"></div>
                        <div class="field"><label>Sub-county</label>
                            <input type="text" name="sub_county" value="{{ $fundi->sub_county }}"></div>
                        <div class="field"><label>Ward</label>
                            <input type="text" name="ward" value="{{ $fundi->ward }}"></div>
                        <div class="field"><label>Business name</label>
                            <input type="text" name="business_name" maxlength="160" value="{{ $fundi->business_name }}"></div>
                        <div class="field"><label>Business registration no.</label>
                            <input type="text" name="business_reg_no" maxlength="60" value="{{ $fundi->business_reg_no }}"></div>
                        <div class="field"><label>Years trading</label>
                            <input type="number" name="years_in_business" min="0" max="80" value="{{ $fundi->years_in_business }}"></div>
                    </div>
                    <datalist id="fundi-counties">@foreach ($counties as $c)<option value="{{ $c }}"></option>@endforeach</datalist>
                    <button class="btn btn-lime btn-block" type="submit" style="margin-top:12px;">Save record</button>
                </form>
            </details>
        </div>

        <div class="card">
            <div class="section-head">
                <h3>Sites they train at</h3>
                <span class="tag">{{ $sites->count() }}</span>
            </div>
            <p class="hint">Each row is the teaching role on the Training desk, approved <b>site by site</b>. The office decides them under
                <b>Training &amp; Certification &rsaquo; Trainers</b> — this screen only shows what the fundi is carrying.</p>
            @forelse ($sites as $s)
                <div class="row-line">
                    <b>{{ $s->venue->name ?? 'A site' }}</b>
                    <span class="hint">{{ $s->venue->county ?? collect([$s->ward, $s->sub_county, $s->county])->filter()->join(' · ') }}</span>
                    <span class="status {{ $s->status === 'approved' ? 'active' : ($s->status === 'applied' ? 'pending_review' : 'closed') }}">{{ $s->statusLabel() }}</span>
                    <span class="hint">{{ $s->teachingLabel() ?: 'no trainings listed' }}</span>
                    <span class="hint">{{ $s->weekly_hours ? $s->weekly_hours.' hours a week' : 'hours not set' }}</span>
                </div>
            @empty
                <p class="hint">Not registered to train anywhere. A senior fundi applies from their own portal,
                    or be put forward by the office.</p>
            @endforelse
        </div>

        <div class="card">
            <h3>Projects they have applied for</h3>
            @forelse ($applications as $a)
                <div class="row-line">
                    <a href="{{ dept_link('admin.projects.show', ['project' => $a->project_id]) }}"><b>{{ $a->project->title ?? 'Project removed' }}</b></a>
                    <span class="hint">{{ $a->project->reference ?? '' }}</span>
                    <span class="tag">{{ $a->project->categoryLabel() ?? '' }}</span>
                    <span class="status {{ $a->status === 'approved' ? 'active' : ($a->status === 'pending_review' ? 'pending_review' : 'closed') }}">{{ str_replace('_', ' ', $a->status) }}</span>
                </div>
            @empty
                <p class="hint">No applications yet. An E-Fundi is the senior worker: they put themselves forward for open
                    projects from their own portal.</p>
            @endforelse
        </div>
    </div>

    <div class="review-side">
        <div class="card">
            <h3>Availability</h3>
            <dl class="kv">
                <dt>Stated</dt><dd><span class="status {{ $fundi->isMatchable() ? 'active' : 'pending_review' }}">{{ $fundi->availabilityLabel() }}</span></dd>
                <dt>Their note</dt><dd>{{ $fundi->availability_note ?: '—' }}</dd>
                <dt>In the work pool</dt><dd>{{ $fundi->isMatchable() ? 'Yes — can be matched or assigned' : 'No' }}</dd>
            </dl>
            <p class="help">The fundi keeps this themselves on their own portal. Correct it here when they stop answering or
                finish a job without telling the office.</p>
            <form method="POST" action="{{ route('admin.fundis.availability', $fundi) }}" class="mini-form"
                  data-confirm="Set {{ $fundi->full_name }} as the selected availability?" data-title="Availability">
                @csrf
                <div class="field">
                    <label>Availability <span class="req">*</span></label>
                    <select name="availability" required>
                        @foreach ($availabilities as $key => $label)
                            <option value="{{ $key }}" @selected($fundi->availability === $key)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="field" style="margin-top:10px;">
                    <label>Note</label>
                    <input type="text" name="availability_note" maxlength="300" value="{{ $fundi->availability_note }}"
                           placeholder="e.g. On a Kisumu job until 30 Nov">
                </div>
                <button class="btn btn-sm btn-primary" type="submit" style="margin-top:10px;">Save availability</button>
            </form>
        </div>

        <div class="card">
            <h3>Payment &amp; membership</h3>
            <dl class="kv">
                <dt>Payment</dt><dd><span class="fee-tag {{ $fundi->fee_status }}">{{ FundiRegistration::FEE_STATUSES[$fundi->fee_status] ?? $fundi->fee_status }}</span></dd>
                @if ($fundi->fee_status !== 'awaiting_payment')
                    <dt>Amount</dt><dd>{{ $fundi->fee_amount ? 'KSh '.number_format((float) $fundi->fee_amount) : '—' }}</dd>
                    <dt>Method</dt><dd>{{ $fundi->fee_method ? Str::headline($fundi->fee_method) : '—' }}</dd>
                    @if ($fundi->fee_reference)<dt>Reference</dt><dd>{{ $fundi->fee_reference }}</dd>@endif
                    <dt>Recorded</dt><dd>{{ $fundi->fee_paid_at?->format('d M Y H:i') ?? '—' }}</dd>
                @endif
                <dt>E-Fundi ID</dt><dd>{{ $fundi->ef_code ?: 'Issued on approval' }}</dd>
            </dl>
            @if ($fundi->fee_status === 'awaiting_payment' && $openApplication)
                <p class="hint">{{ \App\Support\Finance::paybillLine() }} Nothing waits on a payment — record the money
                    here only when it reaches us so it lands on the ledger.</p>
                <form method="POST" action="{{ route('admin.fundis.fee', $fundi) }}" class="fee-form"
                      data-confirm="Record this payment for {{ $fundi->full_name }}?" data-title="Payment">
                    @csrf
                    <div class="form-grid-2">
                        <div class="field"><label>Status <span class="req">*</span></label>
                            <select name="fee_status" required>
                                <option value="paid">Paid</option>
                                <option value="waived">Waived by the office</option>
                            </select></div>
                        <div class="field"><label>Paid through</label>
                            <select name="fee_method">
                                <option value="mpesa">M-Pesa</option>
                                <option value="bank">Bank transfer</option>
                                <option value="cash">Cash at office</option>
                                <option value="other">Other</option>
                            </select></div>
                        <div class="field"><label>Amount (KSh)</label>
                            <input type="number" step="0.01" min="0" name="fee_amount" placeholder="e.g. 1000"></div>
                        <div class="field"><label>M-Pesa / transaction code</label>
                            <input type="text" name="fee_reference" maxlength="60" placeholder="e.g. SLK4H29PQ1"></div>
                    </div>
                    <button class="btn btn-outline btn-block" type="submit" style="margin-top:12px;">Record payment</button>
                </form>
            @endif
        </div>

        <div class="card">
            <h3>Application decision</h3>
            @if ($fundi->status === 'pending_payment')
                <p class="hint">No payment gates this application — approve it below whenever the documents are in order.</p>
            @elseif ($fundi->status === 'pending_review')
                <form method="POST" action="{{ route('admin.fundis.approve', $fundi) }}"
                      data-confirm="Approve {{ $fundi->full_name }}? Their EF ID is issued for good and the activation link is emailed to {{ $fundi->email }}."
                      data-title="Approve application">
                    @csrf
                    <button class="btn btn-lime btn-block" type="submit">Approve and issue EF ID</button>
                </form>
                <form method="POST" action="{{ route('admin.fundis.reject', $fundi) }}" class="reject-form"
                      data-confirm="Reject this application? The reason is sent to the applicant." data-title="Reject application">
                    @csrf
                    <div class="field">
                        <label>Rejection reason (sent to applicant) <span class="req">*</span></label>
                        <textarea name="rejection_reason" rows="2" required placeholder="e.g. Trade certificates could not be verified."></textarea>
                    </div>
                    <button class="btn btn-danger btn-block" type="submit">Reject application</button>
                </form>
            @elseif ($fundi->status === 'rejected')
                <p class="hint">Reason sent: <em>&ldquo;{{ $fundi->rejection_reason }}&rdquo;</em></p>
                @if ($isExecutive)
                    <form method="POST" action="{{ route('admin.fundis.reinstate', $fundi) }}" class="mini-form"
                          data-confirm="Reverse the rejection and make {{ $fundi->full_name }} an active E-Fundi?" data-title="Approve instead">
                        @csrf
                        <button class="btn btn-sm btn-outline" type="submit">Approve instead</button>
                    </form>
                @else
                    <p class="hint">Only a programme executive can reverse a rejection.</p>
                @endif
            @else
                <p class="hint">Registered as <b>{{ $fundi->ef_code }}</b>. Rejections are only open while the application is
                    unpaid or waiting for review; a standing problem afterwards is handled under <i>Standing</i>.</p>
            @endif
        </div>

        <div class="card">
            <h3>Standing</h3>
            @if (! $isExecutive)
                <p class="hint">Staff can prepare the record; <b>only a programme executive</b> suspends, blacklists or
                    reinstates an E-Fundi.</p>
            @elseif ($isBlocked)
                <form method="POST" action="{{ route('admin.fundis.reinstate', $fundi) }}" class="mini-form"
                      data-confirm="Reinstate {{ $fundi->full_name }} as an active E-Fundi and unlock their login?" data-title="Reinstate">
                    @csrf
                    <button class="btn btn-sm btn-lime" type="submit">Reinstate as active</button>
                </form>
                <p class="help">The reason and every past record stay on this row — a fundi is reopened, never deleted or duplicated.</p>
            @else
                <div class="lifecycle">
                    @foreach ([
                        'suspended' => ['Suspend', 'btn-danger', 'Suspend {name}? Their login locks and they drop out of the work pool until you reinstate them.'],
                        'blacklisted' => ['Blacklist', 'btn-danger', 'Blacklist {name}? This is for a fundi who must not be matched again. The record stays on file.'],
                    ] as $to => [$label, $class, $confirm])
                        <form method="POST" action="{{ route('admin.fundis.block', $fundi) }}" class="fee-form" style="margin-top:10px;"
                              data-confirm="{{ str_replace('{name}', $fundi->full_name, $confirm) }}" data-title="{{ $label }}">
                            @csrf
                            <input type="hidden" name="status" value="{{ $to }}">
                            <div class="field">
                                <label>Reason (kept on the record) <span class="req">*</span></label>
                                <textarea name="note" rows="2" maxlength="1000" required placeholder="e.g. Did not turn up for two assigned jobs."></textarea>
                            </div>
                            <button class="btn btn-sm {{ $class }} btn-block" type="submit" style="margin-top:8px;">{{ $label }}</button>
                        </form>
                    @endforeach
                </div>
                <p class="hint">Suspension and blacklist both lock the portal login and take the fundi out of matching.
                    Nothing is deleted — reopen the same row when the issue is settled.</p>
            @endif
        </div>

        <div class="card">
            <h3>Documents ({{ count($docs) }})</h3>
            <form method="POST" action="{{ route('admin.fundis.photo', $fundi) }}" enctype="multipart/form-data" class="photo-form">
                @csrf
                <div class="field">
                    <label>Profile photo</label>
                    <input type="file" name="passport_photo" accept=".jpg,.jpeg,.png" required>
                </div>
                <button class="btn btn-sm btn-lime" type="submit">{{ $fundi->photoPath() ? 'Replace photo' : 'Capture photo' }}</button>
                <p class="help">The picture shown on this record and in their portal. Take it at the counter when the
                    application came in without one.</p>
            </form>
            @forelse ($docs as $slot => $doc)
                @include('partials.doc-card', ['label' => $doc['label'], 'path' => $doc['path'],
                    'url' => route('admin.fundis.file', [$fundi, $slot])])
            @empty
                <p class="help">No documents uploaded.</p>
            @endforelse
        </div>

        <div class="card">
            <h3>Portal account</h3>
            @if ($fundi->user)
                <p>{{ $fundi->user->name }} · {{ $fundi->user->email }}</p>
                <div class="hint">Login is {{ $fundi->user->active ? 'active' : 'locked' }} — they see their own E-Fundi
                    interface at <b>/portal</b>, including their projects and the sites they train at.</div>
            @elseif ($invite)
                <div class="link-box">
                    <label class="hint" for="fundi-activation-link">Activation link — emailed to the fundi; copy it again if they cannot find it</label>
                    <input type="text" readonly id="fundi-activation-link" value="{{ route('activate.show', $invite->token) }}" onclick="this.select()">
                    <button class="btn btn-sm btn-outline" type="button" onclick="copyText('{{ route('activate.show', $invite->token) }}', this)">Copy link</button>
                </div>
                @if ($invite->status === 'pending')
                    <form method="POST" action="{{ route('admin.fundis.reissue', $fundi) }}" class="mini-form"
                          data-confirm="Issue a fresh activation link? The old one stops working and the new one is emailed to {{ $fundi->email }}." data-title="New activation link">
                        @csrf
                        <button class="btn btn-sm btn-ghost" type="submit">New link</button>
                    </form>
                @endif
            @else
                <p class="hint">No account yet — approve this fundi above to issue the EF ID and an activation link.</p>
            @endif
        </div>
    </div>
</div>
@endsection
