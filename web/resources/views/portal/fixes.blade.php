@extends('layouts.member')
@section('title', 'Items to put right')

@section('content')
<div class="page-intro">
    <p class="sub">When the inspector visits your site and marks something <b>failed</b>, it comes here.
        You answer it with what you did and the photograph of the rework — the same item, not a new one. The inspector
        then re-checks it on the office's side; only they can mark it passed, and a report cannot be signed off while
        an item of yours is still standing.</p>
    <div class="member-stats">
        <div class="stat"><small>Waiting on your proof</small><b>{{ $fixes->count() }}</b></div>
        <div class="stat"><small>Re-checked already</small><b>{{ $cleared->count() }}</b></div>
    </div>
</div>

@if ($fixes->isEmpty())
    <div class="card">
        <div class="section-head"><h3>Nothing is flagged on your sites</h3></div>
        <p class="help">An inspection item appears here only when the inspector marks it failed and it has not been
            re-checked yet. Keep logging your days on
            <a href="{{ route('portal.work') }}">My work orders</a> — the inspector's visit is arranged off the back of
            that log.</p>
    </div>
@else
    <div class="card">
        <div class="section-head"><h3>What the inspector flagged</h3></div>
        @foreach ($fixes as $item)
            @php $ins = $item->inspection; @endphp
            <div class="card gaps" style="border-left:4px solid var(--danger);margin-bottom:14px;">
                <div>
                    <b>{{ $item->item }}</b>
                    <div class="hint">
                        {{ $ins->project->reference ?? 'a project' }} — {{ $ins->project->title ?? 'your site' }}
                        · order {{ $ins->workOrder->reference ?? '—' }}
                        · visited {{ $ins->inspected_on?->format('d M Y') ?? $ins->scheduled_on->format('d M Y') }}
                    </div>
                </div>
                <p style="margin:8px 0 4px;"><b>What they wrote:</b> {{ $item->note ?: 'no note was left on the mark' }}</p>

                @if ($item->hasProof())
                    <p class="hint">Your answer is already filed and waiting for the re-check:
                        <a class="doc-link" href="{{ route('portal.fixes.proof', $item) }}" target="_blank" rel="noopener"
                           data-zoom data-zoom-label="{{ $item->item }} — your proof">{{ $item->proof_name ?: 'open the photograph' }} ↗</a>
                        {{ $item->proof_note }} — uploaded {{ $item->proof_on?->format('d M Y H:i') }}</p>
                @endif

                @if (! $item->rechecked_on)
                    <form method="POST" action="{{ route('portal.fixes.prove', $item) }}" class="fee-form"
                          enctype="multipart/form-data" data-title="Answer the flagged item">
                        @csrf
                        <div class="field">
                            <label>What you did to put it right <span class="req">*</span></label>
                            <textarea name="proof_note" rows="2" required maxlength="1000"
                                      placeholder="Exactly the rework, so the inspector knows what to look for">{{ $item->proof_note }}</textarea>
                        </div>
                        <div class="field">
                            <label>Photograph of the fix <span class="req">*</span></label>
                            <input type="file" name="proof" accept="image/*" required>
                            <span class="hint">One clear shot of the finished rework is what the inspector re-checks against.</span>
                        </div>
                        <button class="btn btn-sm btn-primary" type="submit">{{ $item->hasProof() ? 'Send the new photograph' : 'Send the proof' }}</button>
                    </form>
                @endif
            </div>
        @endforeach
    </div>
@endif

@if ($cleared->isNotEmpty())
    <div class="card">
        <div class="section-head"><h3>Already re-checked</h3></div>
        <table class="data">
            <thead><tr><th>Item</th><th>Site</th><th>Your answer</th><th>Re-checked</th><th>Result</th></tr></thead>
            <tbody>
                @foreach ($cleared as $c)
                    <tr>
                        <td>{{ $c->item }}</td>
                        <td class="hint">{{ $c->inspection->project->reference ?? '—' }}</td>
                        <td class="hint">{{ $c->proof_note ?: '—' }}</td>
                        <td>{{ $c->rechecked_on->format('d M Y') }}</td>
                        <td><span class="status {{ $c->result === 'fail' ? 'rejected' : 'active' }}">{{ $c->resultLabel() }}</span></td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@endif

<div class="card">
    <div class="section-head"><h3>How an inspection runs</h3></div>
    <ol class="steps">
        <li><b>The office schedules the visit</b><div class="hint">with the items to be checked listed</div></li>
        <li><b>The inspector marks each item</b><div class="hint">pass, fail, or not applicable on your site</div></li>
        <li><b>You answer a failure here</b><div class="hint">what you did, with the photograph of the rework</div></li>
        <li><b>The inspector re-checks it</b><div class="hint">only they clear a flagged item — your upload never marks it passed</div></li>
        <li><b>The report is signed off</b><div class="hint">not while an item of yours is still standing</div></li>
    </ol>
    <p class="hint">Your progress, your days and your completion photographs stay on
        <a href="{{ route('portal.work') }}">My work orders</a> — this screen is only for what an inspection flagged.</p>
</div>
@endsection
