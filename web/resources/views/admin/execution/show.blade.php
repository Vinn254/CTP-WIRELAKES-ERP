@extends('layouts.admin')
@section('title', 'Work order '.$order->reference)

@php
    use App\Models\WorkOrder;
    use App\Models\WorkOrderDispute;
    use App\Models\WorkOrderMilestone;
    use App\Models\WorkOrderUpdate;
    use App\Support\Finance;

    $pill = ['issued' => 'pending_review', 'accepted' => 'pending_review', 'in_progress' => 'awarded',
        'completion_submitted' => 'awarded', 'confirmed' => 'active', 'disputed' => 'rejected',
        'closed' => 'active', 'cancelled' => 'rejected'];
    $canPlan = in_array($order->status, ['issued', 'accepted', 'in_progress'], true);
    $canIssue = in_array($order->status, ['issued', 'accepted', 'in_progress', 'disputed'], true);
    $canInspect = in_array($order->status, ['completion_submitted', 'disputed'], true);
    $materials = $order->project->providedMaterials;
@endphp

@section('content')
<p class="back"><a href="{{ route('admin.execution.index') }}">&larr; Delivery register</a></p>

<div class="proj-head">
    <div>
        <span class="status {{ $pill[$order->status] ?? 'pending_review' }}">{{ $order->statusLabel() }}</span>
        <span class="tag {{ $order->assignee_type === 'ctp' ? 'gray' : '' }}">{{ $order->assigneeLabel() }}</span>
        <h1 style="margin:8px 0 4px;">{{ $order->reference }} — {{ $order->project->title }}</h1>
        <div class="hint">
            {{ $order->project->reference }} · {{ $order->project->trade ?: 'any trade' }} ·
            {{ collect([$order->project->county, $order->project->ward])->filter()->implode(', ') ?: 'place not set' }} ·
            hirer {{ $order->project->hirer->organisation ?: 'not named' }}<br>
            {{ $order->assignee_name ?: 'no worker named' }}
            @if ($order->assignee_type === 'fundi' && $order->fundi->exists)
                ({{ $order->fundi->ef_code ?: 'no EF code' }})
            @endif
            · {{ $order->start_on?->format('d M Y') ?? 'not started' }}
            → {{ $order->deadline?->format('d M Y') ?? 'no deadline' }}
            · {{ $percent }}% reported on site
        </div>
    </div>
    <div class="page-actions">
        <a class="btn btn-sm btn-outline" href="{{ dept_link('admin.projects.show', ['project' => $order->project]) }}">The project</a>
        @if ($payout)
            <a class="btn btn-sm btn-outline" href="{{ dept_link('admin.payouts.show', ['payout' => $payout]) }}">{{ $payout->reference }}</a>
        @endif
    </div>
</div>

<div class="card" style="border-left:4px solid var(--green);">
    <b>Next on this order:</b> {{ $next }}
</div>

