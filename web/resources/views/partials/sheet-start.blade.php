@php
    // A form that came back with errors reopens its own card, so nothing typed is lost off screen.
    $reopen = $errors->any() && old('_sheet') === $name;
@endphp
<div class="sheet {{ $reopen ? 'open' : '' }}" id="sheet-{{ $name }}" role="dialog" aria-modal="true" aria-label="{{ $title }}">
    <div class="sheet-back" data-close-sheet></div>
    <div class="sheet-card">
        <div class="sheet-head">
            <h3>{{ $title }}</h3>
            <button class="sheet-x" type="button" data-close-sheet aria-label="Close">&#10005;</button>
        </div>
        @isset($sub)<p class="sheet-sub">{{ $sub }}</p>@endisset
