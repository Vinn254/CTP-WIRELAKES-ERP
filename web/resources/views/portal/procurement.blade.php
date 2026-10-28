@extends('layouts.member')
@section('title', 'Purchase request')

@php
    use App\Models\PurchaseRequest;
    use App\Support\Finance;
@endphp

@section('content')
<div class="page-intro">
    <p class="sub">The programme buys materials against a <b>purchase request</b>, and this screen is where an
        E-Fundi on a site raises one — a CTP does not, it mobilises the community and the office buys on its behalf. Say what is needed, how much, what it will cost
        and <b>why the programme needs it</b>: that justification is what the decision is made on, not the item name.
        The Programme Manager approves it, the Procurement Officer gets quotations and sends the order, the
        storekeeper receives the goods and the Finance Officer pays. What you see here is where your own request has
        actually got to.</p>
    <div class="member-stats">
        <div class="stat"><small>Your requests</small><b>{{ $totals['lines'] }}</b></div>
        <div class="stat"><small>Waiting for a decision</small><b>{{ $totals['waiting'] }}</b></div>
        <div class="stat"><small>Being bought</small><b>{{ $totals['moving'] }}</b></div>
        <div class="stat"><small>Delivered and paid</small><b>{{ $totals['filled'] }}</b></div>
    </div>
</div>

<div class="head-acts" style="margin-bottom:18px;">
    <button class="btn btn-sm btn-lime" type="button" data-sheet="add-request">+ Raise a purchase request</button>
</div>

@include('partials.sheet-start', ['name' => 'add-request', 'title' => 'Raise a purchase request',
    'sub' => 'One request per item or batch, charged to the budget the money really comes out of.'])
    <form method="POST" action="{{ route('portal.procurement.store') }}" class="fee-form"
          data-confirm="Send this request to the programme office?" data-title="Raise a request">
        @csrf
        <input type="hidden" name="_sheet" value="add-request">
        <div class="form-grid-3">
            <div class="field">
                <label>What is needed <span class="req">*</span></label>
                <input type="text" name="item_name" required maxlength="190" value="{{ old('item_name') }}"
                       placeholder="e.g. 13-pin wall sockets, 32A">
            </div>
            <div class="field">
                <label>Quantity <span class="req">*</span></label>
                <input type="number" name="quantity" required min="1" max="100000" value="{{ old('quantity', 1) }}">
            </div>
            <div class="field">
                <label>Unit <span class="req">*</span></label>
                <select name="unit" required>
                    @foreach ($units as $key => $label)
                        <option value="{{ $key }}" @selected(old('unit') === $key)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="field">
                <label>What it will cost (KSH)</label>
                <input type="number" name="estimated_cost_ksh" min="0" step="0.01" value="{{ old('estimated_cost_ksh') }}"
                       placeholder="An honest estimate — a quote if you have one.">
            </div>
            <div class="field">
                <label>Charge to budget <span class="req">*</span></label>
                <select name="budget_category" required>
                    @foreach ($categories as $key => $label)
                        <option value="{{ $key }}" @selected(old('budget_category') === $key)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="field">
                <label>Needed by</label>
                <input type="date" name="needed_by" value="{{ old('needed_by') }}">
            </div>
            <div class="field">
                <label>For which project of yours</label>
                <select name="project_id">
                    <option value="">General programme need</option>
                    @foreach ($awarded as $p)
                        <option value="{{ $p->id }}">{{ $p->title }}</option>
                    @endforeach
                </select>
            </div>
            <div class="field">
                <label>County the goods go to</label>
                <input type="text" name="county" maxlength="100" value="{{ old('county', $member->county) }}">
            </div>
            <div class="field">
                <label>Spec or make to match</label>
                <input type="text" name="description" maxlength="1000" value="{{ old('description') }}">
            </div>
        </div>
        <div class="field" style="margin-top:12px;">
            <label>Why the programme needs it <span class="req">*</span></label>
            <textarea name="justification" rows="3" required minlength="15" maxlength="1000"
                      placeholder="e.g. The Nyanda attachment has 8 trainees and 2 working sockets; the wiring job stalls without them.">{{ old('justification') }}</textarea>
            <div class="help">A short reason in plain words — the decision and the quotation are both made on this.</div>
        </div>
        <div class="sheet-actions">
            <button class="btn btn-sm btn-ghost" type="button" data-close-sheet>Cancel</button>
            <button class="btn btn-sm btn-lime" type="submit">Send the request</button>
        </div>
    </form>
