@extends('layouts.admin')
@section('title', $asset->asset_code)

@php
    use App\Support\Finance;
    use App\Support\Technology;

    $assetPill = ['operational' => 'active', 'degraded' => 'pending_review', 'in_repair' => 'awarded', 'down' => 'rejected', 'retired' => 'gray'];
    $ticketPill = ['open' => 'pending_review', 'assigned' => 'pending_review', 'in_progress' => 'awarded', 'resolved' => 'active', 'closed' => 'gray'];
    $openTickets = $asset->tickets->whereIn('status', ['open', 'assigned', 'in_progress'])->count();
@endphp

@section('content')
<p class="back"><a href="{{ route('admin.technology.index', ['tab' => 'assets']) }}">&larr; Asset register</a></p>
<h1>{{ $asset->name }}</h1>
<p class="sub" style="color:var(--muted);margin:6px 0 16px;">
    {{ $asset->asset_code }} · {{ $asset->categoryLabel() }} · tagged <b>{{ $asset->qr_payload }}</b> (§12.7)
</p>

<div class="card">
    <div class="section-head"><h3>On the register</h3></div>
    <div class="detail-grid">
        <div><small>Sub-area</small><b>{{ $asset->categoryLabel() }}</b></div>
        <div><small>Kind</small><b>{{ $asset->kind ?: '—' }}</b></div>
        <div><small>Serial</small><b>{{ $asset->serial_number ?: '—' }}</b></div>
        <div><small>Status</small><b><span class="status {{ $assetPill[$asset->status] ?? 'pending_review' }}">{{ $asset->statusLabel() }}</span></b></div>
        <div><small>Held by</small><b>{{ $asset->assigned_name ?: '—' }}<span class="hint"> {{ $asset->assigned_type ? '('.$asset->assigned_type.')' : '' }}</span></b></div>
        <div><small>Location</small><b>{{ $asset->location ?: '—' }}<span class="hint"> {{ $asset->county }}</span></b></div>
        <div><small>Source</small><b>{{ $asset->sourceLabel() }}</b></div>
        <div><small>Cost</small><b>{{ Finance::ksh($asset->purchase_cost_ksh) }}</b></div>
        <div><small>Supplier</small><b>{{ $asset->supplier ?: '—' }}</b></div>
        <div><small>Purchased</small><b>{{ $asset->purchase_date?->format('d M Y') ?: '—' }}</b></div>
        <div><small>Warranty to</small><b>{{ $asset->warranty_expires_on?->format('d M Y') ?: '—' }}</b></div>
        <div><small>Licence</small><b>{{ $asset->licence_key ?: '—' }}<span class="hint">{{ $asset->licence_seats ? ' · '.$asset->licence_seats.' seats' : '' }}{{ $asset->licence_renews_on ? ' · renews '.$asset->licence_renews_on->format('d M Y') : '' }}</span></b></div>
    </div>
    @if ($asset->description)<p style="margin:12px 0 0;">{{ $asset->description }}</p>@endif
    @if ($asset->isRetired())
        <p style="margin:12px 0 0;border-left:4px solid var(--muted);padding-left:10px;">
            <b>Retired {{ $asset->decommissioned_on?->format('d M Y') }}.</b> {{ $asset->decommission_note }}
            @if ($asset->ewaste_collection_id)
                — routed to E-Waste <a href="{{ route('admin.ewaste.index') }}">{{ $asset->ewasteCollection->reference ?: 'collection' }}</a> (§12.7).
            @else
                — kept on the register with the reason.
            @endif
        </p>
    @endif
    @if ($asset->notes)<p class="hint" style="margin-top:10px;">Notes: {{ $asset->notes }}</p>@endif
</div>