<div class="card">
    <h2 class="section-head">The order (§10.2)</h2>
    <dl class="kv">
        <div><dt>Scope of work</dt><dd>{{ nl2br(e($order->scope ?: 'not written')) }}</dd></div>
        <div><dt>Deliverables</dt><dd>{{ nl2br(e($order->deliverables ?: 'not written')) }}</dd></div>
        <div><dt>Payment schedule</dt><dd>{{ $order->scheduleLabel() }}
            <div class="hint">Released on {{ $order->splitLabel() }}
                @if ($splitTotal !== 100) — <b>the shares do not add to 100%</b>@endif</div></dd></div>
        <div><dt>Agreed amount</dt><dd>{{ $order->agreedLabel() }}
            <span class="hint">what the project was posted with — the payout is calculated from it in Module 11</span></dd></div>
        <div><dt>Materials the programme provides</dt><dd>{{ $order->materials_note ?: 'none listed on the project' }}</dd></div>
        <div><dt>Accepted and signed</dt>
            <dd>@if ($order->accepted_on)
                    <b>{{ $order->signature }}</b> on {{ $order->accepted_on->format('d M Y H:i') }}, starting
                    {{ $order->start_on?->format('d M Y') }}
                    <div class="hint">{{ $order->accept_note }}</div>
                @else
                    not yet — the worker signs it on their own portal screen (§10.4 step 1)
                @endif</dd></div>
        <div><dt>Opened by</dt><dd>{{ $order->raiser->name ?: 'the system' }}
            · {{ $order->created_at->format('d M Y') }}</dd></div>
        @if ($order->closure_note)
            <div><dt>{{ $order->status === 'cancelled' ? 'Why it was stopped' : 'Why it closed' }}</dt>
                <dd>{{ $order->closure_note }}</dd></div>
        @endif
    </dl>

    @if ($canPlan)
        <form method="POST" action="{{ route('admin.execution.plan', $order) }}" class="fee-form" data-title="Fix the order">
            @csrf
            <div class="form-grid-2">
                <div class="field">
                    <label for="scope">Scope of work <span class="req">*</span></label>
                    <textarea name="scope" id="scope" rows="3" required>{{ $order->scope }}</textarea>
                </div>
                <div class="field">
                    <label for="deliverables">Deliverables <span class="req">*</span></label>
                    <textarea name="deliverables" id="deliverables" rows="3" required>{{ $order->deliverables }}</textarea>
                </div>
                <div class="field">
                    <label for="start_on">Start date <span class="req">*</span></label>
                    <input type="date" name="start_on" id="start_on" required
                           value="{{ $order->start_on?->toDateString() ?? now()->toDateString() }}">
                </div>
                <div class="field">
                    <label for="deadline">Deadline</label>
                    <input type="date" name="deadline" id="deadline" value="{{ $order->deadline?->toDateString() }}">
                </div>
                <div class="field">
                    <label for="schedule">Payment schedule <span class="req">*</span></label>
                    <select name="schedule" id="schedule" required>
                        @foreach (WorkOrder::SCHEDULES as $key => $label)
                            <option value="{{ $key }}" @selected($order->schedule === $key)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="field">
                    <label for="agreed_amount_ksh">Agreed amount (KSh) <span class="req">*</span></label>
                    <input type="number" name="agreed_amount_ksh" id="agreed_amount_ksh" step="0.01" min="0" required
                           value="{{ $order->agreed_amount_ksh }}">
                </div>
                <div class="field">
                    <label>Split of the stages (%) — one box per stage</label>
                    <div class="form-grid-2 split-rows" id="split-rows" style="gap:8px;">
                        @foreach (($order->split ?: WorkOrder::DEFAULT_SPLIT) as $i => $share)
                            <div class="split-row" data-index="{{ $i }}" style="display:flex;gap:4px;align-items:center;">
                                <input type="number" name="split[{{ $i }}]" min="1" max="100" step="1"
                                       value="{{ $share }}" aria-label="Stage {{ $i + 1 }} share">
                                <button class="btn btn-sm btn-ghost" type="button" data-split-remove hidden
                                        aria-label="Take stage {{ $i + 1 }} off the split">&times;</button>
                            </div>
                        @endforeach
                    </div>
                    <button class="btn btn-sm btn-outline" type="button" data-split-add="split-rows">+ another stage</button>
                    <span class="hint">Must add to 100. Saving lays the shares you set over the stages that nobody has
                        signed yet; a stage with a signature on it keeps its own figure, and the save is refused if your
                        split would move or drop it — lift that sign-off first (§10.5).</span>
                </div>
                <div class="field">
                    <label for="materials_note">Materials note</label>
                    <input type="text" name="materials_note" id="materials_note" maxlength="1000" value="{{ $order->materials_note }}">
                </div>
            </div>
            <button class="btn btn-sm btn-primary" type="submit">Save the order</button>
        </form>
    @else
        <p class="hint">The order is past the point where its plan is edited ({{ $order->statusLabel() }}).
            A wrong date or share after this point is corrected by a site-log entry or a dispute decision, not by rewriting the sheet.</p>
    @endif
</div>

