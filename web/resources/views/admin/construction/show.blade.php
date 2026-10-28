@extends('layouts.admin')
@section('title', 'Construction file '.$project->reference)

@php
    use App\Models\Project;
    use App\Models\SiteAssessment;
    use App\Models\SiteInspectionItem;
    use App\Support\Finance;

    $pill = ['draft' => 'pending_review', 'open' => 'pending_review', 'awarded' => 'awarded', 'closed' => 'active'];
    $ipill = ['scheduled' => 'pending_review', 'reported' => 'awarded', 'signed_off' => 'active', 'cancelled' => 'rejected'];
    $live = $project->boqItems->where('status', 'live')->values();
    $withdrawn = $project->boqItems->where('status', '!=', 'live')->values();
    $canBuild = in_array($project->status, ['open', 'awarded', 'closed'], true);
@endphp

@section('content')
<p class="back"><a href="{{ route('admin.construction.index') }}">&larr; Construction files</a></p>

<div class="proj-head">
    <div>
        <span class="status {{ $pill[$project->status] ?? 'pending_review' }}">{{ Project::STATUSES[$project->status] ?? $project->status }}</span>
        @if ($openFailures)<span class="tag">{{ $openFailures }} failed item(s) open</span>@endif
        @if ($project->variations->where('status', 'proposed')->isNotEmpty())
            <span class="tag gold">variation waiting on a Director</span>
        @endif
        <h1 style="margin:8px 0 4px;">{{ $project->reference }} — {{ $project->title }}</h1>
        <div class="hint">
            {{ $project->trade ?: 'trade not set' }} ·
            {{ collect([$project->county, $project->ward])->filter()->implode(', ') ?: 'place not set' }} ·
            hirer {{ $project->hirer->organisation ?: 'not named' }}<br>
            Site read {{ $project->assessments->count() }} time(s) · BoQ {{ $live->count() }} live line(s)
            · {{ $project->labourEntries->count() }} labour day(s)
            · {{ $project->inspections->count() }} inspection(s)
            @if ($order) · order {{ $order->reference }} at {{ $order->statusLabel() }} @endif
        </div>
    </div>
    <div class="page-actions">
        <a class="btn btn-sm btn-outline" href="{{ dept_link('admin.projects.show', ['project' => $project]) }}">The project</a>
        @if ($order)
            <a class="btn btn-sm btn-outline" href="{{ dept_link('admin.execution.show', ['order' => $order]) }}">Delivery sheet</a>
        @endif
        @if ($payout)
            <a class="btn btn-sm btn-outline" href="{{ dept_link('admin.payouts.show', ['payout' => $payout]) }}">{{ $payout->reference }}</a>
        @endif
    </div>
</div>

<div class="card">
    <h2 class="section-head">What the file says so far</h2>
    <div class="stat-row">
        <div class="stat"><small>BoQ — materials as priced</small><b>{{ Finance::ksh($boqTotal) }}</b></div>
        <div class="stat"><small>Labour counted on site</small><b>{{ Finance::ksh($labourCost) }}</b></div>
        <div class="stat"><small>Order agreed amount</small><b>{{ $order ? Finance::ksh((float) $order->agreed_amount_ksh) : '—' }}</b></div>
        <div class="stat"><small>Changes approved</small><b>{{ $project->variations->where('status', 'approved')->count() }}</b></div>
        <div class="stat"><small>Reports signed off</small><b>{{ $project->inspections->where('status', 'signed_off')->count() }}</b></div>
    </div>
    <p class="hint">
        The BoQ total and the labour total are the sum of their lines, never a typed figure. They are what the office
        reads when it prices a variation or questions a payout; the payout itself is calculated on
        {!! dept_ref('admin.payouts.index', 'the Finance register') !!}.
    </p>
</div>

