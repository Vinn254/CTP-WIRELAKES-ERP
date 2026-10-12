@extends('layouts.admin')
@section('title', 'Application '.$record->reference)

@php
    $labels = [
        'reference' => 'Reference', 'full_name' => 'Full name', 'contact_name' => 'Contact name',
        'organisation' => 'Organisation', 'national_id' => 'National ID', 'phone' => 'Phone',
        'email' => 'Email', 'dob' => 'Date of birth', 'gender' => 'Gender',
        'county' => 'County', 'sub_county' => 'Sub-county', 'ward' => 'Ward', 'village' => 'Village',
        'education_level' => 'Education level', 'track' => 'Track', 'referral_code' => 'Referral code',
        'tier' => 'Fundi tier', 'business_name' => 'Business name', 'business_reg_no' => 'Business reg no.',
        'years_in_business' => 'Years in business', 'specialisations' => 'Specialisations',
        'service_counties' => 'Service counties', 'referee_name' => 'Referee name',
        'referee_phone' => 'Referee phone', 'referee_role' => 'Referee role', 'org_type' => 'Org type',
        'ctp_code' => 'CTP ID', 'ef_code' => 'Fundi ID', 'hirer_code' => 'Hirer ID',
        'status' => 'Status', 'rejection_reason' => 'Rejection reason',
    ];
    $hidden = ['id', 'created_at', 'updated_at', 'id_scan_path', 'certificate_path', 'passport_photo_path',
        'business_cert_path', 'trade_cert_paths', 'portfolio_paths', 'org_doc_path'];
    $docNames = ['id_scan' => 'National ID scan', 'certificate' => 'School certificate', 'passport' => 'Passport photo',
        'business_cert' => 'Business certificate', 'trade_cert' => 'Trade certificate', 'portfolio' => 'Portfolio photo', 'org_doc' => 'Organisation document'];
@endphp

@section('content')
<p><a href="{{ route('admin.registrations.index', $type) }}">← Back to {{ $label }}</a></p>
<h1>{{ $record->full_name ?? $record->contact_name }} <span class="status {{ $record->status }}">{{ str_replace('_',' ', $record->status) }}</span></h1>
<p class="sub" style="color:var(--muted);">{{ $labels['reference'] ?? 'Reference' }}: <strong>{{ $record->reference }}</strong> · Submitted {{ $record->created_at->format('d M Y H:i') }}</p>

<div class="grid-2" style="align-items:start;margin-top:20px;">
    <div class="card">
        <h3>Application details</h3>
        <dl class="kv">
            @foreach ($record->toArray() as $key => $value)
                @continue(in_array($key, $hidden, true) || $value === null || $value === '')
                <dt>{{ $labels[$key] ?? Str::headline($key) }}</dt>
                <dd>{{ is_array($value) ? implode(', ', $value) : $value }}</dd>
            @endforeach
        </dl>
    </div>

    <div>
        <div class="card">
            <h3>Documents</h3>
            @forelse ($docs as $slot => $path)
                <a class="doc-link" href="{{ route('admin.registrations.file', [$type, $record->id, $slot]) }}" target="_blank">
                    📄 {{ $docNames[explode('.', $slot)[0]] ?? Str::headline($slot) }}
                    <span class="help">(opens in new tab)</span>
                </a>
            @empty
                <p class="help">No documents uploaded.</p>
            @endforelse
        </div>

        @if ($record->status === 'pending_review')
            <div class="card" style="margin-top:16px;">
                <h3>Decision</h3>
                <form method="POST" action="{{ route('admin.registrations.approve', [$type, $record->id]) }}" onsubmit="return confirm('Approve and issue member ID?')">
                    @csrf
                    <button class="btn btn-lime btn-block" type="submit">Approve &amp; issue ID</button>
                </form>
                <form method="POST" action="{{ route('admin.registrations.reject', [$type, $record->id]) }}" style="margin-top:14px;">
                    @csrf
                    <div class="field">
                        <label>Rejection reason (sent to applicant) <span class="req">*</span></label>
                        <textarea name="rejection_reason" rows="2" required></textarea>
                    </div>
                    <button class="btn btn-danger btn-block" type="submit">Reject application</button>
                </form>
            </div>
        @endif
    </div>
</div>
@endsection