<div class="card">
    <h2 class="section-head">Materials issued against the order (§10.3)</h2>
    <p class="help">
        The programme buys what the job needs and the storekeeper hands it over against this order. What is actually
        issued is the figure the payout deducts — the plan on the project page is not. A line that turns out not to be
        needed simply stays un-issued.
    </p>
    <table class="data">
        <thead><tr><th>Material</th><th>Quantity</th><th>Cost each</th><th>Worth</th><th>Stock card</th><th>Issued</th><th></th></tr></thead>
        <tbody>
            @forelse ($materials as $m)
                <tr>
                    <td><b>{{ $m->name }}</b>@if ($m->note)<div class="hint">{{ $m->note }}</div>@endif</td>
                    <td>{{ $m->quantityLabel() }}</td>
                    <td>{{ $m->unitCostLabel() }}</td>
                    <td>{{ $m->totalLabel() }}</td>
                    <td>{{ $m->item->exists ? $m->item->sku.' · '.$m->item->quantityLabel().' on the shelf' : 'bought for the job' }}</td>
                    <td>@if ($m->isSupplied())
                            <span class="status active">{{ $m->supplied_on->format('d M Y') }}</span>
                            <div class="hint">by {{ $m->provider->name ?: 'the store' }}</div>
                        @else
                            <span class="tag gray">at the store</span>
                        @endif</td>
                    <td>
                        @if (! $m->isSupplied() && $canIssue)
                            <details class="need-form">
                                <summary>Issue</summary>
                                <form method="POST" action="{{ route('admin.execution.material.issue', [$order, $m]) }}"
                                      class="fee-form" data-confirm="Issue {{ $m->name }} against {{ $order->reference }}?"
                                      data-title="Issue materials">
                                    @csrf
                                    <div class="field">
                                        <label>Date handed over <span class="req">*</span></label>
                                        <input type="date" name="supplied_on" required value="{{ now()->toDateString() }}">
                                    </div>
                                    <div class="field">
                                        <label>Note</label>
                                        <input type="text" name="note" maxlength="1000" placeholder="Who collected it, where it went">
                                    </div>
                                    <button class="btn btn-sm btn-primary" type="submit">Hand it over</button>
                                </form>
                            </details>
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="7" class="empty-cell">
                    No material line is on the project yet. The office lists them on
                    <a href="{{ dept_link('admin.projects.show', ['project' => $order->project]) }}">the project page</a> before the job starts.
                </td></tr>
            @endforelse
        </tbody>
    </table>
    <p class="hint">
        Planned {{ Finance::ksh($order->materialsPlannedKsh()) }} · issued {{ Finance::ksh($order->materialsIssuedKsh()) }}.
        Issuing a line that names a stock card also books the quantity out on that card (§14.5).
    </p>
</div>

<div class="card">
    <h2 class="section-head">Stages and sign-offs (§10.4 step 4)</h2>
    @if ($order->milestones->isEmpty())
        <p class="help">This order has no stages on it — {{ $order->schedule === 'fixed' ? 'a fixed-price job is paid on completion' : 'save a split on the order above to build them' }}.</p>
    @else
        <table class="data">
            <thead><tr><th>Stage</th><th>Share</th><th>Worth</th><th>Due</th><th>Status</th><th>Signed by</th><th></th></tr></thead>
            <tbody>
                @foreach ($order->milestones as $ms)
                    <tr>
                        <td><b>{{ $ms->name }}</b>@if ($ms->note)<div class="hint">{{ $ms->note }}</div>@endif</td>
                        <td>{{ $ms->share_percent }}%</td>
                        <td>{{ $ms->amountLabel() }}</td>
                        <td>{{ $ms->due_on?->format('d M Y') ?? '—' }}</td>
                        <td><span class="status {{ $ms->isSigned() ? 'active' : ($ms->status === WorkOrderMilestone::DROPPED ? 'closed' : 'pending_review') }}">
                            {{ $ms->statusLabel() }}</span></td>
                        <td>{{ $ms->signer->name ?: '—' }}
                            <div class="hint">{{ $ms->signed_on?->format('d M Y H:i') }}</div></td>
                        <td>
                            @if ($ms->status === WorkOrderMilestone::PENDING)
                                <details class="need-form">
                                    <summary>Sign off</summary>
                                    <form method="POST" action="{{ route('admin.execution.milestone.sign', [$order, $ms]) }}"
                                          class="fee-form" data-confirm="Sign {{ $ms->name }} off on {{ $order->reference }}?"
                                          data-title="Sign the stage off">
                                        @csrf
                                        <div class="field">
                                            <label>What you verified <span class="req">*</span></label>
                                            <input type="text" name="note" required maxlength="1000"
                                                   placeholder="What was seen on site that makes this stage done">
                                        </div>
                                        <button class="btn btn-sm btn-primary" type="submit">Sign off</button>
                                    </form>
                                </details>
                            @elseif ($ms->isSigned())
                                <details class="need-form">
                                    <summary>Lift the sign-off</summary>
                                    <form method="POST" action="{{ route('admin.execution.milestone.unsign', [$order, $ms]) }}"
                                          class="fee-form" data-confirm="Lift the sign-off on {{ $ms->name }}?"
                                          data-title="Lift the sign-off">
                                        @csrf
                                        <div class="field">
                                            <label>Why it is being lifted <span class="req">*</span></label>
                                            <input type="text" name="note" required maxlength="1000">
                                        </div>
                                        <button class="btn btn-sm btn-outline" type="submit">Lift it</button>
                                    </form>
                                </details>
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
        <p class="hint">
            {{ $outstanding ? $outstanding.' '.str('stage')->plural($outstanding).' still to be signed off — the hirer cannot confirm a milestone-split order until they are.' : 'Every stage on the split is signed off.' }}
            A lifted sign-off goes back to pending and the trail keeps both moves.
        </p>
    @endif