<div class="card">
    <div class="section-head"><h3>Repair history</h3><span class="hint">{{ $asset->tickets->count() }} ticket(s) · {{ $openTickets }} open</span></div>

    @forelse ($asset->tickets as $t)
        <div class="card gaps" style="border-left:4px solid {{ $t->isOpen() ? 'var(--danger)' : 'var(--muted)' }};margin-bottom:12px;">
            <div>
                <b>{{ $t->reference }}</b> — {{ $t->title }}
                <span class="status {{ $ticketPill[$t->status] ?? 'pending_review' }}">{{ $t->statusLabel() }}</span>
                <span class="tag {{ $t->priority === 'urgent' ? '' : 'gold' }}">{{ $t->priorityLabel() }}</span>
                <div class="hint">opened by {{ $t->opened_by ?: 'the office' }} {{ $t->created_at?->format('d M Y') }}
                    · {{ $t->fundi->full_name ? 'assigned to '.$t->fundi->full_name : 'not yet assigned' }}</div>
            </div>
            <p style="margin:8px 0 4px;"><b>Fault:</b> {{ $t->fault }}</p>
            @if ($t->resolution)<p style="margin:4px 0;"><b>Fix:</b> {{ $t->resolution }}</p>@endif

            @if ($t->status === 'open')
                <form method="POST" action="{{ route('admin.technology.ticket.assign', $t) }}" class="fee-form" data-title="Assign repair">
                    @csrf
                    <div class="field">
                        <label>Send to an E-Fundi to fix</label>
                        <select name="assigned_fundi_id" required>
                            <option value="">Choose the fundi…</option>
                            @foreach ($fundis as $f)
                                <option value="{{ $f->id }}">{{ $f->full_name }}{{ $f->ef_code ? ' ('.$f->ef_code.')' : '' }}</option>
                            @endforeach
                        </select>
                    </div>
                    <button class="btn btn-sm btn-primary" type="submit">Assign the job</button>
                </form>
            @elseif ($t->status === 'assigned' || $t->status === 'in_progress')
                <form method="POST" action="{{ route('admin.technology.ticket.resolve', $t) }}" class="fee-form" data-title="Record the fix">
                    @csrf
                    <div class="field">
                        <label>What fixed it <span class="req">*</span></label>
                        <textarea name="resolution" rows="2" required maxlength="5000" placeholder="Write the repair the office can confirm">{{ $t->resolution }}</textarea>
                    </div>
                    <button class="btn btn-sm btn-primary" type="submit">Mark as fixed</button>
                    <span class="hint">Or let the assigned fundi record it on their own My repair jobs screen.</span>
                </form>
            @elseif ($t->status === 'resolved')
                <form method="POST" action="{{ route('admin.technology.ticket.close', $t) }}" class="fee-form" data-confirm="Close {{ $t->reference }}? It stays on the asset's history." data-title="Close ticket">
                    @csrf
                    <button class="btn btn-sm btn-primary" type="submit">Confirm and close</button>
                </form>
            @else
                <p class="hint">Closed {{ $t->closed_on?->format('d M Y H:i') }} — kept on the asset's repair history (§12.7).</p>
            @endif
        </div>
    @empty
        <p class="help">No fault has been opened against this asset.</p>
    @endforelse

    @if (! $asset->isRetired())
        <details class="card need-form">
            <summary>Open a repair ticket on {{ $asset->asset_code }}</summary>
            <form method="POST" action="{{ route('admin.technology.asset.ticket', $asset) }}" class="fee-form" data-title="Open ticket">
                @csrf
                <div class="form-grid">
                    <div class="field">
                        <label>Short title <span class="req">*</span></label>
                        <input type="text" name="title" required maxlength="200" placeholder="e.g. No power on boot">
                    </div>
                    <div class="field">
                        <label>Priority <span class="req">*</span></label>
                        <select name="priority" required>
                            @foreach (Technology::PRIORITIES as $key => $label)
                                <option value="{{ $key }}" @selected($key === 'normal')>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="field">
                        <label>Reported by</label>
                        <input type="text" name="opened_by" maxlength="160" value="{{ $asset->assigned_name }}">
                    </div>
                </div>
                <div class="field">
                    <label>The fault <span class="req">*</span></label>
                    <textarea name="fault" rows="2" required maxlength="5000" placeholder="What is wrong, so a fundi can pick it up"></textarea>
                </div>
                <button class="btn btn-sm btn-primary" type="submit">Open the ticket</button>
            </form>
        </details>
    @endif
