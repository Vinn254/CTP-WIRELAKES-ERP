@php
    use App\Models\Need;
    use App\Models\Project;
    $community = $need->community;
@endphp

<div class="need-actions">
    @if ($need->status === 'converted')
        <div class="row-line">
            <span class="hint">Became</span>
            @if ($need->project)
                <a href="{{ dept_link('admin.projects.show', ['project' => $need->project_id]) }}"><b>{{ $need->project->reference }} · {{ $need->project->title }}</b></a>
                <span class="status {{ $need->project->status }}">{{ str_replace('_', ' ', $need->project->status) }}</span>
            @else
                <span class="hint">its project was removed</span>
            @endif
            <span class="hint">on {{ $need->converted_at?->format('d M Y') }}</span>
        </div>
    @endif

    <form method="POST" action="{{ route('admin.needs.priority', $need) }}" class="inline-form"
          data-confirm="Set the priority on this need?" data-title="Priority">
        @csrf
        <label class="hint" for="need-priority-{{ $need->id }}">Priority</label>
        <select name="priority" id="need-priority-{{ $need->id }}">
            @foreach (Need::PRIORITIES as $key => $label)
                <option value="{{ $key }}" @selected($need->priority === $key)>{{ $label }}</option>
            @endforeach
        </select>
        <button class="btn btn-sm btn-outline" type="submit">Save</button>
    </form>

    @if ($need->status !== 'converted')
        <div class="lifecycle">
            @if ($need->status !== 'prioritised')
                <form method="POST" action="{{ route('admin.needs.status', $need) }}" class="mini-form"
                      data-confirm="Prioritise this need? It moves into the funding pipeline." data-title="Prioritise">
                    @csrf
                    <input type="hidden" name="status" value="prioritised">
                    <button class="btn btn-sm btn-lime" type="submit">Prioritise</button>
                </form>
            @endif
            @if ($need->status === 'prioritised')
                <form method="POST" action="{{ route('admin.needs.status', $need) }}" class="mini-form"
                      data-confirm="Send it back to the CTP list as unreviewed?" data-title="Move back">
                    @csrf
                    <input type="hidden" name="status" value="proposed">
                    <button class="btn btn-sm btn-ghost" type="submit">Un-prioritise</button>
                </form>
            @endif
            @if ($need->status !== 'closed')
                <form method="POST" action="{{ route('admin.needs.status', $need) }}" class="mini-form"
                      data-confirm="Close this need without action? It stops counting in the pipeline." data-title="Close need">
                    @csrf
                    <input type="hidden" name="status" value="closed">
                    <button class="btn btn-sm btn-danger" type="submit">Close</button>
                </form>
            @else
                <form method="POST" action="{{ route('admin.needs.status', $need) }}" class="mini-form"
                      data-confirm="Reopen this need?" data-title="Reopen need">
                    @csrf
                    <input type="hidden" name="status" value="proposed">
                    <button class="btn btn-sm btn-ghost" type="submit">Reopen</button>
                </form>
            @endif
        </div>
    @endif

    @if ($need->isLive())
        <details class="convert">
            <summary>Turn this need into a project</summary>
            <form method="POST" action="{{ route('admin.needs.convert', $need) }}"
                  data-confirm="Create a draft project for this need? You publish it from Projects." data-title="Convert to project">
                @csrf
                <div class="field">
                    <label>Project title <span class="req">*</span></label>
                    <input type="text" name="title" required maxlength="160"
                           value="{{ old('title', $need->title) }}">
                </div>
                <div class="field">
                    <label>Brief <span class="req">*</span></label>
                    <textarea name="description" rows="4" required maxlength="8000">{{ old('description', $need->detail ?: $need->title.' — flagged in '.$community->name.' ('.$community->shortLocation().').') }}</textarea>
                    <div class="help">Posted as a draft the programme can edit. The community and its ward are carried over.</div>
                </div>
                <div class="form-grid-2">
                    <div class="field">
                        <label>Project type <span class="req">*</span></label>
                        <select name="category" required>
                            @foreach (Project::CATEGORIES as $key => $rule)
                                <option value="{{ $key }}" @selected($key === 'attachment')>{{ $rule['label'] }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="field">
                        <label>Trade</label>
                        <input type="text" name="trade" maxlength="80" value="{{ old('trade', $community->name) }}">
                    </div>
                    <div class="field">
                        <label>Budget (KSh)</label>
                        <input type="number" step="0.01" min="0" name="budget" value="{{ old('budget') }}">
                    </div>
                    <div class="field">
                        <label>Applications close</label>
                        <input type="date" name="deadline" value="{{ old('deadline') }}">
                    </div>
                </div>
                <button class="btn btn-primary btn-sm" type="submit">Create draft project</button>
            </form>
        </details>
    @endif

    @if ($need->photo_path)
        <a class="doc-link" href="{{ route('admin.needs.photo', $need) }}" target="_blank" rel="noopener" data-zoom data-zoom-label="{{ $need->title ?: $need->reference ?: 'Site photo' }}">Site photo ↗</a>
    @endif
</div>
