@extends('layouts.admin')
@section('title', 'Inventory')

@section('content')
@include('partials.errors')
@php use App\Models\InventoryItem; use App\Models\StockMovement; use App\Support\Finance; @endphp
<p class="back"><a href="{{ route('admin.department', 'finance') }}">&larr; Finance, Procurement &amp; Inventory</a></p>
<h1>Stock register</h1>
<p class="sub page-lede">
    One card per item (§14.5): SKU, description, unit, quantity on hand, the level it reorders at, where it sits and
    what it cost. <b>The quantity is never typed in</b> — it is the sum of the movements on the card, so the store can
    always be explained back to a purchase order that brought goods in, or a project or cohort that drew them out.
    A card that runs empty is reordered, not deleted.
</p>

<div class="stat-row">
    <div class="stat"><small>Cards on the register</small><b>{{ $totals['cards'] }}</b></div>
    <div class="stat"><small>Value on the shelves</small><b>{{ Finance::ksh($totals['value']) }}</b></div>
    <div class="stat"><small>At or below reorder</small><b class="flag-bad">{{ $totals['low'] }}</b></div>
    <div class="stat"><small>Empty</small><b class="flag-bad">{{ $totals['empty'] }}</b></div>
    <div class="stat"><small>Purchases already open</small><b>{{ $requests }}</b></div>
</div>

<div class="tabs">
    <a class="tab {{ $category === 'all' && $flag === 'all' ? 'on' : '' }}"
       href="{{ route('admin.inventory.index') }}">Every card<span class="n">{{ $totals['cards'] }}</span></a>
    @foreach ($categories as $key => $label)
        <a class="tab {{ $category === $key ? 'on' : '' }}"
           href="{{ route('admin.inventory.index', ['cat' => $key, 'f' => $flag, 'q' => $search]) }}">
            {{ $label }}<span class="n"></span>
        </a>
    @endforeach
    <a class="tab {{ $flag === 'low' ? 'on' : '' }}"
       href="{{ route('admin.inventory.index', ['cat' => $category, 'f' => 'low', 'q' => $search]) }}">
        Needs reordering<span class="n">{{ $low }}</span></a>
    <a class="tab {{ $flag === 'empty' ? 'on' : '' }}"
       href="{{ route('admin.inventory.index', ['cat' => $category, 'f' => 'empty', 'q' => $search]) }}">
        Empty<span class="n">{{ $empties }}</span></a>
</div>

<form method="GET" action="{{ route('admin.inventory.index') }}" class="filter-bar">
    <input type="hidden" name="cat" value="{{ $category }}">
    <input type="hidden" name="f" value="{{ $flag }}">
    <input type="search" name="q" value="{{ $search }}" placeholder="Search SKU, item or where it is kept" aria-label="Search SKU, item or where it is kept">
    <button class="btn btn-sm btn-outline" type="submit">Filter</button>
    @if ($search !== '')
        <a class="btn btn-sm btn-ghost"
           href="{{ route('admin.inventory.index', ['cat' => $category, 'f' => $flag]) }}">Clear</a>
    @endif
</form>

<table class="data">
    <thead>
        <tr>
            <th>SKU</th><th>Item</th><th>Category</th><th>On hand</th><th>Reorder at</th>
            <th>Unit cost</th><th>Value</th><th>Kept at</th><th>Standing</th><th></th>
        </tr>
    </thead>
    <tbody>
        @forelse ($items as $i)
            <tr>
                <td><b>{{ $i->sku }}</b></td>
                <td>{{ $i->name }}
                    @if ($i->description)<div class="hint">{{ $i->description }}</div>@endif
                </td>
                <td><span class="tag">{{ InventoryItem::categoryLabel($i->category) }}</span></td>
                <td class="{{ $i->isEmpty() ? 'flag-bad' : '' }}"><b>{{ $i->quantityLabel() }}</b></td>
                <td><span class="hint">{{ $i->reorderLabel() }}</span></td>
                <td>{{ $i->unitCostLabel() }}</td>
                <td>{{ $i->valueLabel() }}</td>
                <td><span class="hint">{{ $i->placeLabel() }}</span></td>
                <td>
                    @if ($i->isEmpty())
                        <span class="status closed">Out of stock</span>
                    @elseif ($i->isLow())
                        <span class="status pending_review">Reorder</span>
                    @else
                        <span class="status active">In stock</span>
                    @endif
                    <div class="hint">{{ $i->movements->count() }} movement(s)</div>
                </td>
                <td class="actions"><a class="btn btn-sm btn-outline"
                    href="{{ route('admin.inventory.show', $i) }}">Open card</a></td>
            </tr>
        @empty
            <tr><td colspan="10" class="empty-cell">
                No card on this filter. Goods arrive on a card themselves when a purchase order is received
                (§14.4 step 6) — a card added here is for stock the store already holds.
            </td></tr>
        @endforelse
    </tbody>
</table>

<div class="card">
    <h3>Open a stock card</h3>
    <p class="hint">The SKU is issued from the category so the shelf label and the register always agree. What is
        already on the shelf is entered once here as an opening movement — every change after that goes through a
        movement.</p>
    <form method="POST" action="{{ route('admin.inventory.store') }}" class="fee-form"
          data-confirm="Open this card on the stock register?" data-title="New stock card">
        @csrf
        <div class="form-grid-2">
            <div class="field">
                <label>Item <span class="req">*</span></label>
                <input type="text" name="name" required maxlength="190"
                       placeholder="e.g. CAT6 patch cable, 2 m — or: soldering iron, 60 W">
            </div>
            <div class="field">
                <label>Category <span class="req">*</span></label>
                <select name="category" required>
                    @foreach ($categories as $key => $label)
                        <option value="{{ $key }}">{{ $label }}</option>
                    @endforeach
                </select>
            </div>
        </div>
        <div class="form-grid-2" style="margin-top:10px;">
            <div class="field">
                <label>Unit <span class="req">*</span></label>
                <select name="unit" required>
                    @foreach ($units as $key => $label)
                        <option value="{{ $key }}">{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="field">
                <label>Reorder level <span class="req">*</span></label>
                <input type="number" name="reorder_level" required min="0" step="0.01"
                       placeholder="The level that raises the flag.">
            </div>
        </div>
        <div class="form-grid-2" style="margin-top:10px;">
            <div class="field">
                <label>Unit cost (KSH) <span class="req">*</span></label>
                <input type="number" name="unit_cost_ksh" required min="0" step="0.01">
            </div>
            <div class="field">
                <label>Already on the shelf</label>
                <input type="number" name="qty_on_hand" min="0" step="0.01" placeholder="Opening balance, if any.">
            </div>
        </div>
        <div class="field" style="margin-top:10px;">
            <label>Kept at <span class="req">*</span></label>
            <input type="text" name="location" required maxlength="190"
                   placeholder="e.g. Kisumu centre store, shelf 2 — or the project it sits on.">
        </div>
        <div class="field" style="margin-top:10px;">
            <label>Description</label>
            <input type="text" name="description" maxlength="1000">
        </div>
        <button class="btn btn-lime btn-block" type="submit" style="margin-top:12px;">Open the card</button>
    </form>
</div>

<p class="hint">Drawing materials for a project or a cohort is recorded on the card itself — an issue out posts its
    value on the <a href="{{ route('admin.ledger.index', ['f' => 'expense', 'kind' => 'materials_issued']) }}">money
    register</a> against {{ StockMovement::REASONS['project_issue'] }} (§14.3, §14.5).</p>
@endsection
