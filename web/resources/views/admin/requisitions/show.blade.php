@extends('layouts.admin')
@section('title', $rq->reference)

@php
    use App\Models\Requisition;
    use App\Support\Finance;

    $here = $rq->stepIndex();
@endphp

@section('content')
@include('partials.errors')
<p class="back"><a href="{{ route('admin.requisitions.index') }}">&larr; Requisitions</a></p>

<div class="applicant-head card">
    <div class="ah-main">
        <h1>{{ $rq->item_name }}
            <span class="status {{ $rq->isClosed() ? ($rq->status === 'rejected' ? 'closed' : 'active') : 'pending_review' }}">
                {{ $rq->statusLabel() }}
            </span>
        </h1>
        <p class="ah-meta">
            <span class="tag">{{ $rq->reference }}</span>
            <span>{{ $rq->quantityLabel() }}</span>
            <span>{{ $rq->priceLabel() }}</span>
            <span>{{ $rq->departmentLabel() }}</span>
            <span>Raised {{ $rq->created_at->format('d M Y') }}</span>
            @if ($rq->needed_by)<span>needed by {{ $rq->needed_by->format('d M Y') }}</span>@endif
        </p>
    </div>
    <div class="ah-contact">
        <span class="hint">Raised by</span>
        <b>{{ $rq->raiser->exists ? $rq->raiser->name : 'the office' }}</b>
    </div>
</div>

