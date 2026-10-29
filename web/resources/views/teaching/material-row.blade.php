@php /** @var \App\Models\TrainingMaterial $paper */ @endphp
<div class="card">
    <div class="section-head">
        <h3>{{ $paper->title }}</h3>
        <span class="tag">{{ $paper->kindLabel() }}</span>
    </div>
    <p class="ah-meta">
        <span>{{ $paper->dateLabel() }}</span>
        <span>{{ $paper->module?->title ?? 'no unit named' }}</span>
        @if ($paper->lesson)<span>lesson {{ $paper->lesson->position }}</span>@endif
        <span>{{ $paper->sizeLabel() }}</span>
    </p>
    @if ($paper->note)<p class="hint">{{ $paper->note }}</p>@endif
    <div class="module-foot">
        <a class="btn btn-sm btn-outline"
           href="{{ $url('material.open', ['cohort' => $cohort->id, 'material' => $paper->id]) }}">Open</a>
        <a class="btn btn-sm btn-ghost"
           href="{{ download_href($url('material.open', ['cohort' => $cohort->id, 'material' => $paper->id])) }}">Download</a>
        <button class="btn btn-sm btn-outline" type="button" data-sheet="material" data-material="{{ json_encode([
                'correct' => $paper->id, 'title' => $paper->title, 'kind' => $paper->kind,
                'training_module_id' => $paper->training_module_id, 'lesson_line_id' => $paper->lesson_line_id,
                'given_on' => $paper->given_on?->toDateString(), 'note' => $paper->note,
                'action' => $url('material.update', ['cohort' => $cohort->id, 'material' => $paper->id]),
            ]) }}">Correct the item</button>
        <form method="POST" action="{{ $url('material.destroy', ['cohort' => $cohort->id, 'material' => $paper->id]) }}"
              data-confirm="Take {{ $paper->title }} off the shelf?">
            @csrf @method('DELETE')
            <button class="btn btn-sm btn-ghost" type="submit">Take off the shelf</button>
        </form>
    </div>
</div>
