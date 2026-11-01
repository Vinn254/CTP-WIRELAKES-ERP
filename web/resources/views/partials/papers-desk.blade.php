{{-- Teaching papers and the library are one desk: the papers a trainer hands in and what the students read. --}}
@php $desk = \App\Support\Departments::canSee(auth()->user(), 'training'); @endphp
@if ($desk)
    <div class="tabs">
        <a class="tab {{ $deskTab === 'papers' ? 'on' : '' }}" href="{{ route('admin.registers.index') }}">
            Teaching papers</a>
        <a class="tab {{ $deskTab === 'library' ? 'on' : '' }}" href="{{ route('admin.library.index') }}">Library</a>
    </div>
@endif
