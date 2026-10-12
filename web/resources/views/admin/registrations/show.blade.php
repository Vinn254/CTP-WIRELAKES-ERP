@extends('layouts.admin')
@section('title', 'Application '.$record->reference)

@php
    $labels = [
        'reference' => 'Reference', 'full_name' => 'Full name', 'contact_name' => 'Contact name',
        'organisation' => 'Organisation', 'national_id' => 'National ID', 'phone' => 'Phone',
        'email' => 'Email', 'dob' => 'Date of birth', 'gender' => 'Gender',
        'county' => 'County', 'sub_county' => 'Sub-county', 'ward' => 'Ward', 'village' => 'Village',
        'education_level' => 'Education level', 'track' => 'Track', 'referral_code' => 'Referral code',
        'fundi_tier' => 'Fundi pool',
        'tier' => 'Fundi tier', 'business_name' => 'Business name', 'business_reg_no' => 'Business reg no.',
        'years_in_business' => 'Years in business', 'specialisations' => 'Specialisations',
        'service_counties' => 'Service counties', 'referee_name' => 'Referee name',
        'referee_phone' => 'Referee phone', 'referee_role' => 'Referee role', 'org_type' => 'Organisation type',
        'ctp_code' => 'CTP ID', 'ef_code' => 'Fundi ID', 'hirer_code' => 'Hirer ID',
        'status' => 'Status', 'rejection_reason' => 'Rejection reason',
    ];
    $groups = [
        'Personal details' => ['full_name', 'contact_name', 'national_id', 'dob', 'gender', 'email', 'phone'],
        'Location' => ['county', 'sub_county', 'ward', 'village'],
        'Programme' => ['track', 'fundi_tier', 'tier', 'education_level', 'business_name', 'business_reg_no', 'years_in_business',
                        'specialisations', 'service_counties', 'referral_code', 'org_type', 'organisation'],
        'Referee' => ['referee_name', 'referee_phone', 'referee_role'],
        'Membership' => ['ctp_code', 'ef_code', 'hirer_code', 'status', 'rejection_reason'],
    ];
    $hidden = ['id', 'created_at', 'updated_at', 'id_scan_path', 'certificate_path', 'passport_photo_path',
        'business_cert_path', 'trade_cert_paths', 'portfolio_paths', 'org_doc_path'];
    $docNames = ['id_scan' => 'National ID scan', 'certificate' => 'School certificate', 'passport' => 'Passport photo',
        'business_cert' => 'Business certificate', 'trade_cert' => 'Trade certificate', 'portfolio' => 'Portfolio photo',
        'org_doc' => 'Organisation document'];
    $data = collect($record->toArray())->except($hidden);
    $personName = $record->full_name ?? $record->contact_name;
    $memberCode = $record->ctp_code ?? $record->ef_code ?? $record->hirer_code;
    $initials = collect(explode(' ', trim($personName)))->filter()->take(2)->map(fn ($w) => mb_strtoupper(mb_substr($w, 0, 1)))->join('');
    $place = collect([$record->county ?? null, $record->sub_county ?? null, $record->ward ?? null])->filter()->join(' · ');
@endphp

@section('content')
<a class="back" href="{{ route('admin.registrations.index', $type) }}">Back to {{ $label }}</a>

<div class="applicant-head card">
    <span class="avatar-lg">{{ $initials }}</span>
    <div class="ah-main">
        <h1>{{ $personName }} <span class="status {{ $record->status }}">{{ str_replace('_', ' ', $record->status) }}</span></h1>
        <p class="ah-meta">
            <span><b>{{ $record->reference }}</b></span>
            @if ($place)<span>{{ $place }}</span>@endif
            @if ($memberCode)<span class="tag">{{ $memberCode }}</span>@endif
            <span>Submitted {{ $record->created_at->format('d M Y') }}, {{ $record->created_at->format('H:i') }}</span>
        </p>
    </div>
    <div class="ah-contact">
        <a href="tel:{{ $record->phone }}">{{ $record->phone }}</a>
        <a href="mailto:{{ $record->email }}">{{ $record->email }}</a>
    </div>
</div>