</div>

<div class="card">
    <h2 class="section-head">Site log (§10.4 steps 3–5)</h2>
    <p class="help">The worker's own days come in from their portal. This desk adds the supervisor's visits
        (§10.4 step 4) and the completion is what the hirer is asked to inspect.</p>
    @if (in_array($order->status, ['accepted', 'in_progress', 'completion_submitted', 'disputed'], true))
        <form method="POST" action="{{ route('admin.execution.visit', $order) }}" class="fee-form" enctype="multipart/form-data"
              data-title="Record a site visit">
            @csrf
            <input type="hidden" name="kind" value="site_visit">
            <div class="form-grid-2">
                <div class="field">
                    <label>Date of the visit <span class="req">*</span></label>
                    <input type="date" name="entry_date" required value="{{ now()->toDateString() }}">
                </div>
                <div class="field">
                    <label>Percentage complete seen</label>
                    <input type="number" name="percent" min="0" max="100" step="1" value="{{ $order->percent_complete }}">
                </div>
            </div>
            <div class="field">
                <label>What was seen <span class="req">*</span></label>
                <textarea name="note" rows="2" required maxlength="5000" placeholder="Who was on site, what had been done, what is missing"></textarea>
            </div>
            <div class="field">
                <label>Photo</label>
                <input type="file" name="photo" accept="image/*">
            </div>
            <button class="btn btn-sm btn-primary" type="submit">Record the visit</button>
        </form>
    @endif

    <table class="data">
        <thead><tr><th>Date</th><th>Entry</th><th>% </th><th>What happened</th><th>By</th><th>Photo</th></tr></thead>
        <tbody>
            @forelse ($order->updates as $u)
                <tr>
                    <td>{{ $u->entry_date->format('d M Y') }}</td>
                    <td><span class="tag {{ $u->kind === 'completion' ? 'gold' : 'gray' }}">{{ $u->kindLabel() }}</span></td>
                    <td>{{ $u->percent !== null ? $u->percent.'%' : '—' }}</td>
                    <td>{{ $u->note }}</td>
                    <td class="hint">{{ $u->fundi->full_name ?: ($u->recorder->name ?: 'the office') }}</td>
                    <td>@if ($u->hasPhoto())
                            <a class="photo-thumb" href="{{ route('admin.execution.update.photo', [$order, $u]) }}" data-zoom
                               data-zoom-label="{{ $order->reference }} — {{ $u->kindLabel() }} {{ $u->entry_date->format('d M Y') }}"
                               aria-label="Zoom the {{ $u->kindLabel() }} photo"><img src="{{ route('admin.execution.update.photo', [$order, $u]) }}" alt="{{ $u->kindLabel() }} photo"></a>
                        @else — @endif</td>
                </tr>
            @empty
                <tr><td colspan="6" class="empty-cell">
                    Nothing has been logged on this job yet.
                </td></tr>
            @endforelse
        </tbody>
    </table>
</div>

