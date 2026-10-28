@extends('layouts.admin')
@section('title', $item->sku)

@php
    use App\Models\StockMovement;
    use App\Support\Finance;
@endphp

@section('content')
@include('partials.errors')
<p class="back"><a href="{{ route('admin.inventory.index') }}">&larr; Stock register</a></p>

<div class="applicant-head card">
    <div class="ah-main">
        <h1>{{ $item->name }}
            <span class="status {{ $item->isEmpty() ? 'closed' : ($item->isLow() ? 'pending_review' : 'active') }}">
                {{ $item->isEmpty() ? 'Out of stock' : ($item->isLow() ? 'Needs reordering' : 'In stock') }}
            </span>
        </h1>
        <p class="ah-meta">
            <span class="tag">{{ $item->sku }}</span>
            <span>{{ \App\Models\InventoryItem::categoryLabel($item->category) }}</span>
            <span>{{ $item->quantityLabel() }} on hand</span>
            <span>reorder at {{ $item->reorderLabel() }}</span>
            <span>{{ $item->unitCostLabel() }} each · {{ $item->valueLabel() }} on the shelf</span>
        </p>
    </div>
    <div class="ah-contact">
        <span class="hint">Kept at</span>
        <b>{{ $item->placeLabel() }}</b>
    </div>
</div>

@if ($item->description)
    <div class="card gaps"><b>On the card:</b> {{ $item->description }}</div>
@endif