<div class="card">
    <h2 class="section-head">Site assessment</h2>
    <p class="help">
        The first thing a construction job needs is somebody standing on the ground writing down what it is like —
        soil, access, water and power, the risks, and what should happen next. A later visit is another assessment
        under this one; an earlier reading is never edited away.
    </p>

    @if ($canBuild)
        <form method="POST" action="{{ route('admin.construction.assess', $project) }}" class="fee-form"
              enctype="multipart/form-data" data-title="File a site assessment">
            @csrf
            <div class="form-grid-2">
                <div class="field">
                    <label>Date of the visit <span class="req">*</span></label>
                    <input type="date" name="visited_on" required value="{{ now()->toDateString() }}">
                </div>
                <div class="field">
                    <label>Recommendation <span class="req">*</span></label>
                    <select name="recommendation" required>
                        @foreach (SiteAssessment::RECOMMENDATIONS as $key => $label)
                            <option value="{{ $key }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="field">
                    <label>Ground and soil</label>
                    <input type="text" name="ground_conditions" maxlength="500" placeholder="Laterite, black cotton, waterlogged…">
                </div>
                <div class="field">
                    <label>Access for vehicles and materials</label>
                    <input type="text" name="access" maxlength="500" placeholder="Can a tipper reach the site?">
                </div>
                <div class="field">
                    <label>Water and power on site</label>
                    <input type="text" name="utilities" maxlength="500">
                </div>
                <div class="field">
                    <label>Risks seen</label>
                    <input type="text" name="risks" maxlength="1000" placeholder="Flooding, unstable boundary, live wires">
                </div>
            </div>
            <div class="field">
                <label>Findings <span class="req">*</span></label>
                <textarea name="findings" rows="3" required maxlength="5000"
                          placeholder="What the visit found and what it means for the design"></textarea>
            </div>
            <div class="field">
                <label>Site photos</label>
                <input type="file" name="photo" accept="image/*">
            </div>
            <button class="btn btn-sm btn-primary" type="submit">File the assessment</button>
        </form>
    @endif

    @if ($project->assessments->isEmpty())
        <p class="hint">Nothing has been written about this site yet. Price the BoQ after somebody has read the ground.</p>
    @else
        <table class="data">
            <thead><tr><th>Visited</th><th>The site</th><th>Recommendation</th><th>By</th><th>Photo</th></tr></thead>
            <tbody>
                @foreach ($project->assessments as $a)
                    <tr>
                        <td>{{ $a->visited_on->format('d M Y') }}</td>
                        <td>
                            <dl class="kv">
                                @foreach (['Ground' => $a->ground_conditions, 'Access' => $a->access,
                                    'Utilities' => $a->utilities, 'Risks' => $a->risks] as $label => $value)
                                    @if ($value)<div><dt>{{ $label }}</dt><dd>{{ $value }}</dd></div>@endif
                                @endforeach
                                <div><dt>Findings</dt><dd>{{ $a->findings }}</dd></div>
                            </dl>
                        </td>
                        <td><b>{{ $a->recommendationLabel() }}</b></td>
                        <td class="hint">{{ $a->assessor->name ?: 'the office' }}</td>
                        <td>@if ($a->hasPhoto())
                                <a class="photo-thumb" href="{{ route('admin.construction.assessment.photo', $a) }}" data-zoom
                                   data-zoom-label="{{ $project->reference }} — site {{ $a->visited_on->format('d M Y') }}"
                                   aria-label="Zoom the site photo"><img src="{{ route('admin.construction.assessment.photo', $a) }}" alt="Site photo"></a>
                            @else — @endif</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif
</div>

