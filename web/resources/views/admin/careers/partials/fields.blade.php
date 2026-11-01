@php
    // The same fields serve the posting sheet and a row's edit sheet; $o is the opening being amended, or null.
    $val = fn (string $field, $fallback = '') => old('_sheet') === ($sheet ?? '') && old($field) !== null
        ? old($field)
        : (string) ($o->{$field} ?? $fallback);
    $date = fn (string $field) => old('_sheet') === ($sheet ?? '') && old($field) !== null
        ? old($field)
        : ($o?->{$field}?->format('Y-m-d') ?? '');
@endphp
<div class="form-grid-2">
    <div class="field full">
        <label>Title on the public page <span class="req">*</span></label>
        <input type="text" name="title" required maxlength="190" value="{{ $val('title') }}"
               placeholder="e.g. Robotics trainer for the Kisumu cohorts">
    </div>
    <div class="field">
        <label>What kind of opening <span class="req">*</span></label>
        <select name="kind" required>
            @foreach (\App\Models\JobOpening::KINDS as $key => $label)
                <option value="{{ $key }}" @selected($val('kind', 'job') === $key)>{{ $label }}</option>
            @endforeach
        </select>
    </div>
    <div class="field">
        <label>Area of work</label>
        <input type="text" name="area" maxlength="90" value="{{ $val('area') }}" placeholder="e.g. Training, robotics, construction">
    </div>
    <div class="field">
        <label>County</label>
        <input type="text" name="county" maxlength="90" value="{{ $val('county') }}">
    </div>
    <div class="field">
        <label>Positions</label>
        <input type="number" name="positions" min="1" max="999" value="{{ $val('positions', 1) }}">
    </div>
    <div class="field">
        <label>Applications close</label>
        <input type="date" name="closing_on" value="{{ $date('closing_on') }}">
        <div class="hint">Leave blank and it stays open until you close it.</div>
    </div>
</div>
<div class="field" style="margin-top:10px;">
    <label>What the work is <span class="req">*</span></label>
    <textarea name="description" rows="4" required maxlength="1000"
              placeholder="What the person will do, for whom, and on what terms.">{{ $val('description') }}</textarea>
</div>
<div class="field" style="margin-top:10px;">
    <label>What we are looking for</label>
    <textarea name="requirements" rows="3" maxlength="1000"
              placeholder="Skills, certificates, experience — anything a person must bring.">{{ $val('requirements') }}</textarea>
</div>