<div class="grid-2 review-grid">
    <div class="card">
        <h3>Application details</h3>
        @foreach ($groups as $group => $keys)
            @php $rows = $data->only($keys)->reject(fn ($v) => $v === null || $v === '' || (is_array($v) && ! count($v))); @endphp
            @if ($rows->isNotEmpty())
                <h4 class="dl-group">{{ $group }}</h4>
                <dl class="kv">
                    @foreach ($rows as $key => $value)
                        <dt>{{ $labels[$key] ?? \Illuminate\Support\Str::headline($key) }}</dt>
                        <dd>
                            @if (is_array($value))
                                <span class="chips">@foreach ($value as $v)<span class="chip">{{ \Illuminate\Support\Str::headline((string) $v) }}</span>@endforeach</span>
                            @elseif (in_array($key, ['dob'], true))
                                {{ \Illuminate\Support\Carbon::parse($value)->format('d M Y') }}
                                <span class="hint">({{ \Illuminate\Support\Carbon::parse($value)->age }} years old)</span>
                            @elseif (in_array($key, ['gender', 'track', 'tier', 'fundi_tier', 'education_level', 'org_type', 'status'], true))
                                <span class="chip">{{ \Illuminate\Support\Str::headline(str_replace('_', ' ', $value)) }}</span>
                            @else
                                {{ $value }}
                            @endif
                        </dd>
                    @endforeach
                </dl>
            @endif
        @endforeach
    </div>

    <div class="review-side">
        <div class="card">
            <h3>Documents ({{ count($docs) }})</h3>
            @forelse ($docs as $slot => $path)
                @php $docKey = explode('.', $slot)[0]; @endphp
                <a class="doc-card" href="{{ route('admin.registrations.file', [$type, $record->id, $slot]) }}" target="_blank">
                    <span class="dc-name">{{ $docNames[$docKey] ?? \Illuminate\Support\Str::headline($slot) }}</span>
                    <span class="dc-open">Open {{ str_ends_with(strtolower($path), '.pdf') ? 'PDF' : 'image' }} ↗</span>
                </a>
            @empty
                <p class="help">No documents uploaded.</p>
            @endforelse
        </div>

        @if ($record->status === 'pending_review')
            <div class="card decision">
                <h3>Decision</h3>
                <form method="POST" action="{{ route('admin.registrations.approve', [$type, $record->id]) }}"
                      data-confirm="Approve {{ $personName }} and issue a member ID?">
                    @csrf
                    <button class="btn btn-lime btn-block" type="submit">Approve &amp; issue ID</button>
                </form>
                <form method="POST" action="{{ route('admin.registrations.reject', [$type, $record->id]) }}" class="reject-form"
                      data-confirm="Reject this application? The reason is sent to the applicant.">
                    @csrf
                    <div class="field">
                        <label>Rejection reason (sent to applicant) <span class="req">*</span></label>
                        <textarea name="rejection_reason" rows="2" required placeholder="e.g. National ID scan is unreadable — please re-upload."></textarea>
                    </div>
                    <button class="btn btn-danger btn-block" type="submit">Reject application</button>
                </form>
            </div>
        @else
            <div class="card outcome {{ $record->status }}">
                <h3>{{ $record->status === 'active' ? 'Approved' : 'Rejected' }}</h3>
                @if ($record->status === 'active')
                    <p>{{ $personName }} is an active member — ID <b>{{ $memberCode }}</b>.</p>
                    @if ($invite)
                        <div class="link-box">
                            <label class="hint">Activation link — send this to the member</label>
                            <input type="text" readonly value="{{ route('activate.show', $invite->token) }}" onclick="this.select()">
                            <button class="btn btn-sm btn-outline" type="button"
                                    onclick="copyText('{{ route('activate.show', $invite->token) }}', this)">Copy link</button>
                        </div>
                        <div class="hint">
                            Status: <span class="status {{ $invite->status === 'accepted' ? 'active' : 'pending_review' }}">{{ ucfirst($invite->status) }}</span>
                            @if ($invite->expires_at) · expires {{ $invite->expires_at->format('d M Y') }}@endif
                            @if ($invite->status === 'accepted') · they have set a password and can sign in @endif
                        </div>
                        @if ($invite->status === 'pending')
                        <form method="POST" action="{{ route('admin.registrations.reissue', [$type, $record->id]) }}" class="mini-form"
                              data-confirm="Issue a fresh activation link? The old one stops working.">
                            @csrf
                            <button class="btn btn-sm btn-ghost" type="submit">New link</button>
                        </form>
                        @endif
                    @else
                        <p class="hint">Welcome email and SMS are dispatched in the notifications phase.</p>
                    @endif
                @else
                    <p>Reason sent to the applicant:<br><em>“{{ $record->rejection_reason }}”</em></p>
                    <form method="POST" action="{{ route('admin.registrations.approve', [$type, $record->id]) }}"
                          data-confirm="Reverse the rejection and approve this application?">
                        @csrf
                        <button class="btn btn-outline btn-sm" type="submit">Approve instead</button>
                    </form>
                @endif
            </div>

            @if ($type === 'ctp' && $record->status === 'active')
                <div class="card">
                    <h3>Project level</h3>
                    <p class="help">A CTP who has finished attachment, internship and their course moves into the fundi
                        pool — choose the level of projects they should see in their portal.</p>
                    <form method="POST" action="{{ route('admin.registrations.tier', [$type, $record->id]) }}" class="mini-form"
                          data-confirm="Change which projects {{ $personName }} can see?" data-title="Project level">
                        @csrf
                        <div class="field">
                            <label>Sees projects for</label>
                            <select name="fundi_tier">
                                <option value="">CTP attachment only</option>
                                @foreach (\App\Models\Project::CTP_POOL_TIERS as $key => $desc)
                                    <option value="{{ $key }}" @selected($record->fundi_tier === $key)>{{ $desc }}</option>
                                @endforeach
                            </select>
                        </div>
                        <button class="btn btn-sm btn-primary" type="submit">Save level</button>
                    </form>
                </div>
            @endif
        @endif
    </div>
</div>
@endsection
