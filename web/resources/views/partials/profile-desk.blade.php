@php
    use App\Support\Profile;

    $statusClass = ['pending' => 'pending_review', 'approved' => 'active', 'returned' => 'rejected',
        'rejected' => 'rejected', 'cancelled' => 'closed'];

    // Only the desk keeping this kind of record answers it, whoever is looking at the page.
    $answers = fn (string $type) => Profile::decides(auth()->user(), $type);
@endphp

<h2 class="sec-title">Documents</h2>

@if ($uploadRoute ?? null)
    <div class="head-acts" style="margin-bottom:18px;">
        <button class="btn btn-sm btn-lime" type="button" data-sheet="desk-paper">File a document</button>
    </div>

    @include('partials.sheet-start', ['name' => 'desk-paper', 'title' => 'File a document on this record',
        'sub' => 'A paper handed over at the counter is put straight on the file — it is already seen by this desk.'])
        <form method="POST" action="{{ route($uploadRoute) }}" enctype="multipart/form-data" class="fee-form"
              data-confirm="Put this document on the record?" data-title="File a document">
            @csrf
            <input type="hidden" name="_sheet" value="desk-paper">
            <input type="hidden" name="subject_type" value="{{ $subjectType }}">
            <input type="hidden" name="subject_id" value="{{ $record->id }}">
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
                <button class="btn btn-sm btn-lime" type="submit">File it</button>
            </div>
        </form>
    @include('partials.sheet-end')
@endif

@forelse ($documents as $document)
    <div class="card">
        <div class="section-head">
            <h3>{{ $document->kindLabel() }}@if (! ($scope ?? null)) — {{ $document->personLabel() }}@endif</h3>
            @if (! ($scope ?? null))<span class="tag">{{ Profile::kindLabel($document->subject_type) }}</span>@endif
            <span class="status {{ $statusClass[$document->status] ?? 'closed' }}">{{ $document->statusLabel() }}</span>
        </div>
        <p class="hint">{{ $document->file_name }} · {{ $document->sizeLabel() }} · sent in
            {{ $document->created_at->format('d M Y') }} by {{ $document->uploader?->name ?: 'the office' }}</p>
        <div class="head-acts">
            <a class="btn btn-sm btn-outline" href="{{ route($fileRoute, $document) }}">Open</a>
            <a class="btn btn-sm btn-outline" href="{{ download_href(route($fileRoute, $document)) }}">Download</a>
            @if ($document->isWaiting() && $answers($document->subject_type))
                <form method="POST" action="{{ route($decideRoute, $document) }}" class="inline-form"
                      data-confirm="Take the {{ strtolower($document->kindLabel()) }} onto {{ $document->personLabel() }}’s file?"
                      data-title="Accept document">
                    @csrf
                    <input type="hidden" name="decision" value="approved">
                    <button class="btn btn-sm btn-primary" type="submit">Accept</button>
                </form>
                <button class="btn btn-sm btn-outline" type="button" data-sheet="back-{{ $document->id }}">
                    Send back</button>
            @elseif ($document->decision_note)
                <span class="hint">{{ $document->decision_note }}</span>
            @endif
        </div>
    </div>

    @if ($document->isWaiting() && $answers($document->subject_type))
        @include('partials.sheet-start', ['name' => 'back-'.$document->id, 'title' => 'Send the paper back',
            'sub' => 'Say what needs fixing, in the words the person reads on their own profile.'])
            <form method="POST" action="{{ route($decideRoute, $document) }}" class="fee-form"
                  data-confirm="Send this paper back for a better copy?" data-title="Send paper back">
                @csrf
                <input type="hidden" name="_sheet" value="back-{{ $document->id }}">
                <input type="hidden" name="decision" value="returned">
                <div class="field">
                    <label>What is wrong with it</label>
                    <textarea name="note" rows="2" maxlength="500"
                              placeholder="Blurry, the edges are cut, the wrong paper">{{ old('note') }}</textarea>
                </div>
                <div class="sheet-actions">
                    <button class="btn btn-sm btn-ghost" type="button" data-close-sheet>Cancel</button>
                    <button class="btn btn-sm btn-lime" type="submit">Send it back</button>
                </div>
            </form>
        @include('partials.sheet-end')
    @endif
@empty
    <div class="card"><p class="page-lede">No document is waiting to be checked.</p></div>
@endforelse

<h2 class="sec-title">Asks to answer</h2>

@forelse ($changes as $change)
    <div class="card">
        <div class="section-head">
            <h3>{{ $change->personLabel() }} asks to change
                {{ collect($change->lines())->pluck('label')->join(', ') }}</h3>
            @if (! ($scope ?? null))<span class="tag">{{ Profile::kindLabel($change->subject_type) }}</span>@endif
            <span class="status pending_review">{{ $change->statusLabel() }}</span>
        </div>
        <div class="table-scroll">
            <table class="data">
                <thead><tr><th>Field</th><th>On file now</th><th>Asked for</th></tr></thead>
                <tbody>
                    @foreach ($change->lines() as $line)
                        <tr><td>{{ $line['label'] }}</td><td>{{ $line['from'] }}</td><td><b>{{ $line['to'] }}</b></td></tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @if ($change->note)<p class="hint">They said: {{ $change->note }}</p>@endif
        @if ($answers($change->subject_type))
            <form method="POST" action="{{ route($answerRoute, $change) }}"
                  data-confirm="Write these changes onto {{ $change->personLabel() }}’s record?"
                  data-title="Answer the ask">
                @csrf
                @include('partials.errors')
                <div class="form-grid-2">
                    <div class="field">
                        <label>Your answer</label>
                        <select name="decision" required>
                            <option value="approved">Approve and write onto the record</option>
                            <option value="rejected">Decline</option>
                        </select>
                    </div>
                    <div class="field">
                        <label>Note for their file</label>
                        <input type="text" name="note" maxlength="500" placeholder="Where a proof is still missing">
                    </div>
                </div>
                <div class="form-actions">
                    <button class="btn btn-primary" type="submit">Send the answer</button>
                </div>
            </form>
        @endif
    </div>
@empty
    <div class="card"><p class="page-lede">No profile change is waiting on this desk.</p></div>
@endforelse
