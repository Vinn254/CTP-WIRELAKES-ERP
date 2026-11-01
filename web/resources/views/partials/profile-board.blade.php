@php
    use App\Support\Profile;

    $statusClass = ['pending' => 'pending_review', 'approved' => 'active', 'returned' => 'rejected',
        'rejected' => 'rejected', 'cancelled' => 'closed'];
    $photo = $record->photo_path ?? $record->passport_photo_path ?? null;

    $initials = collect(explode(' ', trim(Profile::person($subjectType, $record))))
        ->filter()->take(2)->map(fn ($w) => mb_strtoupper(mb_substr($w, 0, 1)))->join('');
@endphp

<div class="card">
    <div class="section-head">
        <h3>On file</h3>
        <span class="tag">{{ $kindTitle }}</span>
        <span class="hint">answered by {{ $office }}</span>
    </div>

    <div style="display:flex;gap:14px;align-items:center;margin-bottom:14px;">
        @if ($photo)
            <a class="avatar-lg has-photo" href="{{ route($photoRoute) }}" data-zoom aria-label="Open the profile photo">
                <img src="{{ route($photoRoute) }}" alt="Profile photo">
            </a>
        @else
            <span class="avatar-lg">{{ $initials }}</span>
        @endif
        <div>
            <b>{{ Profile::person($subjectType, $record) }}</b>
            <p class="hint">This is what {{ $office }} holds. Change any of it by asking them below — the form is
                never written onto the record by itself.</p>
            @if (($addPhoto ?? false) && ! $photo)
                <form method="POST" action="{{ route('portal.photo.store') }}" enctype="multipart/form-data"
                      class="inline-form" data-confirm="Put this picture on your profile?" data-title="Profile photo">
                    @csrf
                    <div style="display:flex;gap:8px;align-items:center;">
                        <input type="file" name="passport_photo" accept=".jpg,.jpeg,.png" required>
                        <button class="btn btn-sm btn-outline" type="submit">Add my photo</button>
                    </div>
                </form>
            @endif
        </div>
    </div>

    <div class="table-scroll">
        <table class="data">
            <tbody>
                @foreach ($lines as $line)
                    <tr>
                        <th class="nowrap" style="width:230px;">{{ $line['label'] }}</th>
                        <td>{{ $line['value'] }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>

<h2 class="sec-title">Documents</h2>

@if ($missing !== [])
    <p class="hint">Still needed: {{ collect($missing)->map(fn ($k) => $papers[$k])->join(', ') }}.</p>
@endif

<div class="head-acts" style="margin-bottom:18px;">
    <button class="btn btn-sm btn-lime" type="button" data-sheet="paper">Send a document</button>
</div>

@include('partials.sheet-start', ['name' => 'paper', 'title' => 'Send a document',
    'sub' => 'A photograph of the paper is enough. It goes to '.ucfirst($office).' to be checked.'])
    <form method="POST" action="{{ route($uploadRoute) }}" enctype="multipart/form-data" class="fee-form"
          data-confirm="Send this document to {{ $office }}?" data-title="Send a document">
        @csrf
        <input type="hidden" name="_sheet" value="paper">
        @include('partials.errors')
        <div class="field">
            <label>Which document <span class="req">*</span></label>
            <select name="kind" required>
                @foreach ($papers as $key => $label)
                    <option value="{{ $key }}" @selected(old('kind') === $key)>{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <div class="field">
            <label>The file <span class="req">*</span></label>
            <input type="file" name="file" accept=".jpg,.jpeg,.png,.webp,.pdf" required>
            <div class="help">JPG, PNG or PDF, up to 8 MB.</div>
        </div>
        <div class="sheet-actions">
            <button class="btn btn-sm btn-ghost" type="button" data-close-sheet>Cancel</button>
            <button class="btn btn-sm btn-lime" type="submit">Send to {{ $office }}</button>
        </div>
    </form>
@include('partials.sheet-end')

@if ($documents->isEmpty())
    <div class="card"><p class="page-lede">Nothing has been sent in from this profile yet.</p></div>
@else
    <div class="table-scroll">
        <table class="data">
            <thead>
                <tr><th>Document</th><th>File</th><th>Sent</th><th>Status</th>
                    <th>{{ ucfirst($office) }}</th><th></th></tr>
            </thead>
            <tbody>
                @foreach ($documents as $document)
                    <tr>
                        <td><b>{{ $document->kindLabel() }}</b></td>
                        <td class="nowrap">
                            <a class="btn btn-sm btn-outline" href="{{ route($fileRoute, $document) }}">Open</a>
                            <a class="btn btn-sm btn-outline"
                               href="{{ download_href(route($fileRoute, $document)) }}">Download</a>
                        </td>
                        <td class="nowrap">{{ $document->created_at->format('d M Y') }}</td>
                        <td><span class="status {{ $statusClass[$document->status] ?? 'closed' }}">{{ $document->statusLabel() }}</span></td>
                        <td>
                            @if ($document->decision_note)<div class="hint">{{ $document->decision_note }}</div>@endif
                            @if ($document->decidedBy)
                                <div class="hint">{{ $document->decidedBy->name }} · {{ $document->decided_at?->format('d M Y') }}</div>
                            @endif
                        </td>
                        <td class="actions">
                            @if ($document->replaceable())
                                <form method="POST" action="{{ route($uploadRoute) }}" enctype="multipart/form-data"
                                      data-confirm="Send a better copy of the {{ $document->kindLabel() }}?"
                                      data-title="Send a better copy">
                                    @csrf
                                    <input type="hidden" name="document" value="{{ $document->id }}">
                                    <input type="hidden" name="kind" value="{{ $document->kind }}">
                                    <div style="display:flex;gap:8px;align-items:center;">
                                        <input type="file" name="file" accept=".jpg,.jpeg,.png,.webp,.pdf" required>
                                        <button class="btn btn-sm btn-outline" type="submit">Send again</button>
                                    </div>
                                </form>
                                @if ($document->isWaiting())
                                    <form method="POST" action="{{ route($dropRoute, $document) }}"
                                          data-confirm="Withdraw the {{ $document->kindLabel() }}?"
                                          data-title="Withdraw paper">
                                        @csrf
                                        <button class="btn btn-sm btn-ghost" type="submit">Withdraw</button>
                                    </form>
                                @endif
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@endif

<h2 class="sec-title">Ask for a change</h2>

@if ($waiting)
    <div class="card">
        <div class="section-head">
            <h3>Waiting on {{ $office }}</h3>
            <span class="status pending_review">{{ $waiting->statusLabel() }}</span>
        </div>
        <div class="table-scroll">
            <table class="data">
                <thead><tr><th>Field</th><th>On file now</th><th>You asked for</th></tr></thead>
                <tbody>
                    @foreach ($waiting->lines() as $line)
                        <tr><td>{{ $line['label'] }}</td><td>{{ $line['from'] }}</td><td><b>{{ $line['to'] }}</b></td></tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @if ($waiting->note)<p class="hint">You said: {{ $waiting->note }}</p>@endif
        <form method="POST" action="{{ route($withdrawChangeRoute, $waiting) }}"
              data-confirm="Withdraw this ask so you can send another one?" data-title="Withdraw ask">
            @csrf
            <button class="btn btn-sm btn-outline" type="submit">Withdraw the ask</button>
        </form>
    </div>
@else
    <form method="POST" action="{{ route($changeRoute) }}" class="card"
          data-confirm="Send these changes to {{ $office }} for approval?" data-title="Ask for a change">
        @csrf
        @include('partials.errors')
        <p class="page-lede">Correct anything that is wrong. The office writes it onto your record when they agree.</p>
        <div class="form-grid-3">
            @foreach ($lines as $line)
                <div class="field">
                    <label>{{ $line['label'] }}</label>
                    @if ($line['kind'] === 'choice')
                        <select name="{{ $line['field'] }}">
                            <option value="">Not on file</option>
                            @foreach ($line['choices'] as $key => $label)
                                <option value="{{ $key }}"
                                    @selected((string) old($line['field'], (string) $line['raw']) === (string) $key)>{{ $label }}</option>
                            @endforeach
                        </select>
                    @elseif ($line['kind'] === 'date')
                        <input type="date" name="{{ $line['field'] }}" value="{{ old($line['field'], $line['raw']) }}">
                    @elseif ($line['kind'] === 'number')
                        <input type="number" name="{{ $line['field'] }}" value="{{ old($line['field'], $line['raw']) }}">
                    @else
                        <input type="text" name="{{ $line['field'] }}" maxlength="180"
                               value="{{ old($line['field'], $line['raw']) }}">
                    @endif
                </div>
            @endforeach
        </div>
        <div class="field">
            <label>Why, if the office needs to know</label>
            <textarea name="note" rows="2" maxlength="500" placeholder="Anything that helps them answer">{{ old('note') }}</textarea>
        </div>
        <div class="form-actions">
            <button class="btn btn-primary" type="submit">Send the ask to {{ $office }}</button>
        </div>
    </form>
@endif

@if ($changes->isNotEmpty())
    <h2 class="sec-title">Every ask from this profile</h2>
    <div class="table-scroll">
        <table class="data">
            <thead><tr><th>Asked</th><th>Fields</th><th>Status</th><th>{{ ucfirst($office) }}</th></tr></thead>
            <tbody>
                @foreach ($changes as $change)
                    <tr>
                        <td class="nowrap">{{ $change->created_at->format('d M Y') }}</td>
                        <td>{{ collect($change->lines())->pluck('label')->join(', ') }}</td>
                        <td><span class="status {{ $statusClass[$change->status] ?? 'closed' }}">{{ $change->statusLabel() }}</span></td>
                        <td>
                            @if ($change->decision_note)<div class="hint">{{ $change->decision_note }}</div>@endif
                            @if ($change->decidedBy)
                                <div class="hint">{{ $change->decidedBy->name }} · {{ $change->decided_at?->format('d M Y') }}</div>
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@endif
