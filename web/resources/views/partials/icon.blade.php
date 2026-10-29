{{-- Monochrome line icon: @include('partials.icon', ['n' => 'users']) --}}
@php
    $paths = [
        'grid' => '<path d="M3 3h7v7H3zM14 3h7v7h-7zM14 14h7v7h-7zM3 14h7v7H3z"/>',
        'users' => '<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/>',
        'layout' => '<rect x="3" y="3" width="18" height="18" rx="2"/><path d="M3 9h18M9 21V9"/>',
        'sliders' => '<path d="M4 21v-7M4 10V3M12 21v-9M12 8V3M20 21v-5M20 12V3M1 14h6M9 8h6M17 16h6"/>',
        'link' => '<path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"/><path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"/>',
        'heart' => '<path d="M20.84 4.61a5.5 5.5 0 0 0-7.79 0L12 5.67l-1.05 1.06a5.5 5.5 0 0 0-7.79 7.79L12 21.23l8.84-8.8a5.5 5.5 0 0 0 0-7.82z"/>',
        'mail' => '<rect x="2" y="4" width="20" height="16" rx="2"/><path d="m22 7-10 6L2 7"/>',
        'key' => '<path d="M21 2l-2 2m-7.61 7.61a5.5 5.5 0 1 1-7.778 7.778 5.5 5.5 0 0 1 7.777-7.777zm0 0L15.5 7.5m0 0L19 4m-3.5 3.5L22 4"/>',
        'globe' => '<circle cx="12" cy="12" r="9"/><path d="M3.6 9h16.8M3.6 15h16.8M12 3a15 15 0 0 1 0 18 15 15 0 0 1 0-18z"/>',
        'logout' => '<path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4M16 17l5-5-5-5M21 12H9"/>',
        'moon' => '<path d="M21 12.79A9 9 0 1 1 11.21 3a7 7 0 0 0 9.79 9.79z"/>',
        'sun' => '<circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M4.9 4.9l1.4 1.4M17.7 17.7l1.4 1.4M2 12h2M20 12h2M4.9 19.1l1.4-1.4M17.7 6.3l1.4-1.4"/>',
        'menu' => '<path d="M3 6h18M3 12h18M3 18h18"/>',
        'close' => '<path d="M18 6 6 18M6 6l12 12"/>',
        'chevron' => '<path d="m6 9 6 6 6-6"/>',
        'image' => '<rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><path d="m21 15-5-5L5 21"/>',
        'check' => '<path d="m20 6-11 11-5-5"/>',
        'plus' => '<path d="M12 5v14M5 12h14"/>',
        'external' => '<path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/><path d="M15 3h6v6M10 14 21 3"/>',
        'briefcase' => '<rect x="2" y="7" width="20" height="14" rx="2"/><path d="M16 7V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v2M2 13h20"/>',
        'building' => '<rect x="4" y="2" width="16" height="20" rx="2"/><path d="M9 7h2M13 7h2M9 11h2M13 11h2M9 15h2M13 15h2M10 22v-3h4v3"/>',
        'award' => '<circle cx="12" cy="9" r="6"/><path d="m8.5 14-1.5 8 5-3 5 3-1.5-8"/>',
        'cap' => '<path d="M22 10 12 5 2 10l10 5 10-5Z"/><path d="M6 12.5V17c0 1.2 2.7 2.5 6 2.5s6-1.3 6-2.5v-4.5"/><path d="M22 10v6"/>',
        'book' => '<path d="M12 7c-2-1.4-4.6-2-8-2v12c3.4 0 6 .6 8 2 2-1.4 4.6-2 8-2V5c-3.4 0-6 .6-8 2Z"/><path d="M12 7v12"/>',
        'wrench' => '<path d="M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.77-3.77a6 6 0 0 1-7.94 7.94l-6.91 6.91a2.12 2.12 0 0 1-3-3l6.91-6.91a6 6 0 0 1 7.94-7.94z"/>',
        'hardhat' => '<path d="M2 18a1 1 0 0 0 1 1h18a1 1 0 0 0 1-1v-2a1 1 0 0 0-1-1H3a1 1 0 0 0-1 1z"/><path d="M10 10V5a1 1 0 0 1 1-1h2a1 1 0 0 1 1 1v5"/><path d="M4 15v-3a6 6 0 0 1 6-6"/><path d="M14 6a6 6 0 0 1 6 6v3"/>',
        'recycle' => '<path d="M21 12a9 9 0 1 1-2.64-6.36"/><path d="M21 3v6h-6"/>',
        'leaf' => '<path d="M11 20A7 7 0 0 1 9.8 6.1C15.5 5 17 4.48 19 2c1 2 2 4.18 2 8 0 5.5-4.78 10-10 10Z"/><path d="M2 21c0-3 1.85-5.36 5.08-6"/>',
        'map' => '<path d="m3 6 6-3 6 3 6-3v15l-6 3-6-3-6 3z"/><path d="M9 3v15M15 6v15"/>',
        'clock' => '<circle cx="12" cy="12" r="9"/><path d="M12 7.5V12l3.4 2"/>',
        'clipboard' => '<rect x="5" y="4" width="14" height="17" rx="2"/><path d="M9.5 4V3a1 1 0 0 1 1-1h3a1 1 0 0 1 1 1v1z"/><path d="M9 11h6M9 15h4"/>',
        'chart' => '<path d="M3 3v18h18"/><path d="M7 15v3M12 10v8M17 6v12"/>',
    ];

    /** What the office types on a content field, and the line icon the site draws for it. */
    $aliases = [
        '🎓' => 'cap', '🛠' => 'wrench', '🏗' => 'hardhat', '♻' => 'recycle', '💼' => 'briefcase',
        '🌱' => 'leaf', '🗺' => 'map', '🤝' => 'users',
    ];

    $raw = trim((string) ($n ?? ''));
    $name = isset($paths[$raw]) ? $raw : ($aliases[str_replace("\u{FE0F}", '', $raw)] ?? null);
@endphp
@if ($name)
    <svg class="ico {{ $cls ?? '' }}" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">{!! $paths[$name] !!}</svg>
@else
    {{ $raw }}
@endif
