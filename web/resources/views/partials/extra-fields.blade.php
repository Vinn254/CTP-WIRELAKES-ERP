{{-- Renders fields an admin added to a section beyond the ones the template uses. --}}
@foreach (content_extra_fields($page, $section, $used) as $val)
    @if ($val !== null && $val !== '')
        <p>{!! nl2br(e($val)) !!}</p>
    @endif
@endforeach
