@extends('layouts.admin')
@section('title', 'Purchases')

@section('content')
@include('partials.errors')
@php use App\Models\FinanceEntry; use App\Models\InventoryItem; use App\Models\PurchaseRequest; use App\Support\Finance; @endphp
<div class="head-row">
    <div>
        <h1>Purchases</h1>
        <p class="page-lede">Purchase requests moving through approval, ordering, delivery and payment.</p>
    </div>
    <div class="head-acts">
        <button class="btn btn-sm btn-lime" type="button" data-sheet="raise">+ Raise a purchase request</button>
        <button class="btn btn-sm btn-outline" type="button" data-sheet="hand">+ Record a purchase by hand</button>
    </div>
</div>

<div class="stat-row">
    <div class="stat"><small>Open requests</small><b>{{ $totals['awaiting'] + $totals['to_order'] }}</b></div>
    <div class="stat"><small>Waiting for a decision</small><b>{{ $totals['awaiting'] }}</b></div>
    <div class="stat"><small>Waiting for a purchase order</small><b>{{ $totals['to_order'] }}</b></div>
    <div class="stat"><small>Value still on the table</small><b>{{ Finance::ksh($totals['raised']) }}</b></div>
    <div class="stat"><small>Owed to suppliers</small><b>{{ Finance::ksh($totals['unpaid']) }}</b></div>
</div>

<form method="GET" action="{{ route('admin.procurement.index') }}" class="filter-bar">
    <label class="field">
        <span>Request status</span>
        <select name="f" onchange="this.form.submit()">
            @foreach ($filters as $key => $label)
                <option value="{{ $key }}" @selected($status === $key)>{{ $label }} ({{ $counts[$key] ?? 0 }})</option>
            @endforeach
        </select>
    </label>
    <input type="search" name="q" value="{{ $search }}" placeholder="Search reference, item or justification" aria-label="Search reference, item or justification">
    <button class="btn btn-sm btn-outline" type="submit">Filter</button>
    @if ($budget !== 'all' || $search !== '')
        <a class="btn btn-sm btn-ghost" href="{{ route('admin.procurement.index', ['f' => $status]) }}">Clear</a>
    @endif
    <div class="grow">
        <label for="budget">Budget line</label>
        <select name="budget" id="budget">
            <option value="all">Any budget line</option>
            @foreach ($categories as $key => $label)
                <option value="{{ $key }}" @selected($budget === $key)>{{ $label }}</option>
            @endforeach
        </select>
    </div>
</form>

<table class="data">
    <thead>
        <tr>
            <th>Request</th><th>What is needed</th><th>Quantity</th><th>Estimated</th><th>Charge to</th>
            <th>Raised by</th><th>On the path</th><th>Status</th><th></th>
        </tr>
    </thead>
    <tbody>
        @forelse ($requests as $pr)
            <tr>
                <td><b>{{ $pr->reference }}</b>
                    @if ($pr->needed_by)<div class="hint">needed by {{ $pr->needed_by->format('d M Y') }}</div>@endif
                </td>
                <td>{{ $pr->item_name }}
                    <div class="hint">{{ $pr->chargeLabel() }}</div>
                </td>
                <td>{{ $pr->quantityLabel() }}</td>
                <td>{{ $pr->costLabel() }}</td>
                <td><span class="hint">{{ $pr->budgetLabel() }}</span></td>
                <td><span class="hint">{{ $pr->raiserLabel() }}</span></td>
                <td>
                    @if ($pr->isHandRecorded())
                        <span class="hint">Recorded by hand</span>
                        <div class="hint">{{ $pr->supplierLabel() }}</div>
                    @else
                        <span class="hint">step {{ $pr->stepIndex() + 1 }} of 7</span>
                        @if ($pr->orders->isNotEmpty())
                            <div class="hint">{{ $pr->orders->pluck('reference')->join(', ') }}</div>
                        @endif
                    @endif
                </td>
                <td>
                    <span class="status {{ $pr->isClosed() ? ($pr->status === 'paid' ? 'active' : 'closed') : 'pending_review' }}">
                        {{ $pr->statusLabel() }}
                    </span>
                </td>
                <td class="actions">
                    <a class="btn btn-sm btn-outline" href="{{ route('admin.procurement.show', $pr) }}">Open</a>
                </td>
            </tr>
        @empty
            <tr><td colspan="9" class="empty-cell">
                Nothing on this filter. Requests raised by an E-Fundi arrive here on the same register as
                the ones typed in by the office — <b>Purchase request</b> in their own portal.
            </td></tr>
        @endforelse
    </tbody>