<div class="grid-2 review-grid">
    <div class="stack">
        <div class="card">
            <h3>The requisition path</h3>
            <p class="hint">A step only opens once the row is actually on it, so nothing is paid out before
                Procurement has approved the ask.</p>
            <ol class="steps">
                @foreach (Requisition::PATH as $i => $step)
                    <li class="{{ $rq->status === $step['status'] ? 'current' : ($i < $here ? 'done' : '') }}">
                        <b>{{ $step['action'] }}</b>
                        <div class="hint">{{ $step['actor'] }}</div>
                        @if ($rq->status === $step['status'])<span class="tag gold">This requisition is here</span>@endif
                    </li>
                @endforeach
            </ol>
        </div>

        <div class="card">
            <h3>What was asked for</h3>
            <dl class="kv">
                <dt>Item</dt><dd>{{ $rq->item_name }}</dd>
                <dt>Quantity</dt><dd>{{ $rq->quantityLabel() }}</dd>
                <dt>Quality or standard</dt><dd>{{ $rq->quality_note ?: 'No standard stated' }}</dd>
                <dt>Estimated price</dt><dd>{{ $rq->priceLabel() }}</dd>
                <dt>Charged to</dt><dd>{{ $rq->budgetLabel() }}</dd>
                <dt>For</dt><dd>{{ $rq->project->exists ? $rq->project->title : 'General desk need' }}</dd>
                <dt>Desk</dt><dd>{{ $rq->departmentLabel() }}</dd>
                <dt>What it is for</dt><dd>{{ $rq->purpose }}</dd>
            </dl>
        </div>

        @if ($rq->decided_at)
            <div class="card gaps">
                <b>Procurement {{ $rq->status === 'rejected' ? 'rejected' : 'checked' }} it
                    on {{ $rq->decided_at->format('d M Y') }}</b>
                <span class="hint">by {{ $rq->decider->exists ? $rq->decider->name : 'the desk' }}.</span>
                @if ($rq->decision_note)
                    <div>{{ $rq->decision_note }}</div>
                @endif
            </div>
        @endif

        @if ($rq->processed_at)
            <div class="card">
                <h3>How Finance processed it</h3>
                <dl class="kv">
                    <dt>Outcome</dt><dd>{{ $rq->statusLabel() }}</dd>
                    @if ($rq->status === 'disbursed')
                        <dt>Paid out</dt><dd><b>{{ $rq->paidLabel() }}</b></dd>
                        <dt>Paid by</dt><dd>{{ Requisition::PAYMENTS[$rq->paid_method] ?? $rq->paid_method }}</dd>
                    @else
                        <dt>Handed to</dt><dd>{{ $rq->given_to }}</dd>
                        <dt>What went out</dt><dd>{{ $rq->quantityLabel() }} of {{ $rq->item_name }}</dd>
                    @endif
                    <dt>Processed on</dt><dd>{{ $rq->processed_at->format('d M Y') }}
                        by {{ $rq->processor->exists ? $rq->processor->name : 'Finance' }}</dd>
                    @if ($rq->finance_note)<dt>Note</dt><dd>{{ $rq->finance_note }}</dd>@endif
                </dl>
                @if ($lines->isNotEmpty())
                    <p class="hint">On the money register:
                        @foreach ($lines as $line)
                            <b>{{ $line->reference }}</b>
                            <span class="hint">{{ $line->title }} — {{ Finance::ksh((float) $line->amount_ksh) }},
                                {{ $line->happened_on->format('d M Y') }}</span>
                        @endforeach
                    </p>
                @else
                    <p class="hint">No money line — the goods came from the store, so the register is not touched twice.</p>
                @endif
            </div>
        @endif
    </div>

    <div class="stack">
        @if ($canDecide)
            <div class="card">
                <h3>Step 2 — Procurement checks it</h3>
                <p class="hint">Compare it with what the store already holds and what the budget line can carry.
                    A rejection stays on the row with its reason.</p>
                <button class="btn btn-sm btn-lime btn-block" type="button" data-sheet="decision">Record the decision</button>
            </div>
        @endif

        @if ($canProcess)
            <div class="card">
                <h3>Step 3 — Finance processes it</h3>
                <p class="hint">Pay the money out and the row reads Disbursed with its register line; release goods
                    from the store and it reads Distributed with the name that received them.</p>
                <button class="btn btn-sm btn-lime btn-block" type="button" data-sheet="process">Process it</button>
            </div>
        @endif

        @if ($canReopen)
            <div class="card">
                <h3>Put it back on the path</h3>
                <p class="hint">A decision taken in error is corrected on the same row — the requisition is not
                    raised twice.</p>
                <button class="btn btn-sm btn-outline btn-block" type="button" data-sheet="reopen">Reopen it</button>
            </div>
        @endif

        @if (! $canDecide && ! $canProcess && ! $canReopen)
            <div class="card">
                <p class="hint">
                    @if ($rq->status === 'submitted')
                        This ask is with the Procurement &amp; Stores desk for a decision.
                    @elseif ($rq->status === 'approved')
                        Approved, and now with Finance to be paid out or handed over.
                    @else
                        This requisition is closed and kept on the register as the record it is.
                    @endif
                </p>
            </div>
        @endif

        <div class="card">
            <h3>Where it stands</h3>
            <dl class="kv">
                <dt>Raised</dt><dd>{{ $rq->created_at->format('d M Y') }}</dd>
                <dt>Decision</dt>
                <dd>{{ $rq->decided_at ? $rq->decided_at->format('d M Y') : 'not taken yet' }}</dd>
                <dt>Processed</dt>
                <dd>{{ $rq->processed_at ? $rq->processed_at->format('d M Y') : 'not yet' }}</dd>
            </dl>
        </div>
    </div>
</div>

@if ($canDecide)
    @include('partials.sheet-start', ['name' => 'decision', 'title' => 'The decision on ' . $rq->reference,
        'sub' => 'It goes to Finance to process, or comes back with the reason written on it.'])
        <form method="POST" action="{{ route('admin.requisitions.decide', $rq) }}" class="fee-form"
              data-confirm="Record this decision on {{ $rq->reference }}?" data-title="Decision">
            @csrf
            <input type="hidden" name="_sheet" value="decision">
            <div class="field">
                <label>Decision <span class="req">*</span></label>
                <select name="approve" required>
                    <option value="yes">Approved — forward it to Finance</option>
                    <option value="no">Rejected — with a reason</option>
                </select>
            </div>
            <div class="field" style="margin-top:10px;">
                <label>Reason <span class="req">*</span> when rejecting</label>
                <input type="text" name="note" maxlength="1000" value="{{ old('note') }}"
                       placeholder="e.g. The store already holds 300 reams — take them out instead of buying.">
            </div>
            <div class="sheet-actions">
                <button class="btn btn-sm btn-ghost" type="button" data-close-sheet>Cancel</button>
                <button class="btn btn-sm btn-lime" type="submit">Record the decision</button>
            </div>
        </form>
    @include('partials.sheet-end')