<div class="card">
    <h2 class="section-head">Bill of quantities</h2>
    <p class="help">
        Every line the build needs, priced: drawing or detail reference, the trade that does it, the unit, the
        quantity and the cost of one unit. The total below is what these lines add up to — a BoQ total is never typed.
        A line that turns out not to be built is withdrawn with a reason and stays on the file; a line an approved
        variation brought in cannot be re-priced here, because that figure was agreed with the hirer.
    </p>

    @if ($canBuild)
        <form method="POST" action="{{ route('admin.construction.boq', $project) }}" class="fee-form" data-title="Save the bill of quantities">
            @csrf
            <table class="data">
                <thead>
                    <tr><th>Drawing / detail</th><th>Trade</th><th>Description of the work <span class="req">*</span></th>
                        <th>Unit</th><th>Quantity</th><th>Cost each (KSh)</th><th>Worth</th></tr>
                </thead>
                <tbody id="boq-rows">
                    @forelse ($live as $i => $line)
                        <tr class="boq-row">
                            <td><input type="hidden" name="boq[{{ $i }}][id]" value="{{ $line->id }}">
                                <input type="text" name="boq[{{ $i }}][drawing_ref]" maxlength="80" value="{{ $line->drawing_ref }}" placeholder="A-101"></td>
                            <td><select name="boq[{{ $i }}][trade]" aria-label="Trade">
                                    <option value="">—</option>
                                    @foreach ($trades as $key => $label)
                                        <option value="{{ $key }}" @selected($line->trade === $key)>{{ $label }}</option>
                                    @endforeach
                                </select></td>
                            <td><input type="text" name="boq[{{ $i }}][description]" maxlength="500" required value="{{ $line->description }}"></td>
                            <td><input type="text" name="boq[{{ $i }}][unit]" maxlength="30" value="{{ $line->unit }}" aria-label="Unit"></td>
                            <td><input type="number" name="boq[{{ $i }}][quantity]" step="0.001" min="0" value="{{ (float) $line->quantity }}" aria-label="Quantity"></td>
                            <td><input type="number" name="boq[{{ $i }}][unit_cost_ksh]" step="0.01" min="0" value="{{ (float) $line->unit_cost_ksh }}" aria-label="Cost each"></td>
                            <td class="boq-line-total">{{ Finance::ksh($line->totalKsh()) }}</td>
                        </tr>
                    @empty
                        <tr class="boq-row">
                            <td><input type="hidden" name="boq[0][id]" value="0">
                                <input type="text" name="boq[0][drawing_ref]" maxlength="80" placeholder="A-101"></td>
                            <td><select name="boq[0][trade]" aria-label="Trade">
                                    <option value="">—</option>
                                    @foreach ($trades as $key => $label)<option value="{{ $key }}">{{ $label }}</option>@endforeach
                                </select></td>
                            <td><input type="text" name="boq[0][description]" maxlength="500" required placeholder="Excavate and prepare the foundation"></td>
                            <td><input type="text" name="boq[0][unit]" maxlength="30" value="m2" aria-label="Unit"></td>
                            <td><input type="number" name="boq[0][quantity]" step="0.001" min="0" value="0" aria-label="Quantity"></td>
                            <td><input type="number" name="boq[0][unit_cost_ksh]" step="0.01" min="0" value="0" aria-label="Cost each"></td>
                            <td class="boq-line-total">0</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
            <button class="btn btn-sm btn-outline" type="button" data-boq-add>Add a line</button>
            <button class="btn btn-sm btn-primary" type="submit">Save the BoQ</button>
            <span class="hint">Saving corrects the lines in place — the {!! dept_ref('admin.activity.index', 'activity trail') !!} keeps each quantity and price change.</span>
        </form>
    @endif

    <p class="hint"><b>Total of the live lines: {{ Finance::ksh($boqTotal) }}</b> over {{ $live->count() }} line(s).</p>

    @if ($withdrawn->isNotEmpty())
        <table class="data">
            <thead><tr><th>Line taken off the build</th><th>Worth</th><th>Why</th><th>By</th><th></th></tr></thead>
            <tbody>
                @foreach ($withdrawn as $line)
                    <tr>
                        <td>{{ $line->description }}
                            <div class="hint">{{ $line->sourceLabel() }}
                                @if ($line->variation->exists) · variation {{ $line->variation->reference }} @endif</div></td>
                        <td><s>{{ Finance::ksh($line->totalKsh()) }}</s></td>
                        <td>{{ $line->withdraw_note }}</td>
                        <td class="hint">{{ $line->withdrawer->name ?: 'the office' }}</td>
                        <td>
                            @if ($canBuild && ! $line->isLocked())
                                <form method="POST" action="{{ route('admin.construction.boq', $project) }}" class="mini-form"
                                      data-confirm="Put this line back on the build?">
                                    @csrf
                                    <input type="hidden" name="boq[0][id]" value="{{ $line->id }}">
                                    <input type="hidden" name="boq[0][drawing_ref]" value="{{ $line->drawing_ref }}">
                                    <input type="hidden" name="boq[0][description]" value="{{ $line->description }}">
                                    <input type="hidden" name="boq[0][unit]" value="{{ $line->unit }}">
                                    <input type="hidden" name="boq[0][quantity]" value="{{ (float) $line->quantity }}">
                                    <input type="hidden" name="boq[0][unit_cost_ksh]" value="{{ (float) $line->unit_cost_ksh }}">
                                    <button class="btn btn-sm btn-outline" type="submit">Put it back</button>
                                </form>
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    @if ($canBuild && $live->reject(fn ($line) => $line->isLocked())->isNotEmpty())
        <details class="need-form">
            <summary>Withdraw a line from the build</summary>
            <p class="hint">A withdrawn line is not deleted — it stays on the BoQ with the reason and off the total.</p>
            <table class="data">
                <thead><tr><th>Live line</th><th>Worth</th><th>Withdraw</th></tr></thead>
                <tbody>
                    @foreach ($live->reject(fn ($line) => $line->isLocked()) as $line)
                        <tr>
                            <td>{{ $line->description }}
                                <div class="hint">{{ $line->tradeLabel() ?: 'any trade' }} · {{ (float) $line->quantity }} {{ $line->unit }}</div></td>
                            <td>{{ Finance::ksh($line->totalKsh()) }}</td>
                            <td>
                                <form method="POST" action="{{ route('admin.construction.withdraw', $line) }}" class="mini-form"
                                      data-confirm="Withdraw this line from the build?">
                                    @csrf
                                    <div class="field">
                                        <label>Why it is off the build <span class="req">*</span></label>
                                        <input type="text" name="withdraw_note" required maxlength="1000">
                                    </div>
                                    <button class="btn btn-sm btn-outline" type="submit">Withdraw</button>
                                </form>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </details>
    @endif
