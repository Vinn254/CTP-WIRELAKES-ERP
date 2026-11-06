@php
    // A form that came back with errors reopens its own card, so nothing typed is lost off screen. A card may also
    // be put up on its own — the reminder that a profile is still short of what the office needs.
    $reopen = ($errors->any() && old('_sheet') === $name) || ($openOnLoad ?? false);
@endphp
<div class="sheet {{ $reopen ? 'open' : '' }}" id="sheet-{{ $name }}" role="dialog" aria-modal="true" aria-label="{{ $title }}">
    <div class="sheet-back" data-close-sheet></div>
    <div class="sheet-card">
        <div class="sheet-head">
            <h3>{{ $title }}</h3>
            <button class="sheet-x" type="button" data-close-sheet aria-label="Close">&#10005;</button>
        </div>
        @isset($sub)<p class="sheet-sub">{{ $sub }}</p>@endisset
        @if ($errors->any() && old('_sheet') === $name)
            {{-- A card that stays up has to say why in its own words — an error list printed behind the popup on the
                 page reads to the office as a form that simply refuses to close. --}}
            <div class="errors">
                <strong>Not saved — fix {{ $errors->count() }} {{ Str::plural('thing', $errors->count()) }} named
                    below, then submit again.</strong>
                <ul>
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif
