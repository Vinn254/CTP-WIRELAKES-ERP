{{-- Pictures and short videos the office has shot on this area, each with the caption it wrote for it. --}}
@php
    $gallery = $gallery ?? \App\Support\Content::rows($area, 'gallery');
    $gallery = array_values(array_filter($gallery, fn ($g) => ! empty($g['photo']) || ! empty($g['video'])));
@endphp

@if ($gallery)
    <section class="section">
        <span class="tag">In pictures</span>
        <h2>{{ \App\Support\Content::get($area, 'intro', 'gallery_title', 'What the work looks like') }}</h2>

        <div class="grid-3">
            @foreach ($gallery as $g)
                <figure class="card gallery-item">
                    @if (! empty($g['photo']))
                        <a class="gallery-shot" href="{{ $g['photo'] }}" data-zoom
                           data-zoom-label="{{ $g['caption'] ?? '' }}">
                            <img src="{{ $g['photo'] }}" alt="{{ $g['caption'] ?? 'Photo from '.\App\Support\ProgrammeAreas::name($area) }}" loading="lazy">
                        </a>
                    @elseif (! empty($g['video']))
                        <video class="gallery-video" src="{{ $g['video'] }}" controls preload="metadata" playsinline></video>
                    @endif

                    @if (! empty($g['caption']))
                        <figcaption>{{ $g['caption'] }}</figcaption>
                    @endif
                </figure>
            @endforeach
        </div>
    </section>
@endif
