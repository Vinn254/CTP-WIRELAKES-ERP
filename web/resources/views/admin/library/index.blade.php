@extends('layouts.admin')
@section('title', 'Library')

{{-- The shelf is read programme by programme: a course is picked up top and its titles are traced in one table —
     what is filed, how heavy it is, when it went up and whether the students can open it today. --}}

@section('content')
<div class="head-row">
    <div>
        <h1>Library</h1>
        <p class="page-lede">Books, notes, slides and PDFs students read for each course.</p>
    </div>
    <div class="head-actions">
        <button class="btn btn-sm btn-lime" type="button" data-sheet="post-item">Post to the library</button>
    </div>
</div>

@include('partials.errors')

<div class="stat-row">
    @foreach ($stats as [$label, $value])
        <div class="stat"><small>{{ $label }}</small><b>{{ $value }}</b></div>
    @endforeach
</div>

<div class="tabs">
    @foreach ($tabs as $t)
        <a class="tab {{ (string) ($course ?? 'all') === $t['key'] ? 'on' : '' }}"
           href="{{ route('admin.library.index', array_filter(['training_id' => $t['key'], 'kind' => $kind, 'status' => $shelf])) }}">
            {{ $t['label'] }} <span class="n">{{ $t['count'] }}</span></a>
    @endforeach
</div>

<form method="GET" action="{{ route('admin.library.index') }}" class="filter-bar inline-filter">
    @if ($course !== null)<input type="hidden" name="training_id" value="{{ $course }}">@endif
    <label class="field">
        <span>Type</span>
        <select name="kind" onchange="this.form.submit()">
            <option value="all" @selected($kind === null)>Every type</option>
            @foreach ($kinds as $key => $label)
                <option value="{{ $key }}" @selected($kind === $key)>{{ $label }}</option>
            @endforeach
        </select>
    </label>
    <label class="field">
        <span>Shelf</span>
        <select name="status" onchange="this.form.submit()">
            <option value="all" @selected($shelf === null)>On and off the shelf</option>
            @foreach ($shelfOptions as $key => $label)
                <option value="{{ $key }}" @selected($shelf === $key)>{{ $label }}</option>
            @endforeach
        </select>
    </label>
    <noscript><button class="btn btn-sm btn-outline" type="submit">Apply</button></noscript>
</form>

@forelse ($groups as $group)
    <h4 class="dl-group">{{ $group['label'] }} — {{ $group['items']->count() }}
        {{ Str::plural('title', $group['items']->count()) }}</h4>
    <div class="table-scroll">
        <table class="data">
            <thead>
                <tr>
                    <th>Title</th><th>Type</th><th>File on record</th><th>Size</th><th>Posted</th><th>Shelf</th><th></th>
                </tr>
            </thead>
            <tbody>
                @foreach ($group['items'] as $item)
                    <tr>
                        <td>
                            <b>{{ $item->title }}</b>
                            @if ($item->note)<p>{{ $item->note }}</p>@endif
                        </td>
                        <td>{{ $item->kindLabel() }}</td>
                        <td class="wrap">{{ $item->file_name }}</td>
                        <td class="num">{{ $item->sizeLabel() }}</td>
                        <td>{{ $item->dateLabel() }}<small>{{ $item->poster?->name ?: 'the office' }}</small></td>
                        <td><span class="status {{ $item->status === 'on' ? 'active' : 'closed' }}">
                            {{ $item->shelfLabel() }}</span></td>
                        <td>
                            <div class="lib-acts">
                                <a class="btn btn-sm btn-outline" href="{{ route('admin.library.open', $item) }}"
                                   target="_blank" rel="noopener">Read</a>
                                <a class="btn btn-sm btn-ghost"
                                   href="{{ download_href(route('admin.library.open', $item)) }}">Download</a>
                                <button class="btn btn-sm btn-ghost" type="button"
                                        data-sheet="fix-{{ $item->id }}">Correct</button>
                                <form method="POST"
                                      action="{{ route('admin.library.shelf', [$item, $item->status === 'on' ? 'off' : 'on']) }}"
                                      data-confirm="{{ $item->status === 'on' ? 'Take '.$item->title.' off the shelf? Students stop seeing it; the file stays on record.' : 'Put '.$item->title.' back on the shelf for the students?' }}"
                                      data-title="Shelf">
                                    @csrf
                                    <button class="btn btn-sm btn-ghost" type="submit">
                                        {{ $item->status === 'on' ? 'Off the shelf' : 'Back on the shelf' }}</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@empty
    <div class="card">
        <h3>Nothing on this shelf yet</h3>
        <p class="help">Post a book, a set of notes or a slide deck and every trainee on that course reads it under
            My library. A title with no course is reading for the whole student body.</p>
    </div>
