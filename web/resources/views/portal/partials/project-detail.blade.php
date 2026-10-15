@php
    use App\Models\Project;
    $isOpen = $project->status === 'open';
@endphp

<p class="back"><a href="{{ $backUrl }}">&larr; Back to projects</a></p>

<span class="tag gray">{{ Project::CATEGORIES[$project->category]['label'] }}</span>
<h1>{{ $project->title }}</h1>
<p class="hint" style="margin:4px 0 18px;">{{ $project->reference }} · posted {{ $project->created_at->format('d M Y') }}</p>

<div class="proj-grid">
    <div class="card">
        <h3>What the work is</h3>
        <p style="white-space:pre-line;margin-top:0;">{{ $project->description }}</p>
        @if ($project->materials)
            <h3 style="margin:18px 0 6px;">Materials required</h3>
            <p style="white-space:pre-line;margin:0;">{{ $project->materials }}</p>
        @endif
        @if ($project->hirer->organisation)
            <p class="hint">Client: <b>{{ $project->hirer->organisation }}</b>@if ($project->hirer->county) · {{ $project->hirer->county }}@endif</p>
        @endif
    </div>

    <div class="card">
        <h3>At a glance</h3>
        <dl class="kv">
            <div><dt>Trade</dt><dd>{{ $project->trade ?: 'Any trade' }}</dd></div>
            <div><dt>Place</dt><dd>{{ collect([$project->county, $project->ward])->filter()->implode(', ') ?: 'To be confirmed' }}
                @if ($project->community->exists)<div class="hint">Community: {{ $project->community->name }}</div>@endif</dd></div>
            <div><dt>Budget</dt><dd>{{ $project->budget !== null ? 'KSh '.number_format((float) $project->budget, 2) : 'Discussed on award' }}</dd></div>
            <div><dt>Duration</dt><dd>{{ $project->duration ?: '—' }}</dd></div>
            <div><dt>Apply by</dt><dd>{{ $project->deadline ? $project->deadline->format('d M Y') : 'Open until assigned' }}</dd></div>
        </dl>

        @if ($application)
            <div class="apply-state">
                @if ($application->status === 'approved')
                    <span class="status awarded">Assigned to you</span>
                    <p class="hint">The programme office will contact you to start.</p>
                @elseif ($application->status === 'rejected')
                    <span class="status rejected">Not selected</span>
                    @if ($application->rejection_reason)<p class="hint">{{ $application->rejection_reason }}</p>@endif
                @else
                    <span class="status pending_review">Application under review</span>
                    <form method="POST" action="{{ route('portal.withdraw', $application) }}" data-confirm="Withdraw your application for this project?" style="margin-top:10px;">
                        @csrf
                        <button class="btn btn-sm btn-ghost" type="submit">Withdraw application</button>
                    </form>
                @endif
            </div>
        @elseif (! $project->accepts($member))
            <p class="hint" style="margin-top:12px;">
                <b>{{ Project::CATEGORIES[$project->category]['label'] }}</b> is posted for
                {{ $member instanceof CtpRegistration ? 'E-Fundis at that level' : 'a different level' }}.
                You can read what the programme is doing here, and apply once your own level matches —
                your training path is under <b>My training</b>.
            </p>
        @elseif ($isOpen)
            <form method="POST" action="{{ route('portal.apply', $project) }}" style="margin-top:14px;">
                @csrf
                <div class="field">
                    <label>Why are you the right fit? <span class="hint">(optional)</span></label>
                    <textarea name="note" rows="4" maxlength="2000" placeholder="Recent similar work, tools you own, when you can start.">{{ old('note') }}</textarea>
                </div>
                <button class="btn btn-primary btn-block" type="submit">Apply for this project</button>
                <div class="hint" style="margin-top:8px;">One worker is assigned per project. The programme office reviews every application.</div>
            </form>
        @else
            <p class="hint" style="margin-top:12px;">
                {{ $preview ? 'This is exactly what the member sees when the project is closed to applications.' : 'This project is no longer accepting applications.' }}
            </p>
        @endif
    </div>
</div>

@if ($project->providedMaterials()->exists())
    @php $lines = $project->providedMaterials()->orderBy('id')->get(); @endphp
    <div class="card">
        <div class="section-head"><h3>Materials the programme provides ({{ $lines->count() }})</h3></div>
        <p class="hint">Every line is something the organisation supplies for this job, with what it costs.
            Whatever is recorded as handed over is <b>deducted from the payout</b> — you are paid the agreed amount
            less what you were given, never twice for the same cable (§14.6 step 6).</p>
        <table class="data money-table">
            <thead>
                <tr><th>Material</th><th>Quantity</th><th>Cost each</th><th>Value</th><th>Given out</th></tr>
            </thead>
            <tbody>
                @foreach ($lines as $m)
                    <tr>
                        <td><b>{{ $m->name }}</b>@if ($m->note)<div class="hint">{{ $m->note }}</div>@endif</td>
                        <td>{{ $m->quantityLabel() }}</td>
                        <td>{{ $m->unitCostLabel() }}</td>
                        <td><b>{{ $m->totalLabel() }}</b></td>
                        <td>@if ($m->isSupplied())
                                <span class="status active">{{ $m->supplied_on->format('d M Y') }}</span>
                            @else
                                <span class="hint">not yet</span>
                            @endif</td>
                    </tr>
                @endforeach
            </tbody>
            <tfoot>
                <tr>
                    <td colspan="3"><b>Listed for the job</b></td>
                    <td><b>{{ \App\Support\Finance::ksh($lines->sum(fn ($l) => $l->totalKsh())) }}</b></td>
                    <td class="hint">{{ \App\Support\Finance::ksh($lines->filter->isSupplied()->sum(fn ($l) => $l->totalKsh())) }} already handed over</td>
                </tr>
            </tfoot>
        </table>
    </div>
@endif