@include('partials.sheet-end')

<div class="card">
    <div class="section-head"><h3>What happens after you send it</h3></div>
    <ol class="steps">
        @foreach ($path as $step)
            <li>
                <b>{{ $step['action'] }}</b>
                <div class="hint">{{ $step['actor'] }}</div>
            </li>
        @endforeach
    </ol>
</div>

<div class="card">
    <div class="section-head"><h3>Your requests ({{ $mine->count() }})</h3></div>
    @if ($mine->isEmpty())
        <p class="help">Nothing raised under your name yet. The first request is usually the one everyone is
            working around — raise it rather than buying it out of pocket.</p>
    @else
        <table class="data">
            <thead>
                <tr><th>Request</th><th>Item</th><th>Qty</th><th>Estimated</th><th>Charge to</th>
                    <th>Step</th><th>Answer</th></tr>
            </thead>
            <tbody>
                @foreach ($mine as $pr)
                    <tr>
                        <td><b>{{ $pr->reference }}</b>
                            @if ($pr->needed_by)<div class="hint">needed by {{ $pr->needed_by->format('d M Y') }}</div>@endif
                        </td>
                        <td>{{ $pr->item_name }}
                            <div class="hint">{{ $pr->chargeLabel() }}</div></td>
                        <td>{{ $pr->quantityLabel() }}</td>
                        <td>{{ $pr->costLabel() }}</td>
                        <td><span class="hint">{{ $pr->budgetLabel() }}</span></td>
                        <td>
                            <span class="hint">step {{ $pr->stepIndex() + 1 }} of 7</span>
                            @if ($pr->orders->isNotEmpty())
                                <div class="hint">{{ $pr->orders->pluck('reference')->join(', ') }}</div>
                            @endif
                            @if ($pr->item->exists)
                                <div class="hint">card {{ $pr->item->sku }} · {{ $pr->item->quantityLabel() }} on hand</div>
                            @endif
                        </td>
                        <td>
                            <span class="status {{ $pr->isClosed() ? ($pr->status === 'paid' ? 'active' : 'closed') : 'pending_review' }}">
                                {{ $pr->statusLabel() }}
                            </span>
                        </td>
                    </tr>
                    @if ($pr->rejection_reason || $pr->decision_note)
                        <tr><td colspan="7" class="hint">
                            <b>{{ $pr->rejection_reason ? 'The office said no:' : 'Note on the decision:' }}</b>
                            {{ $pr->rejection_reason ?: $pr->decision_note }}
                            @if ($pr->decided_on)<span> — {{ $pr->decided_on->format('d M Y') }}</span>@endif
                        </td></tr>
                    @endif
                    @if ($pr->quotations->isNotEmpty())
                        @php $cheapest = $pr->quotations->sortBy('amount_ksh')->first(); @endphp
                        <tr><td colspan="7" class="hint">
                            Quoted at {{ Finance::ksh((float) $cheapest->amount_ksh) }} by
                            {{ $cheapest->supplier->name ?? 'an approved supplier' }}
                            ({{ $pr->quotations->count() }} quotation(s) on file) — the price of record once the
                            purchase order goes out.
                        </td></tr>
                    @endif
                @endforeach
            </tbody>
        </table>
    @endif
</div>

<p class="hint">You cannot approve, quote, receive or pay a request from here — those steps are the office's, and
    each one is signed by a named officer. If a request was refused and you can see the gap, raise a new one with the
    correction: the old row and its reason stay on the register.</p>
@endsection