@endforelse

@foreach ($items as $item)
    @include('partials.sheet-start', ['name' => 'fix-'.$item->id, 'title' => 'Correct '.$item->title,
        'sub' => 'Saving overwrites the title on the shelf — students open the new file, not the old one.'])
        <form method="POST" action="{{ route('admin.library.update', $item) }}" enctype="multipart/form-data"
              class="fee-form" data-confirm="Overwrite this title with what you save now?" data-title="Correct the title">
            @csrf @method('PATCH')
            <input type="hidden" name="_sheet" value="fix-{{ $item->id }}">
            <div class="form-grid">
                <div class="field">
                    <label>Title <span class="req">*</span></label>
                    <input type="text" name="title" required maxlength="190" value="{{ $item->title }}">
                </div>
                <div class="field">
                    <label>Type <span class="req">*</span></label>
                    <select name="kind" required>
                        @foreach ($kinds as $key => $label)
                            <option value="{{ $key }}" @selected($item->kind === $key)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="field">
                    <label>Course</label>
                    <select name="training_id">
                        <option value="">Reading for every course</option>
                        @foreach ($courses as $c)
                            <option value="{{ $c->id }}" @selected($item->training_id === $c->id)>{{ $c->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="field">
                    <label>Shelf</label>
                    <select name="status">
                        @foreach ($shelfOptions as $key => $label)
                            <option value="{{ $key }}" @selected($item->status === $key)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="field">
                    <label>The file</label>
                    <input type="file" name="file"
                           accept=".pdf,.epub,.jpg,.jpeg,.png,.doc,.docx,.ppt,.pptx,.xls,.xlsx,.txt">
                    <div class="help">On record now: {{ $item->file_name }} ({{ $item->sizeLabel() }}).
                        Only choose a file to replace it.</div>
                </div>
            </div>
            <div class="field" style="margin-top:10px;">
                <label>What it covers</label>
                <textarea name="note" rows="2" maxlength="1000">{{ $item->note }}</textarea>
            </div>
            <div class="sheet-actions">
                <button class="btn btn-sm btn-ghost" type="button" data-close-sheet>Cancel</button>
                <button class="btn btn-sm btn-lime" type="submit">Save the correction</button>
            </div>
        </form>
    @include('partials.sheet-end')
@endforeach

@include('partials.sheet-start', ['name' => 'post-item', 'title' => 'Post to the library',
    'sub' => 'Filed against a course, so the students taking that training all read the same copy.'])
    <form method="POST" action="{{ route('admin.library.store') }}" enctype="multipart/form-data" class="fee-form"
          data-confirm="Put this on the library shelf for the students?" data-title="Post to the library">
        @csrf
        <input type="hidden" name="_sheet" value="post-item">
        <div class="form-grid">
            <div class="field">
                <label>Title <span class="req">*</span></label>
                <input type="text" name="title" required maxlength="190" value="{{ old('title') }}"
                       placeholder="e.g. Solar PV Installation — Course Notes 2026">
            </div>
            <div class="field">
                <label>Type <span class="req">*</span></label>
                <select name="kind" required>
                    @foreach ($kinds as $key => $label)
                        <option value="{{ $key }}" @selected(old('kind', 'notes') === $key)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="field">
                <label>Course</label>
                <select name="training_id">
                    <option value="">Reading for every course</option>
                    @foreach ($courses as $c)
                        <option value="{{ $c->id }}" @selected(old('training_id') == $c->id)>{{ $c->name }}</option>
                    @endforeach
                </select>
                <div class="help">Pick from the training list — the catalogue is the office’s.</div>
            </div>
            <div class="field">
                <label>The file <span class="req">*</span></label>
                <input type="file" name="file" required
                       accept=".pdf,.epub,.jpg,.jpeg,.png,.doc,.docx,.ppt,.pptx,.xls,.xlsx,.txt">
                <div class="help">PDF, Word, PowerPoint, Excel, text or a picture — up to 30 MB. Kept private and
                    only opened from this shelf.</div>
            </div>
        </div>
        <div class="field" style="margin-top:10px;">
            <label>What it covers</label>
            <textarea name="note" rows="2" maxlength="1000"
                      placeholder="e.g. Units 1 to 3 of the solar course. Read before the week's practical.">{{ old('note') }}</textarea>
        </div>
        <div class="sheet-actions">
            <button class="btn btn-sm btn-ghost" type="button" data-close-sheet>Cancel</button>
            <button class="btn btn-sm btn-lime" type="submit">Post to the shelf</button>
        </div>
    </form>
@include('partials.sheet-end')
@endsection
