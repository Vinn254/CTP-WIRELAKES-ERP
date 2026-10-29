{{-- One document on a person's file: the card carries both ways of taking it away — read it here, or save it. --}}
@php
    $isPdf = str_ends_with(strtolower($path), '.pdf');
@endphp
<div class="doc-card">
    <span class="dc-name">{{ $label }}</span>
    <span class="dc-acts">
        <a class="dc-open" href="{{ $url }}" target="_blank" rel="noopener"
           @if (! $isPdf) data-zoom data-zoom-label="{{ $label }}" @endif>
            Open {{ $isPdf ? 'PDF' : 'image' }} ↗</a>
        <a class="dc-open" href="{{ download_href($url) }}">Download</a>
    </span>
</div>
