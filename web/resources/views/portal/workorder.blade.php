@extends('layouts.member')
@section('title', 'Work order '.$order->reference)

@php
    use App\Models\WorkOrder;
    use App\Models\WorkOrderDispute;
    use App\Models\WorkOrderMilestone;
    use App\Support\Finance;

    $pill = ['issued' => 'pending_review', 'accepted' => 'pending_review', 'in_progress' => 'awarded',
        'completion_submitted' => 'awarded', 'confirmed' => 'active', 'disputed' => 'rejected',
        'closed' => 'active', 'cancelled' => 'rejected'];
    $today = now()->toDateString();
@endphp

@section('content')
<p class="back"><a href="{{ route('portal.work') }}">&larr; My work orders</a></p>

<div class="page-intro">
    <span class="status {{ $pill[$order->status] ?? 'pending_review' }}">{{ $order->statusLabel() }}</span>
    <h1 style="margin:8px 0 4px;">{{ $order->reference }} — {{ $order->project->title }}</h1>
    <p class="sub">
        {{ collect([$order->project->county, $order->project->ward])->filter()->implode(', ') ?: 'the site on the project' }}
        · for {{ $order->project->hirer->organisation ?: 'the hirer named on the project' }}
        · {{ $order->start_on?->format('d M Y') ?? 'start not confirmed' }}
        → {{ $order->deadline?->format('d M Y') ?? 'no deadline' }}
    </p>
    <p class="hint"><b>Where it stands:</b> {{ $next }}</p>
</div>

<div class="card">
    <div class="section-head"><h3>What you signed up for</h3></div>
    <dl class="kv">
        <div><dt>Scope of work</dt><dd>{{ nl2br(e($order->scope ?: 'not written')) }}</dd></div>
        <div><dt>Deliverables</dt><dd>{{ nl2br(e($order->deliverables ?: 'not written')) }}</dd></div>
        <div><dt>Agreed for the job</dt><dd><b>{{ $order->agreedLabel() }}</b>
            <div class="hint">{{ $order->scheduleLabel() }} — released on {{ $order->splitLabel() }}</div></div></div>
        <div><dt>Materials</dt><dd>{{ $order->materials_note ?: 'the project lists none' }}</dd></div>
        @if ($order->accepted_on)
            <div><dt>You accepted</dt><dd><b>{{ $order->signature }}</b> on {{ $order->accepted_on->format('d M Y H:i') }}
, work confirmed to start {{ $order->start_on?->format('d M Y') }}</dd></div>
        @endif
    </dl>

    @if ($order->status === 'issued')
        <form method="POST" action="{{ route('portal.work.accept', $order) }}" class="fee-form"
              data-confirm="Accept and sign {{ $order->reference }}?" data-title="Accept the work order">
            @csrf
            <p class="help">Accepting is your signature on the scope, the deliverables and the schedule above.
                Confirm the day you are actually starting — the deadline and every stage date are measured from it.</p>
            <div class="form-grid-2">
                <div class="field">
                    <label for="signature">Type your full name as your signature <span class="req">*</span></label>
                    <input type="text" name="signature" id="signature" required minlength="3" maxlength="160"
                           value="{{ $member->full_name }}">
                </div>
                <div class="field">
                    <label for="start_on">I am starting on <span class="req">*</span></label>
                    <input type="date" name="start_on" id="start_on" required value="{{ $order->start_on?->toDateString() ?? $today }}">
                </div>
            </div>
            <div class="field">
                <label for="accept_note">Anything the office should know</label>
                <input type="text" name="accept_note" id="accept_note" maxlength="1000"
                       placeholder="Who is with you on the job, what the site needs first">
            </div>
            <button class="btn btn-lime btn-block" type="submit">Accept and sign the order</button>
        </form>
    @endif
</div>

<div class="card">
    <div class="section-head"><h3>Materials the programme is giving you</h3></div>
    <p class="help">The storekeeper hands these against your order. What is actually issued is deducted from your
        payout — anything not handed over is not deducted, so check the dates on this table.</p>
    <table class="data">
        <thead><tr><th>Material</th><th>Quantity</th><th>Worth each</th><th>Total</th><th>Issued</th></tr></thead>
        <tbody>
            @forelse ($order->project->providedMaterials as $m)
                <tr>
                    <td><b>{{ $m->name }}</b>@if ($m->note)<div class="hint">{{ $m->note }}</div>@endif</td>
                    <td>{{ $m->quantityLabel() }}</td>
                    <td>{{ $m->unitCostLabel() }}</td>
                    <td>{{ $m->totalLabel() }}</td>
                    <td>@if ($m->isSupplied())
                            <span class="status active">{{ $m->supplied_on->format('d M Y') }}</span>
                            <div class="hint">{{ $m->item->exists ? 'from the store · '.$m->item->name : 'bought for the job' }}</div>
                        @else <span class="tag gray">at the store, on assignment</span> @endif</td>
                </tr>
            @empty
                <tr><td colspan="5" class="empty-cell">
                    No material line is listed for this project — ask the office if you were told to expect some.
                </td></tr>
            @endforelse
        </tbody>
    </table>
    <p class="hint">Issued so far {{ Finance::ksh($order->materialsIssuedKsh()) }} of the
        {{ Finance::ksh($order->materialsPlannedKsh()) }} planned.</p>
