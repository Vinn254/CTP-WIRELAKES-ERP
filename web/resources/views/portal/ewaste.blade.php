@extends('layouts.member')
@section('title', 'E-Waste collection')

@php
    use App\Models\EWasteCollection;
@endphp

@section('content')
<div class="page-intro">
    <p class="sub">Most of what the programme picks up is electronics — a dead monitor, a bag of cables, routers that
        a school has written off. Every line you register here goes on the <b>e-waste register</b> with your own name
        against it, the condition you found it in and a photo. The programme office weighs it in at a collection point,
        then routes it: repair, refurbish, recycle or safe disposal.
        {{ $isCtp ? 'You register what you collect in your area; an E-Fundi or the office carries it there.' : 'You register your own collections and forward the ones a CTP logged near you.' }}</p>
    <div class="member-stats">
        <div class="stat"><small>Lines registered</small><b>{{ $totals['lines'] }}</b></div>
        <div class="stat"><small>Items</small><b>{{ number_format($totals['items']) }}</b></div>
        <div class="stat"><small>Estimated</small><b>{{ $totals['estimated'] }} kg</b></div>
        <div class="stat"><small>Weighed in</small><b>{{ $totals['weighed'] ?: '—' }} kg</b></div>
        @unless ($isCtp)
            <div class="stat"><small>Waiting near you</small><b>{{ $totals['waiting'] }}</b></div>
        @endunless
    </div>
</div>

<div class="head-acts" style="margin-bottom:18px;">
    <button class="btn btn-sm btn-lime" type="button" data-sheet="add-collection">+ Add a collection</button>
</div>

@include('partials.sheet-start', ['name' => 'add-collection', 'title' => 'Add a collection',
    'sub' => 'One line per kind of item — a bag of cables and a pallet of monitors are registered separately.'])
    <form method="POST" action="{{ route('portal.ewaste.store') }}" enctype="multipart/form-data" class="fee-form"
          data-confirm="Put this on the e-waste register?" data-title="Register a collection">
        @csrf
        <input type="hidden" name="_sheet" value="add-collection">
        <div class="form-grid-3">
            <div class="field">
                <label>What it is <span class="req">*</span></label>
                <select name="item_type" required>
                    <option value="">Choose the item</option>
                    @foreach ($itemTypes as $key => $label)
                        <option value="{{ $key }}" @selected(old('item_type') === $key)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="field">
                <label>How many <span class="req">*</span></label>
                <input type="number" name="quantity" min="1" max="100000" required value="{{ old('quantity', 1) }}">
            </div>
            <div class="field">
                <label>Date collected <span class="req">*</span></label>
                <input type="date" name="collected_on" required max="{{ now()->toDateString() }}"
                       value="{{ old('collected_on', now()->toDateString()) }}">
            </div>
            <div class="field">
                <label>Condition <span class="req">*</span></label>
                <select name="condition" required>
                    @foreach ($conditions as $key => $label)
                        <option value="{{ $key }}" @selected(old('condition') === $key)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="field">
                <label>County <span class="req">*</span></label>
                <input type="text" name="county" list="ewaste-counties" required
                       value="{{ old('county', $member->county) }}">
            </div>
            <div class="field">
                <label>Ward</label>
                <input type="text" name="ward" value="{{ old('ward', $member->ward) }}">
            </div>
            <div class="field">
                <label>Community it came from</label>
                <select name="community_id">
                    <option value="">Not a registered community</option>
                    @foreach ($communities as $c)
                        <option value="{{ $c->id }}" @selected((int) old('community_id') === $c->id)>{{ $c->name }} — {{ $c->county }}</option>
                    @endforeach
                </select>
            </div>
            <div class="field">
                <label>Estimated weight (kg)</label>
                <input type="number" name="estimated_weight_kg" min="0" step="0.1" value="{{ old('estimated_weight_kg') }}"
                       placeholder="e.g. 45">
            </div>
            <div class="field">
                <label>A photo of the items</label>
                <input type="file" name="photo" accept="image/*">
                <div class="help">Jpg or png, up to 6 MB. The collection point sorts from the picture.</div>
            </div>
        </div>
        <div class="field" style="margin-top:12px;">
            <label>What you know about it</label>
            <textarea name="note" rows="3" maxlength="1000"
                      placeholder="e.g. 12 desktops written off by the school; 4 still boot. Stored dry at the chief's camp.">{{ old('note') }}</textarea>
        </div>
        <datalist id="ewaste-counties">@foreach ($counties as $name)<option value="{{ $name }}"></option>@endforeach</datalist>
        <div class="sheet-actions">
            <button class="btn btn-sm btn-ghost" type="button" data-close-sheet>Cancel</button>
            <button class="btn btn-sm btn-lime" type="submit">Register this collection</button>
        </div>
    </form>
@include('partials.sheet-end')