</table>

{{ $requests->links() }}

@include('partials.sheet-start', ['name' => 'raise', 'title' => 'Raise a purchase request',
    'sub' => 'The form an E-Fundi uses in their portal, and the one the office uses for a CTP.'])
    <form method="POST" action="{{ route('admin.procurement.store') }}" class="fee-form"
          data-confirm="Raise this purchase request?" data-title="Raise request">
        @csrf
        <input type="hidden" name="_sheet" value="raise">
        <div class="form-grid-2">
            <div class="field">
                <label>What is needed <span class="req">*</span></label>
                <input type="text" name="item_name" required maxlength="190" value="{{ old('item_name') }}"
                       placeholder="e.g. 13-pin sockets, 6-way extension leads, CAT6 cable">
            </div>
            <div class="field">
                <label>Stock card it restocks</label>
                <select name="inventory_item_id">
                    <option value="">Not on the stock register yet</option>
                    @foreach ($items as $i)
                        <option value="{{ $i->id }}" @selected((int) old('inventory_item_id') === $i->id)>{{ $i->sku }} — {{ $i->name }} ({{ InventoryItem::unitLabel($i->unit) }})</option>
                    @endforeach
                </select>
            </div>
        </div>
        <div class="form-grid-2" style="margin-top:10px;">
            <div class="field">
                <label>Quantity <span class="req">*</span></label>
                <input type="number" name="quantity" required min="1" step="1" value="{{ old('quantity') }}">
            </div>
            <div class="field">
                <label>Unit <span class="req">*</span></label>
                <select name="unit" required>
                    @foreach ($units as $key => $label)
                        <option value="{{ $key }}" @selected(old('unit') === $key)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
        </div>
        <div class="form-grid-2" style="margin-top:10px;">
            <div class="field">
                <label>What it will cost (KSH)</label>
                <input type="number" name="estimated_cost_ksh" min="0" step="0.01" value="{{ old('estimated_cost_ksh') }}" placeholder="An honest estimate.">
            </div>
            <div class="field">
                <label>Charge to budget <span class="req">*</span></label>
                <select name="budget_category" required>
                    @foreach ($categories as $key => $label)
                        <option value="{{ $key }}" @selected(old('budget_category') === $key)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
        </div>
        <div class="form-grid-2" style="margin-top:10px;">
            <div class="field">
                <label>For which project</label>
                <select name="project_id">
                    <option value="">General programme need</option>
                    @foreach ($projects as $p)
                        <option value="{{ $p->id }}" @selected((int) old('project_id') === $p->id)>{{ $p->title }}</option>
                    @endforeach
                </select>
            </div>
            <div class="field">
                <label>For which cohort</label>
                <select name="cohort_id">
                    <option value="">None</option>
                    @foreach ($cohorts as $c)
                        <option value="{{ $c->id }}" @selected((int) old('cohort_id') === $c->id)>{{ $c->reference }}</option>
                    @endforeach
                </select>
            </div>
        </div>
        <div class="form-grid-2" style="margin-top:10px;">
            <div class="field">
                <label>Needed by</label>
                <input type="date" name="needed_by" value="{{ old('needed_by') }}">
            </div>
            <div class="field">
                <label>County</label>
                <input type="text" name="county" maxlength="100" value="{{ old('county') }}" placeholder="Where the goods are going.">
            </div>
        </div>
        <div class="field" style="margin-top:10px;">
            <label>Description</label>
            <input type="text" name="description" maxlength="1000" value="{{ old('description') }}" placeholder="Spec, size or the make that must be matched.">
        </div>
        <div class="field" style="margin-top:10px;">
            <label>Why the programme needs it <span class="req">*</span></label>
            <textarea name="justification" required rows="3" maxlength="1000"
                      placeholder="e.g. The Kisumu lab has 24 trainees and 9 working sockets; the wiring cohort cannot finish the practical without them.">{{ old('justification') }}</textarea>
        </div>
        <div class="sheet-actions">
            <button class="btn btn-sm btn-ghost" type="button" data-close-sheet>Cancel</button>
            <button class="btn btn-sm btn-lime" type="submit">Raise the request</button>
        </div>
    </form>
