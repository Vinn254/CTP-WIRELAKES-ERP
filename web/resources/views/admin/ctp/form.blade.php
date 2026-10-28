@extends('layouts.admin')
@section('title', 'Edit CTP profile')

@php
    use App\Models\CtpRegistration;
    $certs = count((array) $ctp->certificates_held) ? $ctp->certificates_held : [''];
@endphp

@section('content')
<p class="back"><a href="{{ route('admin.promoters.show', $ctp) }}">&larr; {{ $ctp->ctp_code ?: $ctp->full_name }}</a></p>
<h1>Edit profile — {{ $ctp->full_name }}</h1>
<p class="page-lede">Update the field details the programme's modules and map use.</p>

<form method="POST" action="{{ route('admin.promoters.update', $ctp) }}" class="card">
    @csrf
    @method('PATCH')

    <h3>Location</h3>
    <div class="form-grid">
        <div class="field">
            <label>County <span class="req">*</span></label>
            <input type="text" name="county" list="county-names" value="{{ old('county', $ctp->county) }}" required>
        </div>
        <div class="field">
            <label>Sub-county <span class="req">*</span></label>
            <input type="text" name="sub_county" value="{{ old('sub_county', $ctp->sub_county) }}" required>
        </div>
        <div class="field">
            <label>Ward <span class="req">*</span></label>
            <input type="text" name="ward" value="{{ old('ward', $ctp->ward) }}" required>
        </div>
        <div class="field">
            <label>Village</label>
            <input type="text" name="village" value="{{ old('village', $ctp->village) }}">
        </div>
        <div class="field">
            <label>GPS latitude</label>
            <input type="text" name="gps_lat" id="gpsLat" value="{{ old('gps_lat', $ctp->gps_lat) }}" placeholder="-0.0918020">
        </div>
        <div class="field">
            <label>GPS longitude</label>
            <input type="text" name="gps_lng" id="gpsLng" value="{{ old('gps_lng', $ctp->gps_lng) }}" placeholder="34.7680000">
        </div>
    </div>
    <p class="hint">
        <button class="btn btn-sm btn-ghost" type="button" id="pinHere">Use this device’s location</button>
        <span id="pinNote" class="hint">The pin is what places this CTP on the impact map.</span>
    </p>

    <h3 style="margin-top:20px;">Education</h3>
    <div class="form-grid">
        <div class="field">
            <label>Highest level <span class="req">*</span></label>
            <select name="education_level" required>
                @foreach (CtpRegistration::EDUCATION_LEVELS as $key => $label)
                    <option value="{{ $key }}" @selected(old('education_level', $ctp->education_level) === $key)>{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <div class="field">
            <label>Year left school</label>
            <input type="number" name="year_left_school" min="1960" max="{{ now()->year }}" value="{{ old('year_left_school', $ctp->year_left_school) }}" placeholder="2023">
        </div>
        <div class="field">
            <label>Track</label>
            <select name="track">
                @foreach (CtpRegistration::TRACKS as $key => $label)
                    <option value="{{ $key }}" @selected(old('track', $ctp->track) === $key)>{{ $label }}</option>
                @endforeach
            </select>
            <div class="help">Junior CTPs skip the full cohort — they sit the practical and certification check.</div>
        </div>
    </div>

    <div class="field">
        <label>Certificates held</label>
        <div id="certList">
            @foreach ($certs as $i => $cert)
                <input class="repeat-input" type="text" name="certificates[]" value="{{ old('certificates.'.$i, $cert) }}" placeholder="e.g. CPA Part II — Kenya Accountants Body, 2024">
            @endforeach
        </div>
        <button class="btn btn-sm btn-ghost" type="button" id="addCert">Add another certificate</button>
        <div class="help">What the CTP already holds. Junior-track certificates are verified before the certification check.</div>
    </div>

    <h3 style="margin-top:20px;">Preferences</h3>
    <div class="field">
        <label>Counties this CTP will serve</label>
        <select name="service_counties[]" multiple size="6">
            @foreach ($counties as $name)
                <option value="{{ $name }}" @selected(in_array($name, (array) old('service_counties', $ctp->service_counties ?: []), true))>{{ $name }}</option>
            @endforeach
        </select>
        <div class="help">Leave empty to serve the home county only.</div>
    </div>
    <label class="check-row">
        <input type="checkbox" name="available_for_mobilisation" value="1" @checked(old('available_for_mobilisation', $ctp->available_for_mobilisation))>
        <span>Available for mobilisation drives</span>
    </label>
    <label class="check-row">
        <input type="checkbox" name="trainer_opt_in" value="1" @checked(old('trainer_opt_in', $ctp->trainer_opt_in))>
        <span>Opted in to deliver training as a Fundi-Trainer once rated 3.5 or better</span>
    </label>

    <datalist id="county-names">@foreach ($counties as $name)<option value="{{ $name }}"></option>@endforeach</datalist>

    <div class="form-actions">
        <button class="btn btn-primary" type="submit">Save profile</button>
        <a class="btn btn-ghost" href="{{ route('admin.promoters.show', $ctp) }}">Cancel</a>
    </div>
</form>

<script>
    document.getElementById('addCert').addEventListener('click', function () {
        const input = document.createElement('input');
        input.className = 'repeat-input';
        input.type = 'text';
        input.name = 'certificates[]';
        input.placeholder = 'Another certificate';
        document.getElementById('certList').appendChild(input);
        input.focus();
    });

    const pinNote = document.getElementById('pinNote');
    document.getElementById('pinHere').addEventListener('click', function () {
        if (! navigator.geolocation) {
            pinNote.textContent = 'This browser cannot read a location — type the coordinates instead.';
            return;
        }
        pinNote.textContent = 'Reading GPS…';
        navigator.geolocation.getCurrentPosition(
            (p) => {
                document.getElementById('gpsLat').value = p.coords.latitude.toFixed(7);
                document.getElementById('gpsLng').value = p.coords.longitude.toFixed(7);
                pinNote.textContent = 'Pin captured. Save the profile to place this CTP on the map.';
            },
            () => { pinNote.textContent = 'Location permission refused — type the coordinates instead.'; }
        );
    });
</script>
@endsection