<div class="card">
    <h2 class="section-head">The hirer's inspection (§10.4 steps 6–7)</h2>
    <p class="help">
        A hirer has no account, so the office records what they said when they inspected, with the signed sheet
        attached. Confirming closes the project as delivered and lets the job be handed to Finance; raising an issue
        puts the order into the dispute flow instead.
    </p>

    @if ($order->confirmed_on)
        <dl class="kv">
            <div><dt>Confirmed by</dt><dd><b>{{ $order->confirmed_by_name }}</b> on {{ $order->confirmed_on->format('d M Y') }}</dd></div>
            <div><dt>What they said</dt><dd>{{ $order->confirmation_note }}</dd></div>
            <div><dt>Signed sheet</dt>
                <dd>@if ($order->confirmation_path)
                        <a class="doc-link" href="{{ route('admin.execution.confirmation', $order) }}" target="_blank" rel="noopener"
                           data-zoom data-zoom-label="{{ $order->reference }} — hirer's confirmation">Open the sheet ↗</a>
                    @else
                        <span class="hint">no file was attached — the words above are the record</span>
                    @endif</dd></div>
        </dl>
    @endif

    @if ($canInspect)
        <form method="POST" action="{{ route('admin.execution.confirm', $order) }}" class="fee-form" enctype="multipart/form-data"
              data-confirm="Record that the hirer confirmed {{ $order->reference }} as complete?" data-title="Record the confirmation">
            @csrf
            <div class="form-grid-2">
                <div class="field">
                    <label>Confirmed by (for the hirer) <span class="req">*</span></label>
                    <input type="text" name="confirmed_by_name" required maxlength="160"
                           placeholder="The name the inspection was done by">
                </div>
                <div class="field">
                    <label>Date of the inspection <span class="req">*</span></label>
                    <input type="date" name="confirmed_on" required value="{{ now()->toDateString() }}">
                </div>
            </div>
            <div class="field">
                <label>What they said <span class="req">*</span></label>
                <textarea name="confirmation_note" rows="2" required maxlength="1000"
                          placeholder="What was inspected and what the hirer accepted"></textarea>
            </div>
            <div class="field">
                <label>Signed sheet</label>
                <input type="file" name="sheet" accept="image/*,.pdf,.doc,.docx">
            </div>
            <button class="btn btn-sm btn-primary" type="submit">Record the confirmation</button>
        </form>

        <form method="POST" action="{{ route('admin.execution.dispute', $order) }}" class="fee-form" enctype="multipart/form-data"
              data-confirm="Record that the hirer raised an issue instead of confirming?" data-title="Record the issue">
            @csrf
            <div class="field">
                <label>The issue the hirer raised <span class="req">*</span></label>
                <textarea name="issue" rows="2" required maxlength="5000"
                          placeholder="Exactly what they would not accept, and why"></textarea>
            </div>
            <div class="field">
                <label>Their photos or document</label>
                <input type="file" name="photo" accept="image/*,.pdf">
            </div>
            <button class="btn btn-sm btn-outline" type="submit">Raise the issue (opens the dispute)</button>
        </form>
    @elseif (! $order->confirmed_on)
        <p class="hint">The inspection is not open yet: the worker first logs the job to completion
            (§10.4 step 5), and their request appears on this sheet.</p>
    @endif
</div>

