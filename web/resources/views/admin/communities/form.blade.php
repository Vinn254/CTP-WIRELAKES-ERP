@extends('layouts.admin')
@section('title', $community->exists ? 'Edit community' : 'Add community')

@php $editing = $community->exists; @endphp

@section('content')
<p class="back"><a href="{{ $editing ? route('admin.communities.show', $community) : route('admin.communities.index') }}">&larr; {{ $editing ? $community->name : 'Community register' }}</a></p>
<h1>{{ $editing ? 'Edit '.$community->name : 'Add a community' }}</h1>
<p class="page-lede">Record a community's name, location levels and size.</p>

<form method="POST" action="{{ $editing ? route('admin.communities.update', $community) : route('admin.communities.store') }}" class="card">
    @csrf
    @if ($editing) @method('PATCH') @endif

    <h3>Name &amp; location</h3>
    <div class="form-grid">
        <div class="field">
            <label>Community name <span class="req">*</span></label>
            <input type="text" name="name" value="{{ old('name', $community->name) }}" required maxlength="160" placeholder="e.g. Nyanda youth cluster">
        </div>
        <div class="field">
            <label>County <span class="req">*</span></label>
            <input type="text" name="county" list="county-names" value="{{ old('county', $community->county) }}" required>
        </div>
        <div class="field">
            <label>Sub-county <span class="req">*</span></label>
            <input type="text" name="sub_county" value="{{ old('sub_county', $community->sub_county) }}" required>
        </div>
        <div class="field">
            <label>Ward <span class="req">*</span></label>
            <input type="text" name="ward" value="{{ old('ward', $community->ward) }}" required>
        </div>
        <div class="field">
            <label>Village / location</label>
            <input type="text" name="village" value="{{ old('village', $community->village) }}">
        </div>
    </div>

    <h3 style="margin-top:20px;">Size &amp; pin</h3>
    <div class="form-grid">
        <div class="field">
            <label>Population</label>
            <input type="number" name="population" min="1" value="{{ old('population', $community->population) }}" placeholder="e.g. 4200">
        </div>
        <div class="field">
            <label>Households</label>
            <input type="number" name="households" min="1" value="{{ old('households', $community->households) }}" placeholder="e.g. 980">
        </div>
        <div class="field">
            <label>GPS latitude</label>
            <input type="text" name="gps_lat" id="gpsLat" value="{{ old('gps_lat', $community->gps_lat) }}" placeholder="-0.0918020">
        </div>
        <div class="field">
            <label>GPS longitude</label>
            <input type="text" name="gps_lng" id="gpsLng" value="{{ old('gps_lng', $community->gps_lng) }}" placeholder="34.7680000">
        </div>
    </div>
    <p class="hint">
        <button class="btn btn-sm btn-ghost" type="button" id="pinHere">Use this device’s location</button>
        <span id="pinNote" class="hint">The pin is what puts this community on the impact map.</span>
    </p>

    <div class="field" style="margin-top:14px;">
        <label>What this community is like</label>
        <textarea name="summary" rows="4" maxlength="4000" placeholder="Who lives here, what the young people do, what already exists.">{{ old('summary', $community->summary) }}</textarea>
        <div class="help">Read before a cohort is booked here or a project is matched.</div>
    </div>

    <datalist id="county-names">@foreach ($counties as $name)<option value="{{ $name }}"></option>@endforeach</datalist>

    <div class="form-actions">
        <button class="btn btn-primary" type="submit">{{ $editing ? 'Save community' : 'Add community' }}</button>
        <a class="btn btn-ghost" href="{{ $editing ? route('admin.communities.show', $community) : route('admin.communities.index') }}">Cancel</a>
    </div>
</form>

<script>
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
                pinNote.textContent = 'Pin captured. Save to place this community on the map.';
            },
            () => { pinNote.textContent = 'Location permission refused — type the coordinates instead.'; }
        );
    });
</script>
@endsection
