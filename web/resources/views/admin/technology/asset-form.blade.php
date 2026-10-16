@extends('layouts.admin')
@section('title', 'Add an asset')

@php
    use App\Support\Technology;
@endphp

@section('content')
<p class="back"><a href="{{ route('admin.technology.index', ['tab' => 'assets']) }}">&larr; Asset register</a></p>
<h1>Add an asset</h1>
<p class="sub" style="color:var(--muted);margin:6px 0 16px;">
    One line on the register — a device, a licence or a kit. Pick the sub-area it belongs to (§12.1); the code and the
    QR payload are given to it when you save. Warranty and licence end-dates feed the alerts (§12.7).
</p>

@if ($errors->any())
    <div class="card" style="border-left:4px solid var(--danger);">
        <ul style="margin:0;padding-left:18px;">
            @foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach
        </ul>
    </div>
@endif

<div class="card">
    <form method="POST" action="{{ route('admin.technology.asset.store') }}" class="fee-form" data-title="Add asset">
        @csrf
        <div class="form-grid">
            <div class="field">
                <label>Sub-area <span class="req">*</span></label>
                <select name="category" required>
                    @foreach ($categories as $key => $c)
                        <option value="{{ $key }}" @selected(old('category', $asset->category) === $key)>{{ $c['label'] }} — {{ $c['note'] }}</option>
                    @endforeach
                </select>
            </div>
            <div class="field">
                <label>Name <span class="req">*</span></label>
                <input type="text" name="name" required maxlength="200" value="{{ old('name') }}" placeholder="e.g. Dell Latitude 5440">
            </div>
            <div class="field">
                <label>Kind</label>
                <input type="text" name="kind" maxlength="60" value="{{ old('kind') }}" placeholder="laptop, router, licence…">
            </div>
            <div class="field">
                <label>Serial number</label>
                <input type="text" name="serial_number" maxlength="120" value="{{ old('serial_number') }}">
            </div>
            <div class="field">
                <label>Status <span class="req">*</span></label>
                <select name="status" required>
                    @foreach (Technology::ASSET_STATUSES as $key => $label)
                        <option value="{{ $key }}" @selected(old('status', $asset->status) === $key)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="field">
                <label>Source <span class="req">*</span></label>
                <select name="source" required>
                    @foreach (Technology::SOURCES as $key => $label)
                        <option value="{{ $key }}" @selected(old('source', $asset->source) === $key)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="field">
                <label>Handed to</label>
                <select name="assigned_type">
                    @foreach (Technology::ASSIGNEES as $key => $label)
                        <option value="{{ $key }}" @selected(old('assigned_type', $asset->assigned_type) === $key)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="field">
                <label>Who holds it</label>
                <input type="text" name="assigned_name" list="tech-holders" maxlength="200" value="{{ old('assigned_name') }}" placeholder="community, organisation or person">
                <datalist id="tech-holders">
                    @foreach ($communities as $c)<option value="{{ $c->name }}"></option>@endforeach
                    @foreach ($organisations as $o)<option value="{{ $o->organisation }}"></option>@endforeach
                </datalist>
            </div>
            <div class="field">
                <label>Location</label>
                <input type="text" name="location" maxlength="200" value="{{ old('location') }}">
            </div>
            <div class="field">
                <label>County</label>
                <input type="text" name="county" maxlength="80" value="{{ old('county') }}">
            </div>
            <div class="field">
                <label>Purchased on</label>
                <input type="date" name="purchase_date" value="{{ old('purchase_date', $asset->purchase_date?->format('Y-m-d')) }}">
            </div>
            <div class="field">
                <label>Purchase cost (KSh)</label>
                <input type="number" name="purchase_cost_ksh" min="0" step="0.01" value="{{ old('purchase_cost_ksh', $asset->purchase_cost_ksh) }}">
            </div>
            <div class="field">
                <label>Supplier</label>
                <input type="text" name="supplier" maxlength="200" value="{{ old('supplier') }}">
            </div>
            <div class="field">
                <label>Warranty expires</label>
                <input type="date" name="warranty_expires_on" value="{{ old('warranty_expires_on', $asset->warranty_expires_on?->format('Y-m-d')) }}">
            </div>
            <div class="field">
                <label>Licence key</label>
                <input type="text" name="licence_key" maxlength="200" value="{{ old('licence_key') }}">
            </div>
            <div class="field">
                <label>Licence seats</label>
                <input type="number" name="licence_seats" min="0" max="65535" value="{{ old('licence_seats', $asset->licence_seats) }}">
            </div>
            <div class="field">
                <label>Licence renews on</label>
                <input type="date" name="licence_renews_on" value="{{ old('licence_renews_on', $asset->licence_renews_on?->format('Y-m-d')) }}">
            </div>
        </div>
        <div class="field">
            <label>Description</label>
            <textarea name="description" rows="2" maxlength="5000">{{ old('description', $asset->description) }}</textarea>
        </div>
        <div class="field">
            <label>Notes</label>
            <textarea name="notes" rows="2" maxlength="5000">{{ old('notes') }}</textarea>
        </div>
        <button class="btn btn-sm btn-primary" type="submit">Add to the register</button>
        <a class="btn btn-sm btn-ghost" href="{{ route('admin.technology.index', ['tab' => 'assets']) }}">Cancel</a>
    </form>
</div>
@endsection