</div>

<div class="card">
    <h2 class="section-head">Labour on site</h2>
    <p class="help">Each line is a day somebody worked, whose contract they were under, and the rate they are counted at.
        The worker's own progress and photographs are logged on the delivery sheet — this is the attendance the
        construction cost is built from.</p>

    @if ($canBuild)
        <form method="POST" action="{{ route('admin.construction.labour', $project) }}" class="fee-form" data-title="Count a day's labour">
            @csrf
            <div class="form-grid-2">
                <div class="field">
                    <label>Date worked <span class="req">*</span></label>
                    <input type="date" name="worked_on" required value="{{ now()->toDateString() }}">
                </div>
                <div class="field">
                    <label>Worker <span class="req">*</span></label>
                    <input type="text" name="worker_name" required maxlength="160" placeholder="The person who turned up">
                </div>
                <div class="field">
                    <label>E-Fundi on the register</label>
                    <select name="fundi_id">
                        <option value="">not on the register</option>
                        @foreach ($fundis as $f)
                            <option value="{{ $f->id }}">{{ $f->full_name }}@if ($f->ef_code) · {{ $f->ef_code }}@endif</option>
                        @endforeach
                    </select>
                </div>
                <div class="field">
                    <label>Sub-contractor</label>
                    <input type="text" name="sub_contractor" maxlength="160" placeholder="Who engaged them, if not the programme">
                </div>
                <div class="field">
                    <label>Trade</label>
                    <select name="trade">
                        <option value="">—</option>
                        @foreach ($trades as $key => $label)<option value="{{ $key }}">{{ $label }}</option>@endforeach
                    </select>
                </div>
                <div class="field">
                    <label>Hours <span class="req">*</span></label>
                    <input type="number" name="hours" required min="0.25" max="24" step="0.25" value="8">
                </div>
                <div class="field">
                    <label>Hourly rate (KSh)</label>
                    <input type="number" name="rate_ksh" min="0" step="0.01" value="0">
                </div>
                <div class="field">
                    <label>Note</label>
                    <input type="text" name="note" maxlength="500" placeholder="What they did that day">
                </div>
            </div>
            <button class="btn btn-sm btn-primary" type="submit">Count the day</button>
        </form>
    @endif

    <table class="data">
        <thead><tr><th>Date</th><th>Worker</th><th>Under</th><th>Trade</th><th>Hours</th><th>Rate</th><th>Cost</th><th>By</th></tr></thead>
        <tbody>
            @forelse ($project->labourEntries as $d)
                <tr>
                    <td>{{ $d->worked_on->format('d M Y') }}</td>
                    <td><b>{{ $d->worker_name }}</b>
                        <div class="hint">{{ $d->fundi->full_name ? $d->fundi->full_name.' · '.($d->fundi->ef_code ?: 'no EF code') : 'not on the register' }}</div></td>
                    <td>{{ $d->sub_contractor ?: '—' }}</td>
                    <td>{{ $d->tradeLabel() ?: '—' }}</td>
                    <td>{{ (float) $d->hours }}</td>
                    <td>{{ Finance::ksh((float) $d->rate_ksh) }}</td>
                    <td>{{ Finance::ksh($d->costKsh()) }}</td>
                    <td class="hint">{{ $d->recorder->name ?: 'the office' }}</td>
                </tr>
            @empty
                <tr><td colspan="8" class="empty-cell">
                    No day counted yet. Labour is what makes a construction cost auditable — one line per person per day.
                </td></tr>
            @endforelse
        </tbody>
    </table>
    <p class="hint">Total labour counted on this file: <b>{{ Finance::ksh($labourCost) }}</b>.</p>