<div class="card">
    <div class="section-head"><h3>What {{ $isCtp ? 'you' : 'you as an E-Fundi' }} registered ({{ $mine->count() }})</h3></div>
    @if ($mine->isEmpty())
        <p class="help">Nothing on the register under your name yet. Add the first collection above — even a small
            bag of cables counts, and the programme reports the kilos it keeps out of the community.</p>
    @else
        <table class="data">
            <thead>
                <tr><th>Collection ID</th><th>Date</th><th>Item</th><th>Qty</th><th>Condition</th>
                    <th>Place</th><th>Weight</th><th>Where it has got to</th><th></th></tr>
            </thead>
            <tbody>
                @foreach ($mine as $col)
                    <tr>
                        <td><b>{{ $col->reference }}</b></td>
                        <td>{{ $col->collected_on->format('d M Y') }}</td>
                        <td>{{ $col->itemTypeLabel() }}</td>
                        <td>{{ $col->quantity }}</td>
                        <td><span class="tag {{ $col->condition === 'hazardous' ? 'gold' : 'gray' }}">{{ $col->conditionLabel() }}</span></td>
                        <td class="hint">{{ $col->placeLabel() }}</td>
                        <td>{{ $col->weightLabel() }}</td>
                        <td>
                            <span class="status {{ $col->status === 'received' ? 'active' : 'pending_review' }}">{{ $col->statusLabel() }}</span>
                            @if ($col->forwarder)
                                <div class="hint">carried by {{ $col->forwarder->full_name }} {{ $col->forwarded_on?->diffForHumans() }}</div>
                            @endif
                            @if ($col->receiver)
                                <div class="hint">weighed in by {{ $col->receiver->name }} {{ $col->received_on?->format('d M Y') }}</div>
                            @endif
                        </td>
                        <td>@if ($col->photo_path)
                                <a class="photo-thumb" href="{{ route('portal.ewaste.photo', $col) }}" data-zoom
                                    data-zoom-label="{{ $col->reference }} · {{ $col->itemTypeLabel() }} · {{ $col->conditionLabel() }}"
                                    aria-label="Zoom the photo"><img src="{{ route('portal.ewaste.photo', $col) }}" alt="Photo of {{ $col->reference }}"></a>
                            @else
                                <span class="hint">no photo</span>
                            @endif</td>
                    </tr>
                    @if ($col->note || $col->forward_note)
                        <tr><td colspan="9" class="hint">
                            @if ($col->note)You wrote: {{ $col->note }}. @endif
                            @if ($col->forward_note)What the E-Fundi reported: {{ $col->forward_note }}. @endif
                        </td></tr>
                    @endif
                @endforeach
            </tbody>
        </table>
    @endif
</div>

@unless ($isCtp)
    <div class="card">
        <div class="section-head"><h3>Registered by CTPs, waiting to be taken in ({{ $waiting->count() }})</h3></div>
        <p class="hint">These are in your county. Carry them to a collection point and forward the line — you can
            correct the condition on the way, because what it looks like in bulk is not what the CTP could see.</p>
        @forelse ($waiting as $col)
            <div class="row-line">
                <b>{{ $col->reference }}</b>
                <span class="hint">{{ $col->quantity }} × {{ $col->itemTypeLabel() }}</span>
                <span class="tag gray">{{ $col->conditionLabel() }}</span>
                <span class="hint">{{ $col->placeLabel() }}</span>
                <span class="hint">{{ $col->weightLabel() }}</span>
                <span class="hint">by {{ $col->collectorLabel() }} · {{ $col->collected_on->format('d M Y') }}</span>
                @if ($col->photo_path)
                    <a class="photo-thumb" href="{{ route('portal.ewaste.photo', $col) }}" data-zoom
                        data-zoom-label="{{ $col->reference }} · {{ $col->collectorLabel() }}"
                        aria-label="Zoom the photo"><img src="{{ route('portal.ewaste.photo', $col) }}" alt="Photo of {{ $col->reference }}"></a>
                @endif
            </div>
            <details class="need-form">
                <summary>Forward {{ $col->reference }} to the programme office</summary>
                <form method="POST" action="{{ route('portal.ewaste.forward', $col) }}" class="fee-form"
                      data-confirm="Send this collection to the office?" data-title="Forward collection">
                    @csrf
                    <div class="form-grid-2">
                        <div class="field">
                            <label>Condition as you saw it <span class="req">*</span></label>
                            <select name="condition" required>
                                @foreach (\App\Models\EWasteCollection::CONDITIONS as $key => $label)
                                    <option value="{{ $key }}" @selected($col->condition === $key)>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="field">
                            <label>Where you left it</label>
                            <input type="text" name="note" maxlength="1000"
                                   placeholder="e.g. Left at the Nyanda store with the caretaker; 2 monitors cracked in transit.">
                        </div>
                    </div>
                    <button class="btn btn-lime btn-block" type="submit" style="margin-top:10px;">Forward to the office</button>
                </form>
            </details>
        @empty
            <p class="hint">Nothing is waiting in your county right now.</p>
        @endforelse
    </div>
@endunless

<p class="hint">A collection line is never deleted. If the office weighs something differently, they record the
    weight on the same line and the first figure stays as what you estimated.</p>
@endsection
