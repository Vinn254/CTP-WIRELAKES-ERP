@extends('layouts.admin')
@section('title', $pr->reference)

@php
    use App\Models\PurchaseRequest;
    use App\Support\Finance;

    $here = $pr->stepIndex();
    $canDecide = $pr->status === 'submitted';
    $canQuote = in_array($pr->status, ['approved', 'quoted'], true);
    $canOrder = in_array($pr->status, ['quoted', 'ordered'], true);
    $isExec = Finance::isExecutive();
@endphp

@section('content')
@include('partials.errors')
<p class="back"><a href="{{ route('admin.procurement.index') }}">&larr; Purchases</a></p>

<div class="applicant-head card">
    <div class="ah-main">
        <h1>{{ $pr->item_name }}
            <span class="status {{ $pr->isClosed() ? ($pr->status === 'paid' ? 'active' : 'closed') : 'pending_review' }}">
                {{ $pr->statusLabel() }}
            </span>
        </h1>
        <p class="ah-meta">
            <span class="tag">{{ $pr->reference }}</span>
            <span>{{ $pr->quantityLabel() }}</span>
            <span>{{ $pr->costLabel() }}</span>
            <span>charged to {{ $pr->budgetLabel() }}</span>
            <span>Raised {{ $pr->created_at->format('d M Y') }}</span>
            @if ($pr->needed_by)<span>needed by {{ $pr->needed_by->format('d M Y') }}</span>@endif
        </p>
    </div>
    <div class="ah-contact">
        <span class="hint">Raised by</span>
        <b>{{ $pr->raiserLabel() }}</b>
    </div>
</div>

@if ($pr->isHandRecorded())
    <div class="card gaps">
        <b>Recorded by hand.</b> Bought from {{ $pr->supplierLabel() }} and paid on
        {{ $pr->recorded_by_hand_on->format('d M Y') }} — the buy happened at the counter, outside the system, so this
        row has no quotations and no purchase order behind it. The receipt on the file is the paper behind it.
    </div>
@endif

@if ($pr->rejection_reason)
    <div class="card gaps">
        <b>Rejected:</b> {{ $pr->rejection_reason }}
        <span class="hint">— the request stays on the register with the decision. {{ $pr->decided_on?->format('d M Y') }}
            by {{ $pr->decider->exists ? $pr->decider->name : 'the office' }}.</span>
    </div>
@endif

