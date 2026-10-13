@php
    use App\Models\CtpRegistration;

    $term = CtpRegistration::currentTerm();
    $thisTerm = $member->verifiedReferralsThisTerm();
    $lifetime = $member->verifiedReferrals();
    $next = $member->nextMilestone() ?? 30;
    $share = $member->referralLink();
    $whats = 'https://wa.me/?text='.urlencode('Join CTP-WireLakes as a CTP — use my referral link '.$share);
@endphp

<div class="card">
    <h3>Bring young people in — your referral code</h3>
    <div class="ref-code-box">
        <div>
            <small>Your code</small>
            <b>{{ $member->ctp_code ?: 'Issued on approval' }}</b>
        </div>
        <div class="ref-actions">
            <button class="btn btn-sm btn-outline" type="button"
                    onclick="copyText('{{ $share }}', this)">Copy link</button>
            <a class="btn btn-sm btn-lime" href="{{ $whats }}" target="_blank" rel="noopener">Share on WhatsApp</a>
        </div>
    </div>
    <div class="link-box">
        <input type="text" readonly value="{{ $share }}" onclick="this.select()">
    </div>
    <p class="hint">Drop the link in a WhatsApp or community chat. Anyone who joins through it is counted for you.</p>

    <div class="milestone">
        <div class="ms-top"><b>{{ $thisTerm }} of {{ $next }}</b><span class="hint">verified referrals this term ({{ $term['label'] }})</span></div>
        <div class="ms-bar"><i style="width: {{ min(100, round($thisTerm / $next * 100)) }}%"></i></div>
        @if ($thisTerm >= 15)
            <p class="reward">You have earned <b>{{ $member->discountLabel() }}</b> on this term's training fee.</p>
        @else
            <p class="hint">{{ 15 - $thisTerm }} more to unlock 50% off this term’s fee, {{ 30 - $thisTerm }} more for the full sponsorship credit.</p>
        @endif
    </div>
</div>

<div class="card">
    <h3>People you have brought in <span class="tag gray">{{ $referrals->count() }} referred · {{ $lifetime }} verified</span></h3>
    @forelse ($referrals as $r)
        <div class="row-line">
            <b>{{ $r->full_name }}</b>
            <span class="hint">{{ $r->county }} · joined {{ $r->created_at->format('d M Y') }}</span>
            <span class="status {{ $r->status }}">{{ $r->statusLabel() }}</span>
        </div>
    @empty
        <p class="hint">Nobody yet. Share your link — referrals count once the person has paid and been approved.</p>
    @endforelse
</div>

<div class="card">
    <h3>How counting works</h3>
    <ul class="rules">
        <li>Only CTPs and E-Fundis who <b>paid and were approved</b> count.</li>
        <li>Only referrals made <b>inside the current term</b> count toward the discount.</li>
        <li>The discount is a <b>credit on this term’s fee</b> — never a refund.</li>
        <li>You cannot refer yourself, and each person counts for only one mobiliser.</li>
        <li>If someone you referred is later rejected, they drop off your count.</li>
    </ul>
</div>