</div>

<div class="card">
    <div class="section-head"><h3>Your log</h3></div>
    @if (in_array($order->status, ['accepted', 'in_progress', 'disputed'], true))
        <form method="POST" action="{{ route('portal.work.log', $order) }}" class="fee-form" enctype="multipart/form-data"
              data-title="Log a day">
            @csrf
            <input type="hidden" name="kind" value="progress">
            <div class="form-grid-2">
                <div class="field">
                    <label for="entry_date">Which day <span class="req">*</span></label>
                    <input type="date" name="entry_date" id="entry_date" required value="{{ $today }}">
                </div>
                <div class="field">
                    <label for="percent">Percentage complete</label>
                    <input type="number" name="percent" id="percent" min="0" max="100" step="1" value="{{ $order->percent_complete }}">
                    <span class="hint">the log keeps the highest figure you report</span>
                </div>
            </div>
            <div class="field">
                <label for="note">What you did that day <span class="req">*</span></label>
                <textarea name="note" id="note" rows="3" required maxlength="5000"
                          placeholder="Where you worked, what was finished, what is holding you up"></textarea>
            </div>
            <div class="field">
                <label for="photo">Photo of the work</label>
                <input type="file" name="photo" id="photo" accept="image/*">
            </div>
            <button class="btn btn-sm btn-primary" type="submit">Add to the log</button>
        </form>
    @else
        <p class="help">The log is open while the order is signed and on site. @if ($order->status === 'issued')
            Accept the order first.@endif</p>
    @endif

    <table class="data">
        <thead><tr><th>Date</th><th>Entry</th><th>%</th><th>What happened</th><th>Who wrote it</th><th>Photo</th></tr></thead>
        <tbody>
            @forelse ($order->updates as $u)
                <tr>
                    <td>{{ $u->entry_date->format('d M Y') }}</td>
                    <td><span class="tag {{ $u->kind === 'completion' ? 'gold' : 'gray' }}">{{ $u->kindLabel() }}</span></td>
                    <td>{{ $u->percent !== null ? $u->percent.'%' : '—' }}</td>
                    <td>{{ $u->note }}</td>
                    <td class="hint">{{ $u->fundi->full_name ?: ($u->recorder->name ?: 'the office') }}</td>
                    <td>@if ($u->hasPhoto())
                            <a class="photo-thumb" href="{{ route('portal.work.update.photo', [$order, $u]) }}" data-zoom
                               data-zoom-label="{{ $order->reference }} — {{ $u->kindLabel() }} {{ $u->entry_date->format('d M Y') }}"
                               aria-label="Zoom this photo"><img src="{{ route('portal.work.update.photo', [$order, $u]) }}" alt="{{ $u->kindLabel() }} photo"></a>
                        @else — @endif</td>
                </tr>
            @empty
                <tr><td colspan="6" class="empty-cell">Nothing logged yet.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>

<div class="card">
    <div class="section-head"><h3>Stages and sign-offs</h3></div>
    @if ($order->milestones->isEmpty())
        <p class="help">This order is on the <b>{{ mb_strtolower($order->scheduleLabel()) }}</b> schedule, so it has no
            stage to be signed off — the money is settled on completion.</p>
    @else
        <table class="data">
            <thead><tr><th>Stage</th><th>Share</th><th>Worth</th><th>Due</th><th>Signed off</th></tr></thead>
            <tbody>
                @foreach ($order->milestones as $ms)
                    <tr>
                        <td><b>{{ $ms->name }}</b></td>
                        <td>{{ $ms->share_percent }}%</td>
                        <td>{{ $ms->amountLabel() }}</td>
                        <td>{{ $ms->due_on?->format('d M Y') ?? '—' }}</td>
                        <td>@if ($ms->isSigned())
                                <span class="status active">{{ $ms->signed_on?->format('d M Y') }}</span>
                                <div class="hint">{{ $ms->signer->name ?: 'a supervisor' }} — {{ $ms->note }}</div>
                            @elseif ($ms->status === WorkOrderMilestone::DROPPED)
                                <span class="status closed">{{ $ms->statusLabel() }}</span>
                            @else
                                <span class="tag gray">waiting on a supervisor's visit</span>
                            @endif</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
        <p class="hint">A signed stage is money the programme has agreed you earned; it is still released by Finance
            after the hirer pays, not on the signature alone.</p>
    @endif
</div>

