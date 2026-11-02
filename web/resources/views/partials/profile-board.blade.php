@php
    use App\Support\Profile;

    $statusClass = ['pending' => 'pending_review', 'approved' => 'active', 'returned' => 'rejected',
        'rejected' => 'rejected', 'cancelled' => 'closed'];
    $photo = $record->photo_path ?? $record->passport_photo_path ?? null;

    // What the file has never held — the details still to type and the papers still to send in. The person is met
    // with this list rather than left to find the gaps themselves. A detail only the office types is not their gap.
    $gaps = collect($lines)->where('ro', false)
        ->filter(fn (array $line) => $line['raw'] === null || $line['raw'] === '')
        ->pluck('label');
    $need = $gaps->merge(collect($missing)->map(fn (string $kind) => $papers[$kind]))->values();
    $more = max(0, $need->count() - 6);
    $short = $need->take(6)->join(', ').($more ? ', and '.$more.' more' : '');

    $initials = collect(explode(' ', trim(Profile::person($subjectType, $record))))
        ->filter()->take(2)->map(fn ($w) => mb_strtoupper(mb_substr($w, 0, 1)))->join('');
@endphp

<div class="card" id="on-file">
    <div class="section-head" style="display:flex;gap:10px;align-items:center;flex-wrap:wrap;margin-top:0;">
        <h3>On file</h3>
        <span class="tag">{{ $kindTitle }}</span>
        <span class="hint">answered by {{ $office }}</span>
        <div class="head-acts" style="margin-left:auto;">
            <button class="btn btn-sm btn-lime" type="button" data-sheet="paper">Send a document</button>
            @if (! $waiting)
                <button class="btn btn-sm btn-lime" type="button" data-sheet="fill">Add my details</button>
            @endif
        </div>
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
            <p class="hint">This is what {{ $office }} holds. Anything missing or wrong goes in through
                <b>Add my details</b> and is written onto your record only when {{ $office }} agrees.</p>
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
            <thead>
                <tr><th>Detail</th><th>On file</th><th>Complete</th></tr>
            </thead>
            <tbody>
                @foreach ($lines as $line)
                    <tr>
                        <th class="nowrap" style="width:230px;">{{ $line['label'] }}</th>
                        <td>
                            @if ($line['raw'] === null || $line['raw'] === '')
                                <span class="hint">Not on file</span>
                            @else
                                {{ $line['value'] }}
                            @endif
                        </td>
                        <td class="nowrap" style="width:150px;">
                            @if ($line['ro'])
                                <span class="hint">typed by {{ $office }}</span>
                            @elseif ($line['raw'] === null || $line['raw'] === '')
                                <button class="btn btn-sm btn-outline" type="button" data-sheet="fill"
                                        data-sheet-focus="{{ $line['field'] }}">Add</button>
                            @else
                                <button class="btn btn-sm btn-ghost" type="button" data-sheet="fill"
                                        data-sheet-focus="{{ $line['field'] }}">Correct</button>
                            @endif
                        </td>
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

@include('partials.sheet-start', ['name' => 'paper', 'title' => 'Send a document',
    'sub' => 'A photograph of the paper is enough. It goes to '.$office.' to be checked.'])
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

@if ($waiting)
    <h2 class="sec-title">Waiting on {{ $office }}</h2>

    <div class="card">
        <div class="section-head">
            <h3>Your last ask</h3>
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
@endif

@include('partials.sheet-start', ['name' => 'fill', 'title' => 'Add my details',
    'sub' => 'Fill what '.$office.' is missing, or correct what is wrong. Leave anything you do not know blank.',
    'openOnLoad' => $need->isNotEmpty() && ! $waiting])
    <form method="POST" action="{{ route($changeRoute) }}" class="fee-form"
          data-confirm="Send these details to {{ $office }} for approval?" data-title="Add my details">
        @csrf
        <input type="hidden" name="_sheet" value="fill">
        @include('partials.errors')
        @if ($need->isNotEmpty() && ! $waiting)
            <p class="sheet-sub"><b>Your profile is not complete.</b> Still to fill: {{ $short }}.
                Send what you have to {{ $office }} to be checked.</p>
        @endif
        <div class="form-grid-2">
            @foreach ($lines as $line)
                @continue($line['ro'])
                <div class="field @if ($line['kind'] === 'names') full @endif">
                    <label>{{ $line['label'] }}</label>
                    @if ($line['kind'] === 'names')
                        <div class="name-parts">
                            <input type="text" name="name_first" data-field="{{ $line['field'] }}" maxlength="80"
                                   placeholder="First name" value="{{ old('name_first', $line['parts']['first']) }}">
                            <input type="text" name="name_middle" maxlength="80" placeholder="Middle name"
                                   value="{{ old('name_middle', $line['parts']['middle']) }}">
                            <input type="text" name="name_last" maxlength="80" placeholder="Last name"
                                   value="{{ old('name_last', $line['parts']['last']) }}">
                        </div>
                        <div class="help">The three names go on the record as one name.</div>
                    @elseif ($line['kind'] === 'choice')
                        <select name="{{ $line['field'] }}" data-field="{{ $line['field'] }}">
                            <option value="">Not on file</option>
                            @foreach ($line['choices'] as $key => $label)
                                <option value="{{ $key }}"
                                    @selected((string) old($line['field'], (string) $line['raw']) === (string) $key)>{{ $label }}</option>
                            @endforeach
                        </select>
                    @elseif ($line['kind'] === 'date')
                        <input type="date" name="{{ $line['field'] }}" data-field="{{ $line['field'] }}"
                               value="{{ old($line['field'], $line['raw']) }}">
                    @elseif ($line['kind'] === 'number')
                        <input type="number" name="{{ $line['field'] }}" data-field="{{ $line['field'] }}"
                               value="{{ old($line['field'], $line['raw']) }}">
                    @else
                        <input type="text" name="{{ $line['field'] }}" data-field="{{ $line['field'] }}" maxlength="180"
                               value="{{ old($line['field'], $line['raw']) }}">
                    @endif
                </div>
            @endforeach
        </div>
        <div class="field">
            <label>Why, if the office needs to know</label>
            <textarea name="note" rows="2" maxlength="500" placeholder="Anything that helps them answer">{{ old('note') }}</textarea>
        </div>
        <div class="sheet-actions">
            <button class="btn btn-sm btn-ghost" type="button" data-close-sheet>Later</button>
            <button class="btn btn-sm btn-lime" type="submit">Send to {{ $office }}</button>
        </div>
    </form>
@include('partials.sheet-end')

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
