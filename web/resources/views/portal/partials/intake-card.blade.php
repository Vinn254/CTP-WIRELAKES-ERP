@php $already = $appliedTo->contains($c->id); @endphp
<div class="community-card" style="border-top:1px solid var(--line);padding-top:12px;margin-top:12px;">
    <div class="section-head">
        <h4 style="margin:0;">{{ $c->courseLabel() }}</h4>
        <span class="tag">{{ $c->training?->name }}</span>
        <span class="tag gold">{{ $c->reference }}</span>
        @if ($c->seats > 0)
            <span class="fee-tag {{ $c->seatsLeft() > 0 ? '' : 'paid' }}">{{ $c->seatsLeft() }} of {{ $c->seats }} seats left</span>
        @endif
    </div>
    <p class="hint">{{ $c->dateLabel() }} · {{ $c->hoursLabel() }}
        · {{ $c->placeLabel() }}
        @if ($c->trainer?->fundi) · trainer {{ $c->trainer->fundi->full_name }} @endif
        @if ((float) $c->fee_ksh > 0) · KSH {{ number_format((float) $c->fee_ksh, 2) }} @else · no training fee @endif
    </p>
    @if ($c->summary)<p class="hint">{{ $c->summary }}</p>@endif
    @if ($c->enrol_closes_on)
        <p class="hint">Applications close {{ $c->enrol_closes_on->format('d M Y') }}.</p>
    @endif

    @if ($already)
        <p class="help">You have already put yourself forward for this intake — it is in “Your training” below.</p>
    @elseif ($canWork)
        <form method="POST" action="{{ route('portal.training.apply') }}"
              data-confirm="Apply for {{ $c->reference }} — {{ $c->courseLabel() }}?" data-title="Apply for training">
            @csrf
            <input type="hidden" name="cohort_id" value="{{ $c->id }}">
            <div class="field">
                <label>Why you want this intake (optional)</label>
                <textarea name="note" rows="2" maxlength="1000"
                          placeholder="e.g. I have the tools and can attend every day at the school."></textarea>
            </div>
            <button class="btn btn-primary btn-sm" type="submit">Apply for a seat</button>
        </form>
    @else
        <p class="hint">The office confirms seats for active CTPs.</p>
    @endif

    @if (! empty($inArea) && $canWork)
        <p class="help">This one runs in {{ $member->county }}. Tell the other trainees in your area — bring them
            here to apply, and the office confirms their seats the same way.</p>
    @endif
</div>