@if ($order->status === 'disputed' && $dispute)
    <div class="card" style="border-left:4px solid var(--danger);">
        <div class="section-head"><h3>The hirer's issue — answer it within {{ $window }} hours</h3></div>
        <p>{{ $dispute->issue }}</p>
        <p class="hint">Recorded {{ $dispute->raised_on->format('d M Y H:i') }}
            @if ($dispute->issue_path)
                · <a class="doc-link" href="{{ route('portal.work.dispute.evidence', [$order, $dispute]) }}" target="_blank"
                     rel="noopener" data-zoom data-zoom-label="{{ $order->reference }} — the hirer's file">open their file</a>
            @endif</p>

        @if ($dispute->isAwaitingWorker() && $member instanceof \App\Models\FundiRegistration)
            <p class="hint"><b>{{ $dispute->timeLabel() }}</b></p>
            <form method="POST" action="{{ route('portal.work.dispute.answer', [$order, $dispute]) }}" class="fee-form"
                  data-confirm="Send your answer on this dispute?" data-title="Answer the dispute">
                @csrf
                <div class="field">
                    <label for="response">Your side of it <span class="req">*</span></label>
                    <textarea name="response" id="response" rows="3" required maxlength="5000"
                              placeholder="What happened, what you can still fix, and when"></textarea>
                </div>
                <button class="btn btn-sm btn-primary" type="submit">Send my answer</button>
            </form>
        @elseif ($dispute->responded_on)
            <dl class="kv">
                <div><dt>Your answer, sent {{ $dispute->responded_on->format('d M Y H:i') }}</dt><dd>{{ $dispute->response }}</dd></div>
                <div><dt>Mediation</dt><dd>{{ $dispute->mediated_by_user
                    ? ($dispute->mediator->name ?: 'the Programme Manager').' — '.$dispute->mediation_note : 'not yet' }}</dd></div>
            </dl>
            <p class="hint">The Programme Manager mediates and a Director decides. The outcome — rework, partial or full
                payment, refund, or a deduction — is written on this order and carried onto your payout.</p>
        @else
            <p class="help">An attachment order has no answer box for a trainee; the office settles it with the hirer
                and notes the outcome on your record.</p>
        @endif
    </div>
@endif

<div class="card">
    <div class="section-head"><h3>Finish the job</h3></div>
    @if (in_array($order->status, ['accepted', 'in_progress'], true))
        <p class="help">When everything on the deliverables list is done, request the completion with the final photos.
            The office then arranges the hirer's inspection — they either confirm the work, which starts your payout,
            or raise an issue, which comes back to you here.</p>
        <form method="POST" action="{{ route('portal.work.complete', $order) }}" class="fee-form" enctype="multipart/form-data"
              data-confirm="Request the completion of {{ $order->reference }} and send it for inspection?" data-title="Request completion">
            @csrf
            <div class="field">
                <label for="completion">What was completed <span class="req">*</span></label>
                <textarea name="note" id="completion" rows="3" required maxlength="5000"
                          placeholder="Each deliverable and how it was finished, and anything left for the hirer to know"></textarea>
            </div>
            <div class="field">
                <label for="final_photo">Final photo</label>
                <input type="file" name="photo" id="final_photo" accept="image/*">
            </div>
            <button class="btn btn-lime btn-block" type="submit">Request the completion</button>
        </form>
    @elseif ($order->completion_requested_on)
        <p class="help">You requested the completion on {{ $order->completion_requested_on->format('d M Y') }}.
            @if ($order->confirmed_on)
                The hirer confirmed the work on {{ $order->confirmed_on->format('d M Y') }} — the payout is on
                <a href="{{ route('portal.earnings') }}">My earnings</a> as {{ $payout?->reference ?? 'a row Finance opens' }}.
            @elseif ($order->status === 'disputed')
                The hirer raised an issue instead; answer it above.
            @else
                The office is arranging the inspection.
            @endif</p>
    @endif

    @if ($order->confirmed_on)
        <dl class="kv">
            <div><dt>Confirmed by the hirer</dt><dd>{{ $order->confirmed_by_name }} on {{ $order->confirmed_on->format('d M Y') }}</dd></div>
            <div><dt>What they said</dt><dd>{{ $order->confirmation_note }}</dd></div>
        </dl>
        @if ($payout)
            <p class="hint">Payout <b>{{ $payout->reference }}</b> — {{ $payout->stageLabel() }}. {{ $payout->calculation }}</p>
        @endif
    @endif
</div>

<div class="card">
    <div class="section-head"><h3>The rules on this order</h3></div>
    <ul class="help" style="margin:0;padding-left:18px;">
        <li>The agreed amount is what the project was posted with; your payout is that figure <b>minus</b> the materials
            actually issued, the service commission and any credit decided against the job.</li>
        <li>Money reaches you only after the hirer pays the organisation, and it is released by a programme executive.</li>
        <li>A day you do not log is a day the supervisor cannot sign off — the log is what your payment is checked against.</li>
        <li>Being awarded a job puts you on the <a href="{{ route('portal.efundi') }}">E-Fundi record</a> as on a job;
            set yourself free again there when the order closes.</li>
    </ul>
</div>
@endsection
