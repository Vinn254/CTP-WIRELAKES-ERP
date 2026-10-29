@php
    // The Trainer title carries a field: the office picks the training this person teaches from the programme's
    // own catalogue, and the pick only asks when Trainer is ticked.
    $picked = (int) ($selected ?? 0);
@endphp
<div class="fld trainer-field" @unless ($open ?? false) hidden @endunless>
    <label>Field of training <span class="req">*</span></label>
    <select name="trainer_training_id" @if ($open ?? false) required @endif>
        <option value="">Choose the training they teach</option>
        @foreach ($fields as $field)
            <option value="{{ $field->id }}" @selected($picked === $field->id)>{{ $field->name }}</option>
        @endforeach
    </select>
    <div class="help">Only the trainings the programme runs are listed. The Training &amp; Certification desk still
        posts a trainer to a named site.</div>
</div>
