{{-- Teaching papers, the library and the papers people send in are one desk: what is handed in, what the students
     read, and the documents waiting on the file. Grading is not here — it is set on a cohort's own results. --}}
@php $desk = \App\Support\Departments::canSee(auth()->user(), 'training'); @endphp
@if ($desk)
    <div class="tabs">
        <a class="tab {{ $deskTab === 'papers' ? 'on' : '' }}" href="{{ route('admin.registers.index') }}">
            Teaching papers</a>
        <a class="tab {{ $deskTab === 'library' ? 'on' : '' }}" href="{{ route('admin.library.index') }}">Library</a>
        <a class="tab {{ $deskTab === 'requests' ? 'on' : '' }}" href="{{ route('admin.papers.index') }}">
            Papers &amp; requests
            <span class="n">{{ \App\Support\Profile::waitingFor(auth()->user()) }}</span></a>
    </div>
@endif