</div>

@if (! $asset->isRetired())
    <div class="card">
        <div class="section-head"><h3>Take it out of service</h3></div>
        <p class="help">Retiring writes the reason on the register and, if you point it at one, hands the physical item to
            E-Waste (Module 10). An asset with an open ticket cannot be retired — close or fix the ticket first (§12.7).</p>
        <form method="POST" action="{{ route('admin.technology.asset.decommission', $asset) }}" class="fee-form" data-confirm="Retire {{ $asset->asset_code }}? It leaves the live register." data-title="Retire asset">
            @csrf
            <div class="field">
                <label>Why it is out of service <span class="req">*</span></label>
                <textarea name="decommission_note" rows="2" required maxlength="500" placeholder="Beyond repair, written off, replaced…"></textarea>
            </div>
            <div class="field">
                <label>Route to an E-Waste record (optional)</label>
                <input type="number" name="ewaste_collection_id" min="1" placeholder="collection record id">
            </div>
            <button class="btn btn-sm btn-outline" type="submit">Retire the asset</button>
        </form>
    </div>
@endif

<div class="card">
    <details @if ($errors->any()) open @endif>
        <summary><b>Edit these details</b></summary>
        <form method="POST" action="{{ route('admin.technology.asset.update', $asset) }}" class="fee-form" data-title="Update asset">
            @csrf
            <div class="form-grid">
                <div class="field">
                    <label>Sub-area <span class="req">*</span></label>
                    <select name="category" required>
                        @foreach ($categories as $key => $c)
                            <option value="{{ $key }}" @selected(old('category', $asset->category) === $key)>{{ $c['label'] }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="field">
                    <label>Name <span class="req">*</span></label>
                    <input type="text" name="name" required maxlength="200" value="{{ old('name', $asset->name) }}">
                </div>
                <div class="field">
                    <label>Kind</label>
                    <input type="text" name="kind" maxlength="60" value="{{ old('kind', $asset->kind) }}">
                </div>
                <div class="field">
                    <label>Serial number</label>
                    <input type="text" name="serial_number" maxlength="120" value="{{ old('serial_number', $asset->serial_number) }}">
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
                            <option value="{{ $key }}" @selected((string) old('assigned_type', $asset->assigned_type) === $key)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="field">
                    <label>Who holds it</label>
                    <input type="text" name="assigned_name" list="tech-holders-edit" maxlength="200" value="{{ old('assigned_name', $asset->assigned_name) }}">
                    <datalist id="tech-holders-edit">
                        @foreach ($communities as $c)<option value="{{ $c->name }}"></option>@endforeach
                        @foreach ($organisations as $o)<option value="{{ $o->organisation }}"></option>@endforeach
                    </datalist>
                </div>
                <div class="field">
                    <label>Location</label>
                    <input type="text" name="location" maxlength="200" value="{{ old('location', $asset->location) }}">
                </div>
                <div class="field">
                    <label>County</label>
                    <input type="text" name="county" maxlength="80" value="{{ old('county', $asset->county) }}">
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
                    <input type="text" name="supplier" maxlength="200" value="{{ old('supplier', $asset->supplier) }}">
                </div>
                <div class="field">
                    <label>Warranty expires</label>
                    <input type="date" name="warranty_expires_on" value="{{ old('warranty_expires_on', $asset->warranty_expires_on?->format('Y-m-d')) }}">
                </div>
                <div class="field">
                    <label>Licence key</label>
                    <input type="text" name="licence_key" maxlength="200" value="{{ old('licence_key', $asset->licence_key) }}">
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
                <textarea name="notes" rows="2" maxlength="5000">{{ old('notes', $asset->notes) }}</textarea>
            </div>
            <button class="btn btn-sm btn-primary" type="submit">Save changes</button>
        </form>
    </details>
</div>
@endsection
