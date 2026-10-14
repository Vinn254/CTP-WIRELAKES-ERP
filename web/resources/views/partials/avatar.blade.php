@php
    /**
     * The face on a member record: the photo they joined with when it is on file, their initials when
     * it is not. Every profile screen — portal, office record, application review — includes this one
     * partial, so a member is never shown two different ways.
     */
    $hasPhoto = $member->photoPath();
    $initials = $initials ?? collect(explode(' ', trim($member->full_name)))->filter()
        ->take(2)->map(fn ($w) => mb_strtoupper(mb_substr($w, 0, 1)))->join('');
@endphp
<span class="{{ $class ?? 'avatar-lg' }}{{ $hasPhoto ? ' has-photo' : '' }}">
    @if ($hasPhoto)
        <a href="{{ $src }}" data-zoom aria-label="Open the profile photo">
            <img src="{{ $src }}" alt="{{ $member->full_name }} — profile photo">
        </a>
    @else
        {{ $initials }}
    @endif
</span>
