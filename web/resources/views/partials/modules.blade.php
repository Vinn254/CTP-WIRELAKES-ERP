@php
    $board = $board ?? $enrollment->moduleBoard();
    $canMark = isset($mode) && in_array($mode, ['admin', 'trainer'], true);
    $url = fn (string $name, $m) => $name === 'admin'
        ? route('admin.enrollments.module.mark', [$enrollment, $m])
        : route('portal.trainer.module.mark', [$enrollment, $m]);
@endphp

<h4 class="dl-group">Modules under this training ({{ $board['marked']->count() }} of {{ $board['modules']->count() }} marked)</h4>
@if ($board['modules']->isEmpty())
    <p class="hint">The office has not put any modules under this training —
        it is not compulsory to have them. Add them under <b>Training</b> and they appear here, on the trainer's
        list of what to deliver and on the trainee's record, straight away.</p>
@else
    @foreach ($board['modules'] as $m)
        @php $mark = $board['marked']->get($m->id); @endphp
        <div class="row-line">
            <b>{{ $m->position }}. {{ $m->title }}</b>
            <span class="hint">{{ $m->hoursLabel() }}</span>
            @if ($mark)
                <span class="status active">marked {{ $mark->done_on->format('d M Y') }}</span>
                <span class="hint">{{ $mark->recorderLabel() }}@if ($mark->note) · “{{ $mark->note }}”@endif</span>
            @else
                <span class="status pending_review">not marked</span>
            @endif

            @if ($canMark && $enrollment->state === 'approved')
                @if (! $mark)
                    <form method="POST" action="{{ $url($mode, $m) }}" class="mini-form"
                          data-confirm="Mark {{ $m->title }} as got through?" data-title="Mark module">
                        @csrf
                        <button class="btn btn-sm btn-lime" type="submit">Mark done</button>
                    </form>
                @else
                    <form method="POST" action="{{ $url($mode, $m) }}" class="mini-form"
                          data-confirm="Take the mark off {{ $m->title }}?" data-title="Undo the mark">
                        @csrf @method('DELETE')
                        <button class="btn btn-sm btn-ghost" type="submit">Undo</button>
                    </form>
                @endif
            @endif
        </div>
        @if ($m->outcome)
            <p class="hint" style="margin:0 0 6px;">Expected: {{ $m->outcome }}</p>
        @endif
    @endforeach

    @if ($board['outstanding']->isNotEmpty() && $enrollment->state === 'approved')
        <p class="hint">Every module has to be marked before this trainee reaches 100% and can be certified.</p>
    @endif
@endif