<div class="grid-2 review-grid">
    <div class="stack">
        <div class="card">
            <h3>The card</h3>
            <dl class="kv">
                <dt>SKU</dt><dd>{{ $item->sku }}</dd>
                <dt>Item</dt><dd>{{ $item->name }}</dd>
                <dt>Category</dt><dd>{{ \App\Models\InventoryItem::categoryLabel($item->category) }}</dd>
                <dt>Unit</dt><dd>{{ \App\Models\InventoryItem::unitLabel($item->unit) }}</dd>
                <dt>Quantity on hand</dt><dd><b>{{ $item->quantityLabel() }}</b>
                    <span class="hint">the sum of the movements below — never typed in</span></dd>
                <dt>Reorder level</dt><dd>{{ $item->reorderLabel() }}</dd>
                <dt>Unit cost</dt><dd>{{ $item->unitCostLabel() }}</dd>
                <dt>Value held</dt><dd>{{ $item->valueLabel() }}</dd>
                <dt>Kept at</dt><dd>{{ $item->placeLabel() }}</dd>
                <dt>Standing</dt><dd>{{ $item->statusLabel() }}</dd>
            </dl>
        </div>

        <div class="card">
            <h3>Bring goods in</h3>
            <p class="hint">Goods normally arrive on a purchase order and are signed in there. A movement
                recorded on the card is for a collection brought in, a return, a transfer or a stock count.</p>
            <form method="POST" action="{{ route('admin.inventory.move', $item) }}" class="fee-form"
                  data-confirm="Record this movement onto {{ $item->sku }}?" data-title="Goods in">
                @csrf
                <input type="hidden" name="direction" value="in">
                <div class="field">
                    <label>Why the shelf went up <span class="req">*</span></label>
                    <select name="reason" required>
                        @foreach (StockMovement::IN_REASONS as $key => $label)
                            <option value="{{ $key }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="form-grid-2" style="margin-top:10px;">
                    <div class="field">
                        <label>How much <span class="req">*</span></label>
                        <input type="number" name="quantity" required min="0.01" step="0.01">
                    </div>
                    <div class="field">
                        <label>Unit cost (KSH)</label>
                        <input type="number" name="unit_cost_ksh" min="0" step="0.01" value="{{ $item->unit_cost_ksh }}">
                    </div>
                </div>
                <div class="form-grid-2" style="margin-top:10px;">
                    <div class="field">
                        <label>Date <span class="req">*</span></label>
                        <input type="date" name="moved_on" required value="{{ now()->toDateString() }}">
                    </div>
                    <div class="field">
                        <label>For which project</label>
                        <select name="project_id">
                            <option value="">The store itself</option>
                            @foreach ($projects as $p)
                                <option value="{{ $p->id }}">{{ $p->title }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <div class="field" style="margin-top:10px;">
                    <label>Note</label>
                    <input type="text" name="note" maxlength="1000" placeholder="Counted by, delivery note, what moved.">
                </div>
                <button class="btn btn-lime btn-block" type="submit" style="margin-top:12px;">Record the movement in</button>
            </form>
        </div>
    </div>

    <div class="stack">
        <div class="card">
            <h3>Draw materials out</h3>
            <p class="hint">An issue out posts its value on the money register against the budget the category feeds, so a project's materials are on the project's own account. The shelf never goes below zero —
                raise a purchase request for what is missing instead.</p>
            <form method="POST" action="{{ route('admin.inventory.move', $item) }}" class="fee-form"
                  data-confirm="Issue this material out of the store?" data-title="Materials out">
                @csrf
                <input type="hidden" name="direction" value="out">
                <div class="field">
                    <label>Why the shelf went down <span class="req">*</span></label>
                    <select name="reason" required>
                        @foreach (StockMovement::OUT_REASONS as $key => $label)
                            <option value="{{ $key }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="form-grid-2" style="margin-top:10px;">
                    <div class="field">
                        <label>How much <span class="req">*</span></label>
                        <input type="number" name="quantity" required min="0.01" step="0.01" max="{{ (float) $item->qty_on_hand }}">
                    </div>
                    <div class="field">
                        <label>Unit cost (KSH)</label>
                        <input type="number" name="unit_cost_ksh" min="0" step="0.01" value="{{ $item->unit_cost_ksh }}">
                    </div>
                </div>
                <div class="form-grid-2" style="margin-top:10px;">
                    <div class="field">
                        <label>Date <span class="req">*</span></label>
                        <input type="date" name="moved_on" required value="{{ now()->toDateString() }}">
                    </div>
                    <div class="field">
                        <label>For which cohort</label>
                        <select name="cohort_id">
                            <option value="">None</option>
                            @foreach ($cohorts as $c)
                                <option value="{{ $c->id }}">{{ $c->reference }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <div class="field" style="margin-top:10px;">
                    <label>For which project</label>
                    <select name="project_id">
                        <option value="">The store itself</option>
                        @foreach ($projects as $p)
                            <option value="{{ $p->id }}">{{ $p->title }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="field" style="margin-top:10px;">
                    <label>Note</label>
                    <input type="text" name="note" maxlength="1000" placeholder="Who drew it and what for.">
                </div>
                <button class="btn btn-lime btn-block" type="submit" style="margin-top:12px;">Issue it out</button>
            </form>
        </div>

        @if ($item->isLow() || $item->isEmpty())
            <div class="card">
                <h3>Reorder from this card</h3>
                <p class="hint">A card that has run down is not deleted and not typed over — it raises a purchase
                    request on the same register every other request walks.</p>
                <form method="POST" action="{{ route('admin.inventory.reorder', $item) }}" class="fee-form"
                      data-confirm="Raise a purchase request from {{ $item->sku }}?" data-title="Reorder">
                    @csrf
                    <div class="form-grid-2">
                        <div class="field">
                            <label>How much is needed <span class="req">*</span></label>
                            <input type="number" name="quantity" required min="0.01" step="1"
                                   value="{{ max(1, (int) ceil((float) $item->reorder_level * 2 - (float) $item->qty_on_hand)) }}">
                        </div>
                        <div class="field">
                            <label>Needed by</label>
                            <input type="date" name="needed_by">
                        </div>
                    </div>
                    <div class="field" style="margin-top:10px;">
                        <label>Why <span class="req">*</span></label>
                        <input type="text" name="justification" required maxlength="1000"
                               placeholder="e.g. Two cohorts start in November and the store holds 4 irons for 26 trainees.">
                    </div>
                    <button class="btn btn-lime btn-block" type="submit" style="margin-top:12px;">Raise the purchase request</button>
                </form>
            </div>
        @endif

        <div class="card">
            <h3>Movements on this card</h3>
            <table class="data">
                <thead><tr><th>Date</th><th>Movement</th><th>Value</th><th>Why</th><th>For</th><th>Recorded by</th></tr></thead>
                <tbody>
                    @forelse ($item->movements as $m)
                        <tr>
                            <td>{{ $m->moved_on->format('d M Y') }}</td>
                            <td class="{{ $m->direction === 'in' ? 'flag-good' : 'flag-bad' }}">
                                <b>{{ $m->quantityLabel() }}</b>
                            </td>
                            <td>{{ $m->valueLabel() }}</td>
                            <td>{{ $m->reasonLabel() }}
                                @if ($m->note)<div class="hint">{{ $m->note }}</div>@endif</td>
                            <td><span class="hint">{{ $m->forLabel() }}</span></td>
                            <td><span class="hint">{{ $m->recorder->exists ? $m->recorder->name : 'the system' }}</span></td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="hint" style="text-align:center;padding:16px;">
                            Nothing has moved on this card yet.
                        </td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="card">
            <h3>Purchase requests raised for this item</h3>
            @forelse ($item->requests as $r)
                <div class="row-line">
                    <a href="{{ route('admin.procurement.show', $r) }}"><b>{{ $r->reference }}</b></a>
                    <span class="hint">{{ $r->quantityLabel() }} · {{ $r->costLabel() }} · {{ $r->chargeLabel() }}</span>
                    <span class="status {{ $r->isClosed() ? ($r->status === 'paid' ? 'active' : 'closed') : 'pending_review' }}">
                        {{ $r->statusLabel() }}
                    </span>
                </div>
            @empty
                <p class="hint">No request has been raised against this card yet.</p>
            @endforelse
        </div>
    </div>
</div>

<p class="hint">A movement is never edited away: if a count was wrong it is corrected with an opposite movement on
    the same card, so both the error and the fix stay visible.</p>
@endsection
