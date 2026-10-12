{{-- Renders whole sections an admin created that no template block claims. --}}
@foreach (content_extra_sections($page, $used) as $s)
    <section class="section">
        @if ($s['name'])<h2>{{ $s['name'] }}</h2>@endif
        @if ($s['kind'] === 'repeater')
            <div class="grid-3">
                @foreach ($s['rows'] as $row)
                    <div class="card">
                        @foreach ($row as $val)
                            @if (is_string($val) && $val !== '')
                                @if (preg_match('/\.(png|jpe?g|gif|svg|webp)(\?.*)?$/i', $val))
                                    <img src="{{ $val }}" alt="" style="max-height:140px;border-radius:10px;margin-bottom:8px;">
                                @elseif (mb_strlen($val) > 70)
                                    <p>{{ $val }}</p>
                                @else
                                    <h3>{{ $val }}</h3>
                                @endif
                            @endif
                        @endforeach
                    </div>
                @endforeach
            </div>
        @else
            @foreach ($s['single'] as $val)
                @if (is_string($val) && $val !== '')
                    @if (preg_match('/\.(png|jpe?g|gif|svg|webp)(\?.*)?$/i', $val))
                        <img src="{{ $val }}" alt="" style="max-width:100%;border-radius:12px;">
                    @else
                        <p>{!! nl2br(e($val)) !!}</p>
                    @endif
                @endif
            @endforeach
        @endif
    </section>
@endforeach
