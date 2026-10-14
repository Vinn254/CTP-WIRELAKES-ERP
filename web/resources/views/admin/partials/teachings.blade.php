@php $chosen = array_map('intval', $chosen ?? []); @endphp
<div class="field" style="margin-top:12px;">
    <label>{{ $label ?? 'What they can teach' }} <span class="req">*</span></label>
    <div style="display:flex;flex-wrap:wrap;gap:2px 18px;margin:8px 0;">
        @foreach ($trainings as $tr)
            <label class="check-row" style="margin:0 16px 0 0;">
                <input type="checkbox" name="trainings[]" value="{{ $tr->id }}" @checked(in_array($tr->id, $chosen, true))>
                {{ $tr->name }}@if (($tr->status ?? 'active') !== 'active') <span class="hint">(not listed)</span>@endif
            </label>
        @endforeach
    </div>
    <div class="help">{{ $help ?? 'A cohort can only be handed to a trainer who is approved <b>and</b> listed for that training. Nothing new here? Add it under <a href="'.route('admin.trainings.index').'">Training</a>.' }}</div>
</div>
