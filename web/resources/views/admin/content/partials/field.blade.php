{{-- One content field for the given entry. Expects: $section, $entry, $field --}}
@php
    $value = $entry->values->firstWhere('field_id', $field->id);
    $v = $value?->text_value ?? '';
@endphp
<div class="cf cf-{{ $field->type }}">
    <label>{{ $field->label }}
        <button type="button" class="fld-del" form="delfld-{{ $field->id }}" title="Delete this field">delete</button>
    </label>

    @if ($field->type === 'image')
        @if ($value?->image_path)
            <div class="cf-img">
                <img src="{{ asset('uploads/content/'.$value->image_path) }}" alt="">
                <label class="chk"><input type="checkbox" name="remove_image[{{ $entry->id }}][{{ $field->id }}]" value="1"> Remove picture</label>
            </div>
        @endif
        <input type="file" name="images[{{ $entry->id }}][{{ $field->id }}]" accept=".jpg,.jpeg,.png,.webp">
        <div class="hint">{{ $value?->image_path ? 'Leave empty to keep the current picture. Max 4 MB.' : 'JPG, PNG, SVG or WebP — max 4 MB.' }}</div>
    @elseif ($field->type === 'video')
        @if ($value?->image_path)
            <div class="cf-img">
                <video src="{{ asset('uploads/content/'.$value->image_path) }}" controls preload="metadata" style="max-width:260px;"></video>
                <label class="chk"><input type="checkbox" name="remove_image[{{ $entry->id }}][{{ $field->id }}]" value="1"> Remove clip</label>
            </div>
        @endif
        <input type="file" name="videos[{{ $entry->id }}][{{ $field->id }}]" accept=".mp4,.webm,.mov">
        <div class="hint">{{ $value?->image_path ? 'Leave empty to keep the current clip. Max 20 MB.' : 'A short clip — MP4, WebM or MOV, max 20 MB.' }}</div>
    @elseif ($field->type === 'textarea')
        <textarea name="values[{{ $entry->id }}][{{ $field->id }}]" rows="4">{{ $v }}</textarea>
    @elseif ($field->type === 'price')
        <input type="number" step="any" min="0" name="values[{{ $entry->id }}][{{ $field->id }}]" value="{{ $v }}">
        <div class="hint">Amount in KSh — number only.</div>
    @elseif ($field->type === 'url')
        <input type="url" name="values[{{ $entry->id }}][{{ $field->id }}]" value="{{ $v }}" placeholder="https://…">
        <div class="hint">Full link, or a site path like /register/ctp</div>
    @else
        <input type="text" name="values[{{ $entry->id }}][{{ $field->id }}]" value="{{ $v }}">
    @endif
</div>
