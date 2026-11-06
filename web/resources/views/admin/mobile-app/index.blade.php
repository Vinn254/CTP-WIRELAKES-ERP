@extends('layouts.admin')
@section('title', 'Mobile app download')

@section('content')
<div class="head-row">
    <div>
        <h1>Mobile app download</h1>
        <p class="page-lede">Put up the app members install on their phone and the link the public site points at.</p>
    </div>
    <div class="head-actions">
        <button class="btn btn-sm btn-lime" type="button" data-sheet="publish">Put up a new version</button>
    </div>
</div>

@include('partials.errors')

<div class="stat-row">
    <div class="stat"><small>Version out now</small><b>{{ $version ?: '—' }}</b></div>
    <div class="stat"><small>Package on the site</small><b>{{ $size ?: 'none' }}</b></div>
    <div class="stat"><small>Downloads taken</small><b>{{ $downloads }}</b></div>
    <div class="stat"><small>Put up</small><b>{{ $publishedAt ?: '—' }}</b></div>
</div>

<div class="card">
    <h3>What a visitor downloads today</h3>
    @if ($file)
        <table class="data">
            <tbody>
                <tr><th>File</th><td>{{ $file }}</td></tr>
                <tr><th>Size</th><td>{{ $size ?: '—' }}</td></tr>
                <tr><th>Version</th><td>{{ $version ?: 'not recorded' }}</td></tr>
                <tr><th>What is new</th><td>{{ $notes ?: '—' }}</td></tr>
                <tr><th>Play Store link</th><td>{{ $playUrl ?: 'the file only' }}</td></tr>
                <tr><th>Downloads</th><td>{{ $downloads }} taken from the public page</td></tr>
            </tbody>
        </table>
        <p class="hint" style="margin-top:12px;">
            Putting up a new version overwrites this one — the old package comes off the server in the same save, so a
            member can never be handed a build that the desk has already replaced.
        </p>
        <form method="POST" action="{{ route('admin.mobile-app.package.delete') }}" class="mini-form" style="margin-top:12px;"
              data-confirm="Take the app down? The public page stops offering a download until you put a package back."
              data-title="Take down">
            @csrf @method('DELETE')
            <button class="btn btn-sm btn-ghost" type="submit">Take the package down</button>
        </form>
    @else
        <p class="hint">Nothing is on the server for the public page to hand out — it tells visitors the app is not out
            yet. Use <b>Put up a new version</b> above and select the <code>.apk</code> the build produced.</p>
    @endif
</div>

<div class="card">
    <h3>The link on the website</h3>
    <p class="hint">This page is in the top menu and the footer of every public page, so a member finds it without
        being told where to look.</p>
    <p style="margin-top:10px;">
        <a class="btn btn-sm btn-outline" href="{{ $pageUrl }}" target="_blank" rel="noopener">Open the download page ↗</a>
    </p>
</div>

@include('partials.sheet-start', ['name' => 'publish', 'title' => 'Put up a new version',
    'sub' => 'Save overwrites the package on the download page. Leave the file box empty to change only the words and the link.'])
    <form method="POST" action="{{ route('admin.mobile-app.save') }}" enctype="multipart/form-data" class="fee-form">
        @csrf
        <input type="hidden" name="_sheet" value="publish">
        <div class="form-grid">
            <div class="field">
                <label>App package (.apk)</label>
                <input type="file" name="package" accept=".apk,application/vnd.android.package-archive">
                <small class="hint">The file from <code>flutter build apk --release</code>, up to 200 MB.</small>
            </div>
            <div class="field">
                <label>Version</label>
                <input type="text" name="version" maxlength="40" value="{{ old('version', $version) }}"
                       placeholder="1.0 (build 1)">
            </div>
            <div class="field">
                <label>Google Play link</label>
                <input type="url" name="play_url" maxlength="200" value="{{ old('play_url', $playUrl) }}"
                       placeholder="https://play.google.com/…">
                <small class="hint">Optional. Shown beside the download button once the listing is live.</small>
            </div>
        </div>
        <div class="field" style="margin-top:12px;">
            <label>What is new in this version</label>
            <textarea name="notes" rows="3" maxlength="600">{{ old('notes', $notes) }}</textarea>
            <small class="hint">Printed on the download page, so a member knows why to update.</small>
        </div>
        <button class="btn btn-lime btn-block" type="submit" style="margin-top:14px;">Save to the download page</button>
    </form>
@include('partials.sheet-end')
@endsection
