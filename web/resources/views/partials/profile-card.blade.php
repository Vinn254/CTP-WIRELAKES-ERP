@php
    $mine = \App\Support\Profile::waitingOnMine(auth()->user());
@endphp
{{-- The person's own file, put beside the desks they work: it belongs to them, not to a department. --}}
<section class="ov-desk" style="margin:0 0 20px;">
    <header class="ov-desk-head">
        <h2>My profile</h2>
        <span>1 page</span>
    </header>
    <p class="ov-desk-note">Your details and photo, the documents you send in to be checked, and the edits you ask for.</p>
    <div class="ov-grid">
        <a class="ov-cell {{ $mine ? 'hot' : '' }}" href="{{ route('admin.profile.index') }}">
            <span class="ov-ico">@include('partials.icon', ['n' => 'image'])</span>
            <span class="ov-cell-body">
                <b>My profile</b>
                <small>Your file, your documents and your change requests</small>
            </span>
            @if ($mine)<span class="ov-pill">{{ $mine }}</span>@endif
        </a>
    </div>
</section>
