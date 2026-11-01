{{-- The office leave register and a worker's own leave are one desk: the answer here, the booking beside it. --}}
@php
    $slug = \App\Support\Departments::currentSlug() ?: (auth()->user()->departmentSlugs()[0] ?? null);

    $register = \App\Support\Departments::canHost('admin.host.leave.index', $slug)
        ? route('admin.host.leave.index', $slug)
        : route('admin.leave.index');
@endphp
<div class="tabs">
    <a class="tab {{ ($deskTab ?? '') === 'register' ? 'on' : '' }}" href="{{ $register }}">Leave register</a>
    <a class="tab {{ ($deskTab ?? '') === 'mine' ? 'on' : '' }}" href="{{ route('admin.myleave.index') }}">My leave</a>
</div>
