{{-- What people say about this area, in their own words — the office types these in, nobody invents them. --}}
@php
    $voices = $voices ?? \App\Support\Content::rows($area, 'testimonials');
    $voices = array_values(array_filter($voices, fn ($v) => ! empty($v['quote'])));
@endphp

@if ($voices)
    <section class="section">
        <span class="tag">Testimonials</span>
        <h2>{{ content($area, 'intro', 'voices_title', 'From the people we have trained and served') }}</h2>

        <div class="grid-3">
            @foreach ($voices as $v)
                <blockquote class="card voice">
                    <p>“{{ $v['quote'] }}”</p>
                    <footer>
                        <b>{{ $v['name'] ?? '' }}</b>
                        @if (! empty($v['role']))<span>{{ $v['role'] }}</span>@endif
                        @if (! empty($v['town']))<span>{{ $v['town'] }}</span>@endif
                    </footer>
                </blockquote>
            @endforeach
        </div>
    </section>
@endif
