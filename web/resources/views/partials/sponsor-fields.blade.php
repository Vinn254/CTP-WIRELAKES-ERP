@php
    /** A sponsor is either one of our own partners or anybody else the applicant names — with a way to reach them. */
    $pickedId = (int) old('sponsor_id', $record->sponsor_id ?? 0);
@endphp
<div class="form-grid-3">
    <div class="field">
        <label>A partner of the programme <span class="hint">(optional)</span></label>
        <select name="sponsor_id">
            <option value="">No partner sponsor</option>
            @foreach ($partners as $p)
                <option value="{{ $p->id }}" @selected($pickedId === $p->id)>{{ $p->name }}</option>
            @endforeach
        </select>
    </div>
    <div class="field">
        <label>Or write the sponsor's name <span class="hint">(optional)</span></label>
        <input type="text" name="sponsor_name" value="{{ old('sponsor_name', $record->sponsor_name ?? '') }}" maxlength="150"
               placeholder="e.g. Amani Youth Group">
    </div>
    <div class="field">
        <label>Their phone or email <span class="hint">(optional)</span></label>
        <input type="text" name="sponsor_contact" value="{{ old('sponsor_contact', $record->sponsor_contact ?? '') }}" maxlength="120"
               placeholder="e.g. 07XX XXX XXX">
    </div>
</div>
@if ($office ?? false)
    <p class="help">Pick a partner from the list, or type the name of anybody else backing this person. Use one of the
        two — a partner is a programme relationship, a written-in name is somebody we have not signed anything with.</p>
@else
    <p class="help">Use one of the two: the list if your sponsor works with us, or their name and contact if they do not.
        Naming a sponsor changes nothing on its own — the office contacts them and confirms it on your record.</p>
@endif