<div class="grid-2 review-grid">
    <div class="stack">
        <div class="card">
            <h3>The purchase path</h3>
            <p class="hint">Every step names the officer who owns it. A step only opens once the row is actually on
                it, so nothing is paid before it is received and nothing is ordered without a quotation on file.</p>
            <ol class="steps">
                @foreach (PurchaseRequest::PATH as $i => $step)
                    <li class="{{ $pr->status === $step['status'] ? 'current' : ($i < $here ? 'done' : '') }}">
                        <b>{{ $step['action'] }}</b>
                        <div class="hint">{{ $step['actor'] }}</div>
                        @if ($pr->status === $step['status'])<span class="tag gold">This request is here</span>@endif
                    </li>
                @endforeach
            </ol>
        </div>

        <div class="card">
            <h3>What was asked for</h3>
            <dl class="kv">
                <dt>Item</dt><dd>{{ $pr->item_name }}</dd>
                <dt>Description</dt><dd>{{ $pr->description ?: '—' }}</dd>
                <dt>Quantity</dt><dd>{{ $pr->quantityLabel() }}</dd>
                <dt>Estimated cost</dt><dd>{{ $pr->costLabel() }}</dd>
                <dt>Charged to</dt><dd>{{ $pr->budgetLabel() }}</dd>
                <dt>For</dt><dd>{{ $pr->chargeLabel() }}</dd>
                @if ($pr->county)<dt>Going to</dt><dd>{{ $pr->county }}</dd>@endif
                <dt>Stock card</dt>
                <dd>
                    @if ($pr->item->exists)
                        <a href="{{ route('admin.inventory.show', $pr->item) }}">{{ $pr->item->sku }} — {{ $pr->item->name }}</a>
                        <span class="hint">{{ $pr->item->quantityLabel() }} on hand at {{ $pr->item->placeLabel() }}</span>
                    @else
                        <span class="hint">Not tied to a card — receiving the goods opens one.</span>
                    @endif
                </dd>
                <dt>Why it is needed</dt><dd>{{ $pr->justification }}</dd>
                @if ($pr->decision_note && $pr->status !== 'rejected')
                    <dt>Note on the decision</dt><dd>{{ $pr->decision_note }}</dd>
                @endif
            </dl>
        </div>

        @if ($canDecide)
            <div class="card">
                <h3>Step 2 — the Programme Manager decides</h3>
                <p class="hint">A rejection is written on the row with its reason and the request is not deleted.</p>
                <form method="POST" action="{{ route('admin.procurement.decide', $pr) }}" class="fee-form"
                      data-confirm="Record this decision on {{ $pr->reference }}?" data-title="Decision">
                    @csrf
                    <div class="form-grid-2">
                        <div class="field">
                            <label>Decision <span class="req">*</span></label>
                            <select name="approve" required>
                                <option value="yes">Approved — go and get quotations</option>
                                <option value="no">Rejected — with a reason</option>
                            </select>
                        </div>
                        <div class="field">
                            <label>Note / reason <span class="req">*</span> when rejecting</label>
                            <input type="text" name="note" maxlength="1000"
                                   placeholder="e.g. Re-quote after the supplier list is refreshed; this estimate is 40% over.">
                        </div>
                    </div>
                    <button class="btn btn-lime btn-block" type="submit" style="margin-top:12px;">Record the decision</button>
                </form>
            </div>
        @endif
    </div>

    <div class="stack">
        <div class="card">
            <h3>Step 3 — quotations from approved suppliers</h3>
            <p class="hint">Prices are attached as evidence, so a quotation is never edited into another figure: a
                wrong one is removed and the supplier quotes again. Exactly one is the price of record.</p>

            <table class="data">
                <thead><tr><th>Supplier</th><th>Price</th><th>Quote ref</th><th>On</th><th></th></tr></thead>
                <tbody>
                    @forelse ($pr->quotations as $q)
                        <tr>
                            <td>{{ $q->supplier->name ?? '—' }}
                                @if ($q->is_selected)<div><span class="status active">Price of record</span></div>@endif
                                @if ($q->note)<div class="hint">{{ $q->note }}</div>@endif
                            </td>
                            <td class="{{ $best && $best->id === $q->id ? 'flag-good' : '' }}">{{ $q->amountLabel() }}</td>
                            <td><span class="hint">{{ $q->quotation_ref ?: '—' }}</span></td>
                            <td><span class="hint">{{ $q->quoted_on?->format('d M Y') }}</span></td>
                            <td class="actions">
                                @if ($canQuote && ! $q->is_selected)
                                    <form method="POST" action="{{ route('admin.procurement.quotation.select', $q) }}" class="mini-form">
                                        @csrf
                                        <button class="btn btn-sm btn-outline" type="submit">Buy against this</button>
                                    </form>
                                @endif
                                <a class="btn btn-sm btn-ghost" href="{{ route('admin.procurement.quotation.document', $q) }}"
                                   target="_blank" rel="noopener">Print</a>
                                <a class="btn btn-sm btn-ghost"
                                   href="{{ download_href(route('admin.procurement.quotation.document', $q)) }}">Download</a>
                                @include('partials.sheet-start', ['name' => 'email-quote-'.$q->id,
                                    'title' => 'Send this quotation to the supplier',
                                    'sub' => 'They get the same page the office prints, so the figure cannot be read two ways.'])
                                    <form method="POST" action="{{ route('admin.procurement.quotation.email', $q) }}"
                                          class="fee-form" data-title="Email quotation">
                                        @csrf
                                        <input type="hidden" name="_sheet" value="email-quote-{{ $q->id }}">
                                        <div class="field">
                                            <label>Send to <span class="req">*</span></label>
                                            <input type="email" name="to" required maxlength="190"
                                                   value="{{ old('to', $q->supplier?->email) }}"
                                                   placeholder="the supplier's email address">
                                        </div>
                                        <div class="field" style="margin-top:10px;">
                                            <label>The line above the document</label>
                                            <input type="text" name="note" maxlength="1000"
                                                   placeholder="e.g. Confirm this price holds to 30 November and quote PR-2026-00004 on your invoice.">
                                        </div>
                                        <div class="sheet-actions">
                                            <button class="btn btn-sm btn-ghost" type="button" data-close-sheet>Cancel</button>
                                            <button class="btn btn-sm btn-lime" type="submit">Send the quotation</button>
                                        </div>
                                    </form>
                                @include('partials.sheet-end')
                                <button class="btn btn-sm btn-ghost" type="button"
                                        data-sheet="email-quote-{{ $q->id }}">Email</button>
                                @if ($q->emailed_at)
                                    <div class="hint">sent to {{ $q->sent_to }} {{ $q->emailed_at->format('d M Y') }}</div>
                                @endif
                                @if ($canQuote && $pr->status !== 'paid')
                                    <form method="POST" action="{{ route('admin.procurement.quotation.delete', $q) }}" class="mini-form"
                                          data-confirm="Remove this price from {{ $pr->reference }}?" data-title="Remove quotation">
                                        @csrf @method('DELETE')
                                        <button class="btn btn-sm btn-ghost" type="submit">Remove</button>
                                    </form>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="hint" style="text-align:center; padding:16px;">
                            No quotation on file yet.
                        </td></tr>
                    @endforelse
                </tbody>
            </table>

            @if ($canQuote)
                <form method="POST" action="{{ route('admin.procurement.quote', $pr) }}" class="fee-form"
                      data-confirm="Attach this quotation to {{ $pr->reference }}?" data-title="Attach quotation">
                    @csrf
                    <div class="form-grid-2">
                        <div class="field">
                            <label>Supplier <span class="req">*</span></label>
                            <select name="supplier_id" required>
                                @forelse ($suppliers as $s)
                                    <option value="{{ $s->id }}">{{ $s->name }} · {{ $s->suppliesLabel() }}</option>
                                @empty
                                    <option value="">No approved supplier on the list yet</option>
                                @endforelse
                            </select>
                        </div>
                        <div class="field">
                            <label>Quoted price (KSH) <span class="req">*</span></label>
                            <input type="number" name="amount_ksh" required min="0.01" step="0.01">
                        </div>
                    </div>
                    <div class="form-grid-2" style="margin-top:10px;">
                        <div class="field">
                            <label>Quotation number</label>
                            <input type="text" name="quotation_ref" maxlength="80" placeholder="Their ref, for the file.">
                        </div>
                        <div class="field">
                            <label>Quoted on <span class="req">*</span></label>
                            <input type="date" name="quoted_on" required value="{{ now()->toDateString() }}">
                        </div>
                    </div>
                    <div class="field" style="margin-top:10px;">
                        <label>Note</label>
                        <input type="text" name="note" maxlength="1000" placeholder="Delivery time, validity, taxes.">
                    </div>
                    <button class="btn btn-lime btn-block" type="submit" style="margin-top:12px;">
                        {{ $suppliers->isEmpty() ? 'Add a supplier to the approved list first' : 'Attach the quotation' }}
                    </button>
                </form>
            @else
                <p class="hint">{{ $pr->reference }} is {{ $pr->statusLabel() }} — quotations are attached after the
                    Programme Manager approves the request.</p>
            @endif
        </div>

        @if ($canOrder)
            <div class="card">
                <h3>Step 4 — raise the purchase order</h3>
                <p class="hint">The order names one supplier, the quantity and the agreed unit price. Its total is
                    what the invoice is matched to and what Finance pays.</p>
                <form method="POST" action="{{ route('admin.procurement.order', $pr) }}" class="fee-form"
                      data-confirm="Write a purchase order against {{ $pr->reference }}?" data-title="Raise purchase order">
                    @csrf
                    <div class="field">
                        <label>Supplier <span class="req">*</span></label>
                        <select name="supplier_id" required>
                            @foreach ($suppliers as $s)
                                <option value="{{ $s->id }}" @selected($best && $best->supplier_id === $s->id)>
                                    {{ $s->name }}{{ $best && $best->supplier_id === $s->id ? ' — price of record '.$best->amountLabel() : '' }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="form-grid-2" style="margin-top:10px;">
                        <div class="field">
                            <label>Quantity ordered <span class="req">*</span></label>
                            <input type="number" name="quantity" required min="0.01" step="0.01"
                                   value="{{ $pr->quantity }}">
                        </div>
                        <div class="field">
                            <label>Unit price agreed (KSH) <span class="req">*</span></label>
                            <input type="number" name="unit_cost_ksh" required min="0" step="0.01"
                                   value="{{ $best ? round((float) $best->amount_ksh / max((float) $pr->quantity, 1), 2) : '' }}">
                        </div>
                    </div>
                    <div class="field" style="margin-top:10px;">
                        <label>Note on the order</label>
                        <input type="text" name="note" maxlength="1000"
                               placeholder="e.g. Deliver to the Kisumu centre store, attn. storekeeper, before 30 Oct.">
                    </div>
                    <button class="btn btn-lime btn-block" type="submit" style="margin-top:12px;">Write the order</button>
                </form>
            </div>
        @endif

        <div class="card">
            <h3>Purchase orders on this request</h3>
            @forelse ($pr->orders as $o)
                <div class="section-head"><b>{{ $o->reference }}</b>
                    <span class="status {{ $o->status === 'paid' ? 'active' : ($o->status === 'draft' ? 'pending_review' : 'pending_review') }}">
                        {{ $o->statusLabel() }}
                    </span>
                </div>
                <dl class="kv">
                    <dt>Supplier</dt><dd>{{ $o->supplier->name ?? '—' }} <span class="hint">{{ $o->supplier?->payLabel() }}</span></dd>
                    <dt>Ordered</dt><dd>{{ $o->orderedLabel() }} at {{ Finance::ksh((float) $o->unit_cost_ksh) }}
                        <b>= {{ $o->amountLabel() }}</b></dd>
                    <dt>Delivery note</dt><dd>{{ $o->delivery_note_ref ?: 'not signed in yet' }}
                        <div class="hint">{{ $o->receivedLabel() }} received
                            @if ($o->received_qty !== null)
                                @if ((float) $o->received_qty < (float) $o->quantity) <span class="flag-bad">short of the order</span>
                                @else <span class="flag-good">as ordered</span>@endif
                            @endif
                        </div></dd>
                    <dt>Invoice</dt><dd>{{ $o->invoice_ref ?: '—' }} · {{ $o->invoiceLabel() }}
                        @if ($o->invoice_amount_ksh !== null)
                            <div class="hint {{ $o->paperworkMatches() ? 'flag-good' : 'flag-bad' }}">
                                {{ $o->paperworkMatches() ? 'PO, delivery note and invoice agree' : 'the invoice is not the order amount — query it before paying' }}
                            </div>
                        @endif</dd>
                    @if ($o->receipt_note)<dt>Quality check</dt><dd>{{ $o->receipt_note }}</dd>@endif
                    <dt>Paper trail</dt>
                    <dd><span class="hint">
                        raised by {{ $o->raiser->exists ? $o->raiser->name : '—' }}
                        @if ($o->issued_on) · sent {{ $o->issued_on->format('d M Y') }}@endif
                        @if ($o->received_on) · received {{ $o->received_on->format('d M Y') }} by {{ $o->receiver->exists ? $o->receiver->name : '—' }}@endif
                        @if ($o->matched_on) · matched {{ $o->matched_on->format('d M Y') }} by {{ $o->matcher->exists ? $o->matcher->name : '—' }}@endif
                        @if ($o->paid_on) · paid {{ $o->paid_on->format('d M Y') }}@endif
                        @if ($o->emailed_at) · order emailed to {{ $o->sent_to }} {{ $o->emailed_at->format('d M Y') }}@endif
                    </span></dd>
                </dl>

                <p class="hint" style="margin:0 0 12px;">
                    <a class="btn btn-sm btn-outline" href="{{ route('admin.procurement.order.document', $o) }}"
                       target="_blank" rel="noopener">Print or save the order</a>
                    <a class="btn btn-sm btn-ghost"
                       href="{{ download_href(route('admin.procurement.order.document', $o)) }}">Download</a>
                    @include('partials.sheet-start', ['name' => 'email-order-'.$o->id,
                        'title' => 'Send the order to the supplier',
                        'sub' => 'The email carries the same document the office prints. Sending it is the sending step.'])
                        <form method="POST" action="{{ route('admin.procurement.order.email', $o) }}"
                              class="fee-form" data-title="Email order">
                            @csrf
                            <input type="hidden" name="_sheet" value="email-order-{{ $o->id }}">
                            <div class="field">
                                <label>Send to <span class="req">*</span></label>
                                <input type="email" name="to" required maxlength="190"
                                       value="{{ old('to', $o->supplier?->email) }}"
                                       placeholder="the supplier's email address">
                            </div>
                            <div class="field" style="margin-top:10px;">
                                <label>The line above the document</label>
                                <textarea name="note" rows="2" maxlength="1000"
                                          placeholder="e.g. Deliver to the Kisumu centre store, attn. storekeeper, before 30 Oct."></textarea>
                            </div>
                            <div class="sheet-actions">
                                <button class="btn btn-sm btn-ghost" type="button" data-close-sheet>Cancel</button>
                                <button class="btn btn-sm btn-lime" type="submit">Send {{ $o->reference }}</button>
                            </div>
                        </form>
                    @include('partials.sheet-end')
                    <button class="btn btn-sm btn-outline" type="button"
                            data-sheet="email-order-{{ $o->id }}">Email to the supplier</button>
                    @unless ($o->supplier?->email)
                        <span class="hint">no supplier email on file — type theirs, or add it to the supplier record</span>
                    @endunless
                </p>

                @if ($o->status === 'draft')
                    <form method="POST" action="{{ route('admin.procurement.order.issue', $o) }}" class="mini-form"
                          data-confirm="Send {{ $o->reference }} to the supplier?" data-title="Send order">
                        @csrf
                        <button class="btn btn-sm btn-primary" type="submit">Send to the supplier</button>
                    </form>
                @endif

                @if ($o->status === 'issued')
                    <form method="POST" action="{{ route('admin.procurement.order.receive', $o) }}" class="fee-form"
                          data-confirm="Sign this delivery in and put the goods on the stock card?" data-title="Receive goods">
                        @csrf
                        <p class="hint">Step 6 — the storekeeper checks quantity and quality, signs the delivery note
                            and the goods go onto the shelf. The same delivery cannot be counted twice.</p>
                        <div class="form-grid-2">
                            <div class="field">
                                <label>Quantity actually received <span class="req">*</span></label>
                                <input type="number" name="received_qty" required min="0.01" step="0.01" value="{{ $o->quantity }}">
                            </div>
                            <div class="field">
                                <label>Delivery note number <span class="req">*</span></label>
                                <input type="text" name="delivery_note_ref" required maxlength="80" placeholder="DN-4471">
                            </div>
                        </div>
                        <div class="field" style="margin-top:10px;">
                            <label>Quality check</label>
                            <input type="text" name="receipt_note" maxlength="1000"
                                   placeholder="e.g. 24 cartons counted, 2 damaged on the pallet, warranty cards in.">
                        </div>
                        <button class="btn btn-lime btn-block" type="submit" style="margin-top:12px;">
                            Sign the delivery in and update stock
                        </button>
                    </form>
                @endif

                @if ($o->status === 'received')
                    <form method="POST" action="{{ route('admin.procurement.order.match', $o) }}" class="fee-form"
                          data-confirm="Match the paperwork on {{ $o->reference }}?" data-title="Match paperwork">
                        @csrf
                        <p class="hint">Step 7 — the order, the delivery note the storekeeper signed and the supplier
                            invoice are lined up here before a payment is raised.</p>
                        <div class="form-grid-2">
                            <div class="field">
                                <label>Invoice number <span class="req">*</span></label>
                                <input type="text" name="invoice_ref" required maxlength="80" placeholder="INV-90112">
                            </div>
                            <div class="field">
                                <label>Invoice amount (KSH) <span class="req">*</span></label>
                                <input type="number" name="invoice_amount_ksh" required min="0.01" step="0.01"
                                       value="{{ $o->amount_ksh }}">
                            </div>
                        </div>
                        <button class="btn btn-lime btn-block" type="submit" style="margin-top:12px;">Match and raise the payment</button>
                    </form>
                @endif

                @if ($o->status === 'matched')
                    <form method="POST" action="{{ route('admin.procurement.order.pay', $o) }}" class="fee-form"
                          data-confirm="Record this payment on the ledger?" data-title="Pay the supplier">
                        @csrf
                        <p class="hint">Step 8 — money leaves the programme here, so it is a Finance Officer
                            (programme executive) action. The ledger carries the expense against {{ $pr->budgetLabel() }}.</p>
                        <div class="form-grid-2">
                            @include('partials.mpesa-line', ['cls' => 'full'])
                            <div class="field">
                                <label>Paid by <span class="req">*</span></label>
                                <select name="method" required>
                                    <option value="mpesa">M-Pesa</option>
                                    <option value="bank">Bank transfer</option>
                                    <option value="cheque">Cheque</option>
                                    <option value="cash">Cash</option>
                                </select>
                            </div>
                            <div class="field">
                                <label>Date paid <span class="req">*</span></label>
                                <input type="date" name="paid_on" required value="{{ now()->toDateString() }}">
                            </div>
                        </div>
                        <button class="btn btn-lime btn-block" type="submit" style="margin-top:12px;"
                            @if (! $isExec) disabled @endif>
                            {{ $isExec ? 'Pay '.$o->amountLabel().' and record it' : 'Only a programme executive records a payment' }}
                        </button>
                        @if (! $isExec)
                            <div class="hint" style="margin-top:8px;">The paperwork is matched — hand the payment to
                                the Finance Officer to release.</div>
                        @endif
                    </form>
                @endif

                @if (! $loop->last)<hr style="margin:18px 0; border:0; border-top:1px solid var(--line);">@endif
            @empty
                <p class="hint">No purchase order written yet.</p>
            @endforelse
        </div>
    </div>
</div>
@endsection