@endif

@if ($canProcess)
    @include('partials.sheet-start', ['name' => 'process', 'title' => 'Process ' . $rq->reference,
        'sub' => 'Money out is signed by a programme executive and posts its own register line; goods handed over are recorded with who received them.'])
        <form method="POST" action="{{ route('admin.requisitions.process', $rq) }}" class="fee-form"
              data-confirm="Process {{ $rq->reference }} and keep the record on this row?" data-title="Process requisition">
            @csrf
            <input type="hidden" name="_sheet" value="process">
            <div class="field">
                <label>How it was settled <span class="req">*</span></label>
                <select name="outcome" required>
                    @if ($isExec)
                        <option value="disbursed">Disbursed — money was paid out</option>
                    @endif
                    <option value="distributed">Distributed — goods handed over</option>
                </select>
                @if (! $isExec)
                    <div class="hint">Paying money out is a programme executive’s action. Process it as a
                        distribution here, or ask an executive account to disburse it.</div>
                @endif
            </div>
            <div class="form-grid-2" style="margin-top:10px;">
                <div class="field">
                    <label>Amount paid (KSH) <span class="req">*</span></label>
                    <input type="number" name="paid_amount_ksh" min="0.01" step="0.01"
                           value="{{ old('paid_amount_ksh', $rq->estimated_price_ksh) }}">
                </div>
                <div class="field">
                    <label>Paid by <span class="req">*</span></label>
                    <select name="paid_method">
                        @foreach ($payments as $key => $label)
                            <option value="{{ $key }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
            <div class="field" style="margin-top:10px;">
                <label>Handed to <span class="req">*</span> when goods go out</label>
                <input type="text" name="given_to" maxlength="190" value="{{ old('given_to') }}"
                       placeholder="e.g. Jane Odhiambo, Training desk — signed the store note.">
            </div>
            <div class="field" style="margin-top:10px;">
                <label>Note on the processing</label>
                <textarea name="finance_note" rows="2" maxlength="1000"
                          placeholder="e.g. Paid in two parts; the balance goes with next week’s imprest.">{{ old('finance_note') }}</textarea>
            </div>
            <div class="sheet-actions">
                <button class="btn btn-sm btn-ghost" type="button" data-close-sheet>Cancel</button>
                <button class="btn btn-sm btn-lime" type="submit">Process it</button>
            </div>
        </form>
    @include('partials.sheet-end')
@endif

@if ($canReopen)
    @include('partials.sheet-start', ['name' => 'reopen', 'title' => 'Reopen ' . $rq->reference,
        'sub' => 'The row goes back to Procurement’s queue as raised, with this note replacing the old one.'])
        <form method="POST" action="{{ route('admin.requisitions.decide', $rq) }}" class="fee-form"
              data-confirm="Put {{ $rq->reference }} back on the path as raised?" data-title="Reopen requisition">
            @csrf
            <input type="hidden" name="_sheet" value="reopen">
            <input type="hidden" name="approve" value="back">
            <div class="field">
                <label>Why it is back on the path <span class="req">*</span></label>
                <input type="text" name="note" required minlength="10" maxlength="1000"
                       placeholder="e.g. Rejected against the wrong budget line — the store note says it is operations money.">
            </div>
            <div class="sheet-actions">
                <button class="btn btn-sm btn-ghost" type="button" data-close-sheet>Cancel</button>
                <button class="btn btn-sm btn-lime" type="submit">Reopen it</button>
            </div>
        </form>
    @include('partials.sheet-end')
@endif
@endsection