<div class="card">
    <h2 class="section-head">Disputes (§10.6)</h2>
    @if ($order->disputes->isEmpty())
        <p class="help">No dispute on this order. One is opened here when the hirer refuses to confirm; the worker then
            has {{ WorkOrderDispute::WINDOW_HOURS }} hours to answer on their own portal, the Programme Manager mediates
            and a programme executive decides the outcome.</p>
    @else
        @foreach ($order->disputes as $d)
            <div class="card gaps" style="border-left:4px solid {{ $d->isDecided() ? 'var(--green)' : 'var(--danger)' }};">
                <b>{{ $d->raised_on->format('d M Y H:i') }} — {{ $d->isDecided() ? $d->outcomeLabel() : 'open' }}</b>
                <p style="margin:6px 0;">{{ $d->issue }}</p>
                <dl class="kv">
                    <div><dt>Raised by (for the hirer)</dt><dd>{{ $d->raiser->name ?: 'the office' }}</dd></div>
                    <div><dt>Evidence</dt>
                        <dd>@if ($d->issue_path)
                                <a class="doc-link" href="{{ route('admin.execution.dispute.evidence', [$order, $d]) }}" target="_blank"
                                   rel="noopener" data-zoom data-zoom-label="{{ $order->reference }} — dispute evidence">Open the file ↗</a>
                            @else <span class="hint">none attached</span> @endif</dd></div>
                    <div><dt>Worker's answer</dt>
                        <dd>@if ($d->responded_on)
                                {{ $d->response }}
                                <div class="hint">{{ $d->responder->full_name ?: 'the worker' }} · {{ $d->responded_on->format('d M Y H:i') }}
                                    @if ($d->responded_on->gt($d->dueOn()))<span class="tag gold">after the 48 hours</span>@endif</div>
                            @else
                                <span class="tag {{ $d->isOverdue() ? '' : 'gray' }}">{{ $d->timeLabel() }}</span>
                            @endif</dd></div>
                    <div><dt>Mediation</dt><dd>{{ $d->mediated_by_user
                        ? ($d->mediator->name ?: 'the office').' — '.$d->mediation_note : 'not yet' }}</dd></div>
                    @if ($d->isDecided())
                        <div><dt>Decided</dt><dd>{{ $d->outcomeLabel() }}
                            @if ((float) $d->penalty_ksh > 0) · deduction {{ $d->penaltyLabel() }} @endif
                            <div class="hint">{{ $d->outcome_note }} — {{ $d->decider->name ?: 'a Director' }},
                                {{ $d->decided_on->format('d M Y H:i') }}</div></dd></div>
                    @endif
                </dl>

                @if (! $d->isDecided())
                    <form method="POST" action="{{ route('admin.execution.dispute.mediate', [$order, $d]) }}" class="mini-form"
                          data-title="Record mediation">
                        @csrf
                        <div class="field">
                            <label>What the Programme Manager did <span class="req">*</span></label>
                            <input type="text" name="note" required maxlength="1000" placeholder="Who was spoken to and what was agreed">
                        </div>
                        <button class="btn btn-sm btn-outline" type="submit">Record mediation</button>
                    </form>

                    @if ($isExecutive)
                        <form method="POST" action="{{ route('admin.execution.dispute.decide', [$order, $d]) }}" class="fee-form"
                              data-confirm="Decide this dispute? The outcome lands on the order and the payout." data-title="Decide the dispute">
                            @csrf
                            <div class="form-grid-2">
                                <div class="field">
                                    <label>Outcome <span class="req">*</span></label>
                                    <select name="outcome" required>
                                        @foreach (WorkOrderDispute::OUTCOMES as $key => $label)
                                            <option value="{{ $key }}">{{ $label }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="field">
                                    <label>Deduction from the payout (KSh)</label>
                                    <input type="number" name="penalty_ksh" min="0" step="0.01" value="0">
                                </div>
                            </div>
                            <div class="field">
                                <label>The decision <span class="req">*</span></label>
                                <textarea name="outcome_note" rows="2" required maxlength="1000"
                                          placeholder="What the worker and the hirer each owe now"></textarea>
                            </div>
                            <button class="btn btn-sm btn-primary" type="submit">Decide as a Director</button>
                        </form>
                    @else
                        <p class="hint">Mediation is recorded by staff; the outcome itself is a programme executive's
                            decision — the same rule as issuing a certificate or releasing money.</p>
                    @endif
                @endif
            </div>
        @endforeach
        <p class="hint">A decided dispute is never reopened or rewritten — a fresh issue opens the next dispute on this order.</p>
    @endif
</div>

<div class="card">
    <h2 class="section-head">Money (§10.4 steps 8–12)</h2>
    @if ($payout)
        <dl class="kv">
            <div><dt>Payout</dt><dd><a href="{{ dept_link('admin.payouts.show', ['payout' => $payout]) }}"><b>{{ $payout->reference }}</b></a>
                — {{ $payout->stageLabel() }}</dd></div>
            <div><dt>Calculated as</dt><dd>{{ $payout->calculation }}</dd></div>
            <div><dt>Net to the fundi</dt><dd>{{ $payout->netLabel() }}
                <span class="hint">Finance confirms the hirer's payment before anything is approved or released — this desk does not move money.</span></dd></div>
        </dl>
        @if ($payout->stage === 'released' && $order->status === 'confirmed')
            <form method="POST" action="{{ route('admin.execution.close', $order) }}" class="fee-form" data-title="Close the order">
                @csrf
                <div class="field">
                    <label>Why the order is closed <span class="req">*</span></label>
                    <input type="text" name="closure_note" required maxlength="1000"
                           value="Payout {{ $payout->reference }} released.">
                </div>
                <button class="btn btn-sm btn-primary" type="submit">Close the order</button>
            </form>
        @endif
    @elseif ($order->assignee_type === 'ctp')
        <p class="help">This order was awarded as a <b>CTP attachment</b>, so there is no E-Fundi payout to open —
            attachment is training and is credited on the trainee's record (§10.2). The hirer's invoice still sits with
            Finance on the project.</p>
    @elseif ($order->status === 'confirmed')
        <p class="help">Handing the job over opens the payout row on Module 11's register, already behind the gate that
            waits for the hirer's money. Materials are not typed in here: they are taken from what the store actually
            issued above.</p>
        <form method="POST" action="{{ route('admin.execution.handover', $order) }}" class="fee-form"
              data-confirm="Open the payout for {{ $order->reference }} on the Module 11 register?" data-title="Hand to Finance">
            @csrf
            <div class="form-grid-2">
                <div class="field">
                    <label>Service commission (KSh)</label>
                    <input type="number" name="commission_ksh" min="0" step="0.01" value="0">
                    <span class="hint">§10.4 step 10 — what the programme keeps</span>
                </div>
                <div class="field">
                    <label>Mobilization credit (KSh)</label>
                    <input type="number" name="mobilization_ksh" min="0" step="0.01" value="0">
                    <span class="hint">what the CTP who mobilised is owed, if it is settled out of this job</span>
                </div>
            </div>
            <div class="field">
                <label>Note for Finance</label>
                <input type="text" name="note" maxlength="1000"
                       placeholder="Anything the Finance Officer has to read before confirming the hirer's payment">
            </div>
            <p class="hint">Deductions carried across automatically: materials {{ Finance::ksh($order->materialsIssuedKsh()) }}
                @if ($penalties > 0) · decided deductions {{ Finance::ksh($penalties) }} @endif.</p>
            <button class="btn btn-sm btn-primary" type="submit">Hand to Finance and open the payout</button>
        </form>
    @else
        <p class="hint">Nothing is owed yet — the payout is opened once the hirer has confirmed the work.</p>
    @endif

    @if (in_array($order->status, ['issued', 'accepted', 'in_progress', 'completion_submitted', 'disputed'], true))
        <form method="POST" action="{{ route('admin.execution.cancel', $order) }}" class="fee-form"
              data-confirm="Stop this order? It stays on the register with the reason." data-title="Stop the order">
            @csrf
            <div class="field">
                <label>Why the job is stopped <span class="req">*</span></label>
                <input type="text" name="closure_note" required minlength="10" maxlength="1000"
                       placeholder="The hirer cancelled it, the worker could not start, the site was lost">
            </div>
            <button class="btn btn-sm btn-outline" type="submit">Stop this order</button>
        </form>
    @endif
</div>

<p class="hint">
    What stays where: the materials <i>plan</i> and the applications are on the project (Module 1), the worker was found
    on the matching desk (Module 6), and every figure the fundi is paid is calculated and released on the payout
    register (Module 11). This sheet only records the delivery and the decisions on it.
</p>
<script>
    // The split is a repeater: + drops another stage box so a four-stage schedule is set in one save.
    document.addEventListener('click', function (e) {
        function relabel(box) {
            const rows = box.querySelectorAll('.split-row');
            rows.forEach(function (row, i) {
                const input = row.querySelector('input');
                input.name = 'split[' + i + ']';
                input.setAttribute('aria-label', 'Stage ' + (i + 1) + ' share');
                row.querySelector('[data-split-remove]').hidden = rows.length < 2;
            });
        }

        const add = e.target.closest('[data-split-add]');
        if (add) {
            const box = document.getElementById(add.dataset.splitAdd);
            const rows = box.querySelectorAll('.split-row');
            if (rows.length >= 6) return;

            const row = rows[rows.length - 1].cloneNode(true);
            row.querySelector('input').value = '';
            box.append(row);
            relabel(box);
            row.querySelector('input').focus();

            return;
        }

        const drop = e.target.closest('[data-split-remove]');
        if (drop) {
            const box = drop.closest('.split-rows');
            drop.closest('.split-row').remove();
            relabel(box);
        }
    });
</script>
@endsection
