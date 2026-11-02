{{-- The grading popup for one unit — the Academic Registrar sets what each mark is worth here, and this partial is
     included wherever a unit's marks are read: a cohort's results and a student's own record. --}}
@php
    use App\Support\Grading;

    $sheetName = $sheetName ?? 'grade-'.$unit->id;
@endphp
@include('partials.sheet-start', [
    'name' => $sheetName,
    'title' => 'Grading — '.$unit->title,
    'sub' => 'Say what each mark is worth. The five parts must add to 100%.',
])
<form method="POST" action="{{ route('admin.grading.update', $unit) }}" class="fee-form">
    @csrf
    <input type="hidden" name="_sheet" value="{{ $sheetName }}">
    <div class="fields-grid three">
        @foreach (array_keys(\App\Models\UnitGrading::PARTS) as $part)
            <label class="field">
                <span>{{ \App\Models\UnitGrading::PARTS[$part] }} %</span>
                <input type="number" name="{{ $part }}_pct" min="0" max="100" step="1" required
                       value="{{ old($part.'_pct', $grading->{"{$part}_pct"}) }}"
                       data-weight="{{ \App\Models\UnitGrading::PARTS[$part] }}">
            </label>
        @endforeach
        <label class="field">
            <span>Pass mark</span>
            <input type="number" name="pass_pct" min="0" max="100" step="0.5" required
                   value="{{ old('pass_pct', $grading->pass_pct) }}">
        </label>
    </div>
    <p class="hint" data-weight-sum>Adds to 100%.</p>
    <label class="field">
        <span>Note on this grading</span>
        <input type="text" name="note" maxlength="500"
               placeholder="e.g. Theory paper and CATs are set by the examining body."
               value="{{ old('note', $grading->note) }}">
    </label>
    <p class="hint">{{ Grading::weightLine($grading) }}.</p>
    <div class="form-foot">
        <button class="btn btn-lime" type="submit">Save the grading</button>
        <button class="btn btn-outline" type="button" data-close-sheet>Cancel</button>
    </div>
</form>
@include('partials.sheet-end')

@once
    <script>
        // The five boxes must add to 100 before the desk sends the form — the sum is said out loud as they type.
        document.addEventListener('input', function (e) {
            const box = e.target.closest('[data-weight]');
            if (! box) return;

            const form = box.closest('form');
            const total = Array.from(form.querySelectorAll('[data-weight]'))
                .reduce(function (sum, i) { return sum + (Number(i.value) || 0); }, 0);
            const out = form.querySelector('[data-weight-sum]');

            out.textContent = total === 100
                ? 'Adds to 100%.'
                : 'Adds to ' + total + '% — ' + (100 - total > 0 ? 'put ' + (100 - total) + '% more on a part.' : 'take ' + (total - 100) + '% off a part.');
            out.classList.toggle('flag-bad', total !== 100);
        });
    </script>
@endonce
