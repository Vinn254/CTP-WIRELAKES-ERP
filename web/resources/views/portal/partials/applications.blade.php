@php
    use App\Models\ProjectApplication;
    $shown = isset($limit) ? $applications->take($limit) : $applications;
@endphp

@if ($shown->isEmpty())
    <div class="card"><p class="page-lede">You have not applied for any project yet.</p></div>
@else
    @foreach ($shown as $a)
        <div class="invite-row {{ $a->status === 'rejected' ? 'revoked' : '' }}">
            <div class="invite-main">
                <b>{{ $a->project->title }}</b>
                <span class="status {{ $a->status === 'approved' ? 'awarded' : ($a->status === 'rejected' ? 'rejected' : 'pending_review') }}">
                    {{ ProjectApplication::STATUSES[$a->status] ?? $a->status }}
                </span>
                <div class="hint">{{ $a->project->reference }}@if ($a->project->hirer->organisation) · {{ $a->project->hirer->organisation }}@endif · applied {{ $a->created_at->format('d M Y') }}</div>
                @if ($a->status === 'approved')
                    <div class="hint">Assigned to you — the programme office will contact you on {{ $a->phone }}.</div>
                @elseif ($a->rejection_reason)
                    <div class="hint">Feedback: {{ $a->rejection_reason }}</div>
                @endif
            </div>
            <div class="invite-actions">
                <a class="btn btn-sm btn-outline" href="{{ route('portal.project', $a->project) }}">Open</a>
                @if ($a->status === 'pending_review')
                    <form method="POST" action="{{ route('portal.withdraw', $a) }}" data-confirm="Withdraw your application for {{ $a->project->title }}?">
                        @csrf
                        <button class="btn btn-sm btn-ghost" type="submit">Withdraw</button>
                    </form>
                @endif
            </div>
        </div>
    @endforeach
    @isset($limit)
        @if ($applications->count() > $limit)
            <p class="more"><a href="{{ route('portal.applications') }}">All {{ $applications->count() }} of your applications &rarr;</a></p>
        @endif
    @endisset
@endif
