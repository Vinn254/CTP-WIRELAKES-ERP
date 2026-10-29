@php
    /** @var \App\Models\Cohort $cohort */
    $many = $mine->count() > 1;
@endphp
<div class="head-row">
    <div>
        <h1>{{ $cohort->reference }} — {{ $cohort->courseLabel() }}</h1>
        <p class="ah-meta">
            <span>{{ $cohort->venue?->name ?? 'No venue set' }}</span>
            <span>{{ $cohort->periodLabel() }}</span>
            <span>{{ $roll->count() }} CTP{{ $roll->count() === 1 ? '' : 's' }} on the roll</span>
            <span>{{ $cohort->training?->modules?->count() ?? 0 }} {{ Str::plural('unit', (int) ($cohort->training?->modules?->count() ?? 0)) }}</span>
        </p>
    </div>
    @if ($many)
        <div class="head-acts">
            <label class="field">
                <span>Cohort</span>
                <select onchange="location.href=this.value">
                    @foreach ($mine as $c)
                        <option value="{{ $url($screen, ['cohort' => $c->id]) }}" @selected($c->id === $cohort->id)>
                            {{ $c->reference }} — {{ $c->courseLabel() }}
                        </option>
                    @endforeach
                </select>
            </label>
        </div>
    @endif
</div>

<div class="tabs">
    @foreach ($tabs as $tab)
        <a class="tab {{ $tab['key'] === $screen ? 'on' : '' }}" href="{{ $tab['href'] }}">{{ $tab['label'] }}</a>
    @endforeach
</div>

@if ($roll->isEmpty())
    <p class="flash-error" style="margin:0 0 18px">No CTP holds an approved seat in this cohort yet, so there is
        nothing to mark. The office takes seats onto the roll under <b>Cohorts</b>.</p>
@endif