</div>

<div class="card">
    <h2 class="section-head">Site inspections</h2>
    <p class="help">
        A visit is scheduled with the items to be checked listed, one per line. The inspector marks every item pass,
        fail or not applicable and writes the report; a failed item is answered by the worker with the photograph of the
        rework on <a href="{{ route('portal.fixes') }}">their own portal</a>, re-checked by the inspector, and only then
        can the report be signed off. Nothing here changes the order's progress — that is the delivery sheet.
    </p>

    @if ($canBuild)
        <form method="POST" action="{{ route('admin.construction.schedule', $project) }}" class="fee-form" data-title="Schedule an inspection">
            @csrf
            <div class="form-grid-2">
                <div class="field">
                    <label>Date scheduled for <span class="req">*</span></label>
                    <input type="date" name="scheduled_on" required value="{{ now()->addDays(3)->toDateString() }}">
                </div>
                <div class="field">
                    <label>Inspector</label>
                    <select name="inspector_user">
                        @foreach ($staff as $u)
                            <option value="{{ $u->id }}" @selected($u->id === auth()->id())>{{ $u->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="field">
                    <label>What this visit is for <span class="req">*</span></label>
                    <input type="text" name="purpose" required maxlength="500" placeholder="Foundation check before the blind">
                </div>
            </div>
            <div class="field">
                <label>Items to check — one per line <span class="req">*</span></label>
                <textarea name="items" rows="4" required maxlength="6000"
                          placeholder="Setting out to the drawings&#10;Foundation depth and cover&#10;Wall thickness and lintel bearings"></textarea>
            </div>
            <button class="btn btn-sm btn-primary" type="submit">Schedule the visit</button>
        </form>
    @endif

    @if ($project->inspections->isEmpty())
        <p class="hint">No inspection has been scheduled on this file yet.</p>
    @endif

    @foreach ($project->inspections as $ins)
        @php $openReport = in_array($ins->status, ['scheduled', 'reported'], true); @endphp
        <div class="card gaps" style="border-left:4px solid {{ $ins->status === 'signed_off' ? 'var(--green)' : ($ins->openFailures() ? 'var(--danger)' : 'var(--lime)') }};">
            <b>{{ $ins->scheduled_on->format('d M Y') }} — {{ $ins->purpose }}</b>
            <div class="hint">
                <span class="status {{ $ipill[$ins->status] ?? 'pending_review' }}">{{ $ins->statusLabel() }}</span>
                · inspector {{ $ins->inspector->name ?: 'not set' }}
                @if ($ins->inspected_on) · visited {{ $ins->inspected_on->format('d M Y') }} @endif
                @if ($ins->signed_off_on) · signed off {{ $ins->signed_off_on->format('d M Y H:i') }} by {{ $ins->signer->name ?: 'the inspector' }} @endif
                @if ($ins->workOrder->exists) · against {{ $ins->workOrder->reference }} @endif
            </div>

            @if ($ins->report_note)<p style="margin:8px 0 4px;">{{ $ins->report_note }}</p>@endif

            @if ($ins->hasPhoto())
                <a class="photo-thumb" href="{{ route('admin.construction.inspection.photo', $ins) }}" data-zoom
                   data-zoom-label="{{ $project->reference }} — inspection {{ $ins->scheduled_on->format('d M Y') }}"
                   aria-label="Zoom the inspection photo"><img src="{{ route('admin.construction.inspection.photo', $ins) }}" alt="Inspection photo"></a>
            @endif

            @if ($openReport)
                <form method="POST" action="{{ route('admin.construction.report', $ins) }}" class="fee-form"
                      enctype="multipart/form-data" data-title="File the report of this visit">
                @csrf
            @endif
                <table class="data">
                    <thead><tr><th>Item the inspector was asked to check</th><th>Mark</th>
                        <th>What was found</th><th>The worker's proof</th><th>Re-check</th></tr></thead>
                    <tbody>
                        @foreach ($ins->items as $item)
                            <tr>
                                <td>{{ $item->item }}</td>
                                <td>
                                    @if ($openReport)
                                        <select name="items[{{ $item->id }}][result]" aria-label="Result for {{ str($item->item)->limit(30) }}">
                                            @foreach (SiteInspectionItem::RESULTS as $key => $label)
                                                <option value="{{ $key }}" @selected($item->result === $key)>{{ $label }}</option>
                                            @endforeach
                                        </select>
                                    @else
                                        <span class="status {{ $item->result === 'fail' ? 'rejected' : ($item->result === 'pending' ? 'pending_review' : 'active') }}">
                                            {{ $item->resultLabel() }}</span>
                                    @endif
                                </td>
                                <td>
                                    @if ($openReport)
                                        <input type="text" name="items[{{ $item->id }}][note]" maxlength="1000"
                                               value="{{ $item->note }}" placeholder="What was seen — a failure must be explained">
                                    @else
                                        {{ $item->note ?: '—' }}
                                    @endif
                                    @if ($item->rechecked_on)
                                        <div class="hint">re-checked {{ $item->rechecked_on->format('d M Y') }}</div>
                                    @endif
                                </td>
                                <td class="hint">
                                    @if ($item->hasProof())
                                        <a class="doc-link" href="{{ route('admin.construction.item.proof', $item) }}" target="_blank"
                                           rel="noopener" data-zoom data-zoom-label="{{ $item->item }} — proof of the fix">Proof ↗</a>
                                        <div>{{ $item->proof_note }}</div>
                                        <div>{{ $item->prover->full_name ?: 'the worker' }} · {{ $item->proof_on?->format('d M Y H:i') }}</div>
                                    @elseif ($item->result === 'fail')
                                        waiting for the worker to upload it
                                    @else — @endif
                                </td>
                                <td>
                                    @if ($item->result === 'fail')
                                        <span class="tag {{ $item->isCleared() ? 'gray' : '' }}">{{ $item->isCleared() ? 're-checked' : 'open' }}</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>

                @if ($openReport)
                    <div class="form-grid-2">
                        <div class="field">
                            <label>Date of the visit <span class="req">*</span></label>
                            <input type="date" name="inspected_on" required
                                   value="{{ $ins->inspected_on?->toDateString() ?? now()->toDateString() }}">
                        </div>
                        <div class="field">
                            <label>Site photos</label>
                            <input type="file" name="photo" accept="image/*">
                        </div>
                    </div>
                    <div class="field">
                        <label>The report <span class="req">*</span></label>
                        <textarea name="report_note" rows="2" required maxlength="5000"
                                  placeholder="What the visit means for the build">{{ $ins->report_note }}</textarea>
                    </div>
                    <button class="btn btn-sm btn-primary" type="submit">File the report</button>
                    @if ($ins->unmarkedCount())
                        <span class="hint">{{ $ins->unmarkedCount() }} item(s) still carry no mark — every one has to be checked before the report stands.</span>
                    @endif
                    </form>
                @else
                    <p class="hint">This report is signed off, so its marks are read-only here. A fresh finding is a new
                        inspection; a wrong mark on a signed sheet is answered by the next visit, not by rewriting this one.</p>
                @endif

            @foreach ($ins->failures() as $item)
                @if ($openReport && (! $item->isCleared() || $item->hasProof()))
                    <div style="margin-top:12px;padding:10px 12px;border:1px solid var(--line);border-radius:10px;">
                        <b>{{ $item->item }}</b>
                        <div class="hint">{{ $item->resultLabel() }}
                            @if ($item->proof_on) · proof from {{ $item->prover->full_name ?: 'the worker' }} on {{ $item->proof_on->format('d M Y H:i') }} @endif</div>
                        @if ($item->proof_note)<div class="hint">They said: {{ $item->proof_note }}</div>@endif
                        @if ($item->note)<div class="hint">The inspector wrote: {{ $item->note }}</div>@endif

                        <form method="POST" action="{{ route('admin.construction.recheck', $item) }}" class="mini-form" data-title="Re-check the failed item">
                            @csrf
                            <div class="form-grid-2">
                                <div class="field">
                                    <label>On the re-check</label>
                                    <select name="result">
                                        <option value="pass">Passed — the rework is acceptable</option>
                                        <option value="fail">Still failed</option>
                                        <option value="n_a">Not applicable after all</option>
                                    </select>
                                </div>
                                <div class="field">
                                    <label>What the re-check found <span class="req">*</span></label>
                                    <input type="text" name="note" required maxlength="1000">
                                </div>
                            </div>
                            <button class="btn btn-sm btn-outline" type="submit">Record the re-check</button>
                            @if (! $item->hasProof())
                                <span class="hint">This item has no proof of a fix yet — the worker uploads it on their portal first.</span>
                            @endif
                        </form>
                    </div>
                @endif
            @endforeach

            @if ($ins->status === 'reported')
                <form method="POST" action="{{ route('admin.construction.signOff', $ins) }}" class="fee-form"
                      data-confirm="Sign this inspection report off?" data-title="Sign off the report">
                    @csrf
                    <div class="field">
                        <label>Signing note <span class="req">*</span></label>
                        <input type="text" name="sign_off_note" required maxlength="1000"
                               placeholder="What the inspector certifies, and any item the site still owes">
                    </div>
                    <button class="btn btn-sm btn-primary" type="submit">Sign off the report</button>
                    @if ($ins->openFailures())
                        <span class="hint">{{ $ins->openFailures() }} failed item(s) still need the worker's proof and a re-check.</span>
                    @endif
                </form>
            @endif
        </div>
    @endforeach
</div>

<div class="card">
    <h2 class="section-head">Variations — work the design did not carry</h2>
    <p class="help">
        A Construction Manager raises the change with its cost, why the design has to move, the materials it needs and
        the days it adds. Only a programme executive approves or rejects it. An approved variation adds its line to the
        BoQ, lifts the agreed amount on the work order, re-prices the stages nobody has signed yet, extends the
        deadline, and corrects a payout that has not passed the release gate. A rejected one stays on the file —
        "we asked and it was refused" is part of the record.
    </p>

    @if ($canBuild && $order)
        <form method="POST" action="{{ route('admin.construction.variation', $project) }}" class="fee-form" data-title="Raise a variation">
            @csrf
            <div class="field">
                <label>The additional work <span class="req">*</span></label>
                <textarea name="scope" rows="2" required maxlength="5000"
                          placeholder="Exactly what has to be built that the drawings did not carry"></textarea>
            </div>
            <div class="form-grid-2">
                <div class="field">
                    <label>Why it is needed <span class="req">*</span></label>
                    <input type="text" name="reason" required maxlength="1000" placeholder="Rock found on the second footing">
                </div>
                <div class="field">
                    <label>Additional cost (KSh) <span class="req">*</span></label>
                    <input type="number" name="extra_cost_ksh" required min="0" step="0.01" value="0">
                </div>
                <div class="field">
                    <label>Materials the change needs</label>
                    <input type="text" name="extra_materials" maxlength="1000">
                </div>
                <div class="field">
                    <label>Extra days on the programme</label>
                    <input type="number" name="extra_days" min="0" max="3650" value="0">
                </div>
            </div>
            <button class="btn btn-sm btn-primary" type="submit">Raise it against {{ $order->reference }}</button>
        </form>
    @elseif ($canBuild)
        <p class="hint">A variation is priced against a work order. This project has none yet — it has to be awarded and
            its order issued on {!! dept_ref('admin.execution.index', 'the delivery register') !!} first.</p>
    @endif

    @foreach ($variations as $v)
        <div class="card gaps" style="border-left:4px solid {{ $v->status === 'approved' ? 'var(--green)' : ($v->status === 'rejected' ? 'var(--muted)' : 'var(--lime)') }};">
            <b>{{ $v->reference }} — {{ $v->statusLabel() }}</b>
            <div class="hint">raised {{ $v->created_at->format('d M Y') }} by {{ $v->raiser->name ?: 'the office' }}
                against {{ $v->workOrder->reference ?: 'the order' }} · {{ Finance::ksh((float) $v->extra_cost_ksh) }}
                @if ((int) $v->extra_days > 0) · {{ $v->extra_days }} extra days @endif</div>
            <p style="margin:6px 0;">{{ $v->scope }}</p>
            <dl class="kv">
                <div><dt>Why</dt><dd>{{ $v->reason }}</dd></div>
                @if ($v->extra_materials)<div><dt>Materials</dt><dd>{{ $v->extra_materials }}</dd></div>@endif
                @if ($v->decision_note)
                    <div><dt>Decision</dt><dd>{{ $v->decision_note }}
                        <div class="hint">{{ $v->decider->name ?: 'a Director' }} · {{ $v->decided_on?->format('d M Y H:i') }}</div></dd></div>
                @endif
            </dl>

            @if ($v->status === 'proposed')
                @if ($isExecutive)
                    <form method="POST" action="{{ route('admin.construction.decide', $v) }}" class="fee-form"
                          data-confirm="Decide {{ $v->reference }}? An approval moves the order, its stages and the BoQ." data-title="Decide the variation">
                        @csrf
                        <div class="form-grid-2">
                            <div class="field">
                                <label>Decision <span class="req">*</span></label>
                                <select name="decision">
                                    <option value="approved">Approved — carry it into the build</option>
                                    <option value="rejected">Rejected — the design stands</option>
                                </select>
                            </div>
                            <div class="field">
                                <label>The decision <span class="req">*</span></label>
                                <input type="text" name="decision_note" required maxlength="1000"
                                       placeholder="What the hirer and the fundi are told">
                            </div>
                        </div>
                        <button class="btn btn-sm btn-primary" type="submit">Decide as a Director</button>
                    </form>
                @else
                    <p class="hint">Staff raise a variation; only a programme executive decides it — the same gate that
                        issues a certificate or releases money.</p>
                @endif
            @endif
        </div>
    @endforeach
</div>

<script>
    // The BoQ is a repeater: + drops another empty line and renumbers the table so every row keeps its index.
    document.addEventListener('click', function (e) {
        const add = e.target.closest('[data-boq-add]');
        if (! add) return;

        const box = document.getElementById('boq-rows');
        const rows = box.querySelectorAll('.boq-row');
        if (rows.length >= 60) return;

        const row = rows[rows.length - 1].cloneNode(true);
        row.querySelectorAll('input, select').forEach(function (f) {
            if (f.type === 'hidden') { f.value = '0'; return; }
            if (f.tagName === 'SELECT') { f.selectedIndex = 0; return; }
            f.value = f.getAttribute('aria-label') === 'Unit' ? 'each' : '';
        });
        const total = row.querySelector('.boq-line-total');
        if (total) total.textContent = '0';
        box.append(row);

        box.querySelectorAll('.boq-row').forEach(function (r, i) {
            r.querySelectorAll('input, select').forEach(function (f) {
                f.name = f.name.replace(/\[\d+\]/, '[' + i + ']');
            });
        });

        row.querySelector('input[type="text"]').focus();
    });

    // A line's worth is shown as it is typed, so the office sees the arithmetic before it saves it.
    document.addEventListener('input', function (e) {
        const row = e.target.closest('.boq-row');
        if (! row) return;

        const qty = row.querySelector('[aria-label="Quantity"]');
        const cost = row.querySelector('[aria-label="Cost each"]');
        const out = row.querySelector('.boq-line-total');
        if (qty && cost && out) {
            out.textContent = (parseFloat(qty.value || 0) * parseFloat(cost.value || 0)).toLocaleString('en-KE');
        }
    });
</script>
@endsection
