@extends('layouts.admin')
@section('title', 'Purchases')

@section('content')
@include('partials.errors')
@php use App\Models\PurchaseRequest; use App\Support\Finance; @endphp
<p class="back"><a href="{{ route('admin.department', 'finance') }}">&larr; Finance, Procurement &amp; Inventory</a></p>
<h1>Purchases</h1>
<p class="sub" style="color:var(--muted);margin:6px 0 16px;">
    One row per purchase request, walked through the eight steps of §14.4: <b>an E-Fundi on a site raises it</b> from
    their portal, or the office raises it — a CTP never does, it mobilises the community and the office buys on its
    behalf — then the Programme Manager decides, the Procurement Officer
    attaches quotations and sends a purchase order, the storekeeper signs the delivery note and puts the goods on a
    stock card, the invoice is matched and the Finance Officer pays. A request is never deleted or raised twice; a
    rejected one stays here with its reason.
</p>

<div class="stat-row">
    <div class="stat"><small>Open requests</small><b>{{ $totals['awaiting'] + $totals['to_order'] }}</b></div>
    <div class="stat"><small>Waiting for a decision</small><b>{{ $totals['awaiting'] }}</b></div>
    <div class="stat"><small>Waiting for a purchase order</small><b>{{ $totals['to_order'] }}</b></div>
    <div class="stat"><small>Value still on the table</small><b>{{ Finance::ksh($totals['raised']) }}</b></div>
    <div class="stat"><small>Owed to suppliers</small><b>{{ Finance::ksh($totals['unpaid']) }}</b></div>
</div>

<div class="tabs">
    @foreach ($filters as $key => $label)
        <a class="tab {{ $status === $key ? 'on' : '' }}"
           href="{{ route('admin.procurement.index', ['f' => $key, 'budget' => $budget, 'q' => $search]) }}">
            {{ $label }}<span class="n">{{ $counts[$key] ?? 0 }}</span>
        </a>
    @endforeach
</div>

<form method="GET" action="{{ route('admin.procurement.index') }}" class="filter-bar">
    <input type="hidden" name="f" value="{{ $status }}">
    <input type="search" name="q" value="{{ $search }}" placeholder="Search reference, item or justification">
    <button class="btn btn-sm btn-outline" type="submit">Filter</button>
    @if ($budget !== 'all' || $search !== '')
        <a class="btn btn-sm btn-ghost" href="{{ route('admin.procurement.index', ['f' => $status]) }}">Clear</a>
    @endif
    <div style="width:100%;">
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
                    <span class="hint">step {{ $pr->stepIndex() + 1 }} of 7</span>
                    @if ($pr->orders->isNotEmpty())
                        <div class="hint">{{ $pr->orders->pluck('reference')->join(', ') }}</div>
                    @endif
                </td>
                <td>
                    <span class="status {{ $pr->isClosed() ? ($pr->status === 'paid' ? 'active' : 'closed') : 'pending_review' }}">
                        {{ $pr->statusLabel() }}
                    </span>
                </td>
                <td style="text-align:right;">
                    <a class="btn btn-sm btn-outline" href="{{ route('admin.procurement.show', $pr) }}">Open</a>
                </td>
            </tr>
        @empty
            <tr><td colspan="9" style="text-align:center;color:var(--muted);padding:22px;">
                Nothing on this filter. Requests raised by an E-Fundi arrive here on the same register as
                the ones typed in by the office — <b>Purchase request</b> in their own portal.
            </td></tr>
        @endforelse
    </tbody>
</table>

{{ $requests->links() }}

<div class="card">
    <h3>Raise a purchase request (§14.4 step 1)</h3>
    <p class="hint">This is the same form an E-Fundi uses in their portal — and the form the office uses on behalf of
        a CTP, which never raises one itself. Say why the programme needs it — the decision is
        made on the justification, not the item name — and pick the budget line it is charged to.</p>
    <form method="POST" action="{{ route('admin.procurement.store') }}" class="fee-form"
          data-confirm="Raise this purchase request?" data-title="Raise request">
        @csrf
        <div class="form-grid-2">
            <div class="field">
                <label>What is needed <span class="req">*</span></label>
                <input type="text" name="item_name" required maxlength="190"
                       placeholder="e.g. 13-pin sockets, 6-way extension leads, CAT6 cable">
            </div>
            <div class="field">
                <label>Stock card it restocks</label>
                <select name="inventory_item_id">
                    <option value="">Not on the stock register yet</option>
                    @foreach ($items as $i)
                        <option value="{{ $i->id }}">{{ $i->sku }} — {{ $i->name }} ({{ \App\Models\InventoryItem::unitLabel($i->unit) }})</option>
                    @endforeach
                </select>
            </div>
        </div>
        <div class="form-grid-2" style="margin-top:10px;">
            <div class="field">
                <label>Quantity <span class="req">*</span></label>
                <input type="number" name="quantity" required min="1" step="1">
            </div>
            <div class="field">
                <label>Unit <span class="req">*</span></label>
                <select name="unit" required>
                    @foreach ($units as $key => $label)
                        <option value="{{ $key }}">{{ $label }}</option>
                    @endforeach
                </select>
            </div>
        </div>
        <div class="form-grid-2" style="margin-top:10px;">
            <div class="field">
                <label>What it will cost (KSH)</label>
                <input type="number" name="estimated_cost_ksh" min="0" step="0.01" placeholder="An honest estimate.">
            </div>
            <div class="field">
                <label>Charge to budget <span class="req">*</span></label>
                <select name="budget_category" required>
                    @foreach ($categories as $key => $label)
                        <option value="{{ $key }}">{{ $label }}</option>
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
                        <option value="{{ $p->id }}">{{ $p->title }}</option>
                    @endforeach
                </select>
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
        <div class="form-grid-2" style="margin-top:10px;">
            <div class="field">
                <label>Needed by</label>
                <input type="date" name="needed_by">
            </div>
            <div class="field">
                <label>County</label>
                <input type="text" name="county" maxlength="100" placeholder="Where the goods are going.">
            </div>
        </div>
        <div class="field" style="margin-top:10px;">
            <label>Description</label>
            <input type="text" name="description" maxlength="1000" placeholder="Spec, size or the make that must be matched.">
        </div>
        <div class="field" style="margin-top:10px;">
            <label>Why the programme needs it <span class="req">*</span></label>
            <textarea name="justification" required rows="3" maxlength="1000"
                      placeholder="e.g. The Kisumu lab has 24 trainees and 9 working sockets; the wiring cohort cannot finish the practical without them."></textarea>
        </div>
        <button class="btn btn-lime btn-block" type="submit" style="margin-top:12px;">Raise the request</button>
    </form>
</div>
@endsection