@include('partials.sheet-end')

@include('partials.sheet-start', ['name' => 'hand', 'title' => 'Record a purchase by hand',
    'sub' => 'For a purchase already made outside the system — bought at the counter and paid on a receipt.'])
    @include('partials.mpesa-line')
    <form method="POST" action="{{ route('admin.procurement.hand') }}" class="fee-form"
          data-confirm="Record this purchase as already bought and paid?" data-title="Record by hand">
        @csrf
        <input type="hidden" name="_sheet" value="hand">
        <div class="form-grid-2">
            <div class="field">
                <label>What was bought <span class="req">*</span></label>
                <input type="text" name="item_name" required maxlength="190" value="{{ old('item_name') }}"
                       placeholder="e.g. Cement, 50 kg bags; a replacement router">
            </div>
            <div class="field">
                <label>Supplier as on the receipt <span class="req">*</span></label>
                <input type="text" name="supplier_label" required maxlength="190" value="{{ old('supplier_label') }}"
                       placeholder="e.g. Kisumu Hardware, Naivas Mumbi">
            </div>
        </div>
        <div class="form-grid-2" style="margin-top:10px;">
            <div class="field">
                <label>Quantity <span class="req">*</span></label>
                <input type="number" name="quantity" required min="1" step="1" value="{{ old('quantity') }}">
            </div>
            <div class="field">
                <label>Unit <span class="req">*</span></label>
                <select name="unit" required>
                    @foreach ($units as $key => $label)
                        <option value="{{ $key }}" @selected(old('unit') === $key)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
        </div>
        <div class="form-grid-3" style="margin-top:10px;">
            <div class="field">
                <label>Amount paid (KSH) <span class="req">*</span></label>
                <input type="number" name="paid_amount_ksh" required min="0.01" step="0.01" value="{{ old('paid_amount_ksh') }}">
            </div>
            <div class="field">
                <label>Date paid <span class="req">*</span></label>
                <input type="date" name="paid_on" required value="{{ old('paid_on') }}">
            </div>
            <div class="field">
                <label>Paid by <span class="req">*</span></label>
                <select name="method" required>
                    @foreach (array_diff_key(FinanceEntry::METHODS, ['pending' => '']) as $key => $label)
                        <option value="{{ $key }}" @selected(old('method') === $key)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
        </div>
        <div class="form-grid-2" style="margin-top:10px;">
            <div class="field">
                <label>Charge to budget <span class="req">*</span></label>
                <select name="budget_category" required>
                    @foreach ($categories as $key => $label)
                        <option value="{{ $key }}" @selected(old('budget_category') === $key)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="field">
                <label>For which project</label>
                <select name="project_id">
                    <option value="">General programme need</option>
                    @foreach ($projects as $p)
                        <option value="{{ $p->id }}" @selected((int) old('project_id') === $p->id)>{{ $p->title }}</option>
                    @endforeach
                </select>
            </div>
        </div>
        <div class="form-grid-2" style="margin-top:10px;">
            <div class="field">
                <label>Description</label>
                <input type="text" name="description" maxlength="1000" value="{{ old('description') }}" placeholder="Spec, size or the make that was matched.">
            </div>
            <div class="field">
                <label>County</label>
                <input type="text" name="county" maxlength="100" value="{{ old('county') }}">
            </div>
        </div>
        <div class="field" style="margin-top:10px;">
            <label>What it was for <span class="req">*</span></label>
            <textarea name="justification" required rows="3" maxlength="1000"
                      placeholder="e.g. The store had no tape left; the wiring cohort needed it the same morning. Receipt is attached to the file.">{{ old('justification') }}</textarea>
        </div>
        <p class="help">It lands on the register as already paid, with one line on the money register. The eight steps
            are not run around it, and the receipt stays the paper behind the row.</p>
        <div class="sheet-actions">
            <button class="btn btn-sm btn-ghost" type="button" data-close-sheet>Cancel</button>
            <button class="btn btn-sm btn-lime" type="submit">Record the purchase</button>
        </div>
    </form>
@include('partials.sheet-end')
@endsection
