@php
    $docs = $enrollment->documents;
    $mode = $mode ?? null;
    $canUpload = in_array($mode, ['admin', 'trainer'], true) && $enrollment->state === 'approved';
    // The trainee sees what sits on their own record; only the office and their trainer can open the file.
    $canOpen = in_array($mode, ['admin', 'trainer'], true);
    $action = fn (string $name) => $name === 'admin'
        ? route('admin.enrollments.document.store', $enrollment)
        : route('portal.trainer.document.store', $enrollment);
    $open = fn (string $name, $d) => $name === 'admin'
        ? route('admin.enrollments.document.show', [$enrollment, $d])
        : route('portal.trainer.document.show', [$enrollment, $d]);
    $modules = $modules ?? collect();
    // Only a picture can be zoomed; a PDF or a document still opens in its own tab.
    $isImage = fn (string $name) => in_array(strtolower(pathinfo($name, PATHINFO_EXTENSION)), ['jpg', 'jpeg', 'png', 'gif', 'webp'], true);
@endphp

<h4 class="dl-group">Documents on the record ({{ $docs->count() }})</h4>
@forelse ($docs as $d)
    <div class="row-line">
        <b>{{ $d->title }}</b>
        <span class="tag gray">{{ $d->kindLabel() }}</span>
        <span class="hint">{{ $d->dated_on->format('d M Y') }} · {{ $d->sizeLabel() }}</span>
        @if ($d->module)<span class="tag">{{ $d->module->title }}</span>@endif
        @if ($canOpen)
            <a class="btn btn-sm btn-outline" href="{{ $open($mode, $d) }}"
                @if ($isImage($d->file_name)) data-zoom data-zoom-label="{{ $d->title }} — {{ $enrollment->ctp->full_name }}" @endif
                title="{{ $isImage($d->file_name) ? 'Zoom the photo' : 'Open the file' }}">Open</a>
        @else
            <span class="hint">{{ $d->file_name }}</span>
        @endif
        <span class="hint">{{ $d->uploaderLabel() }}</span>
    </div>
    @if ($d->note)<p class="hint" style="margin:0 0 6px;">{{ $d->note }}</p>@endif
@empty
    <p class="hint">Nothing has been uploaded for this trainee yet.</p>
@endforelse

@if ($canUpload)
    <details class="need-form">
        <summary>Upload a document for {{ $enrollment->ctp->full_name }}</summary>
        <form method="POST" action="{{ $action($mode) }}" enctype="multipart/form-data" class="fee-form"
              data-confirm="Add this document to their training record?" data-title="Upload document">
            @csrf
            <div class="form-grid-2">
                <div class="field">
                    <label>What it is <span class="req">*</span></label>
                    <input type="text" name="title" required maxlength="150"
                           placeholder="e.g. Term 1 report — Nyanda Technical School">
                </div>
                <div class="field">
                    <label>Type <span class="req">*</span></label>
                    <select name="kind" required>
                        @foreach ($documentKinds as $key => $label)
                            <option value="{{ $key }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="field">
                    <label>Date it covers <span class="req">*</span></label>
                    @php
                        $starts = $enrollment->cohort?->starts_on;
                        $ends = $enrollment->cohort?->ends_on;
                        $today = now()->toDateString();
                        $defaultDate = $starts && $today < $starts->toDateString()
                            ? $starts->toDateString()
                            : ($ends && $today > $ends->toDateString() ? $ends->toDateString() : $today);
                    @endphp
                    <input type="date" name="dated_on" required
                           min="{{ $starts?->toDateString() }}"
                           max="{{ $ends?->toDateString() }}"
                           value="{{ old('dated_on', $defaultDate) }}">
                </div>
                <div class="field">
                    <label>Module it belongs to</label>
                    <select name="training_module_id">
                        <option value="">Not tied to one module</option>
                        @foreach ($modules as $m)
                            <option value="{{ $m->id }}">{{ $m->title }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="field">
                    <label>The file <span class="req">*</span></label>
                    <input type="file" name="file" required accept=".pdf,.jpg,.jpeg,.png,.doc,.docx,.xls,.xlsx">
                    <div class="help">PDF, Word, Excel or a photo — up to 8 MB. Kept private and only opened from this record.</div>
                </div>
            </div>
            <div class="field" style="margin-top:10px;">
                <label>What the office should take from it</label>
                <input type="text" name="note" maxlength="1000"
                       placeholder="e.g. Passed both school papers; practical board signed off by the school.">
            </div>
            <p class="hint">It must be dated inside {{ $enrollment->cohort?->dateLabel() }} — the period the office set for this training.</p>
            <button class="btn btn-lime btn-block" type="submit" style="margin-top:10px;">Upload to their record</button>
        </form>
    </details>
@endif
