@php
    use App\Support\Technology;
@endphp

<div class="form-grid">
    <div class="field">
        <label>Sub-area <span class="req">*</span></label>
        <select name="category" required>
            @foreach ($categories as $key => $c)
                <option value="{{ $key }}" @selected(old('category', $project->category) === $key)>{{ $c['label'] }}</option>
            @endforeach
        </select>
    </div>
    <div class="field">
        <label>Title <span class="req">*</span></label>
        <input type="text" name="title" required maxlength="200" value="{{ old('title', $project->title) }}" placeholder="e.g. School attendance chatbot">
    </div>
    <div class="field">
        <label>Technology used</label>
        <input type="text" name="technology" maxlength="300" value="{{ old('technology', $project->technology) }}" placeholder="e.g. Python, an LLM, ESP32 kit">
    </div>
    <div class="field">
        <label>Stage <span class="req">*</span></label>
        <select name="status" required>
            @foreach (Technology::PROJECT_STATUSES as $key => $label)
                <option value="{{ $key }}" @selected(old('status', $project->status) === $key)>{{ $label }}</option>
            @endforeach
        </select>
    </div>
    <div class="field">
        <label>Progress</label>
        <input type="number" name="progress_percent" min="0" max="100" value="{{ old('progress_percent', $project->progress_percent) }}">
    </div>
    <div class="field">
        <label>Team size</label>
        <input type="number" name="team_size" min="0" max="65535" value="{{ old('team_size', $project->team_size) }}">
    </div>
    <div class="field">
        <label>Mentoring E-Fundi</label>
        <select name="mentor_fundi_id">
            <option value="">No mentor yet</option>
            @foreach ($fundis as $f)
                <option value="{{ $f->id }}" @selected((int) old('mentor_fundi_id', $project->mentor_fundi_id) === $f->id)>{{ $f->full_name }}{{ $f->ef_code ? ' ('.$f->ef_code.')' : '' }}</option>
            @endforeach
        </select>
    </div>
    <div class="field">
        <label>Community it serves</label>
        <select name="community_id">
            <option value="">Not tied to a community</option>
            @foreach ($communities as $c)
                <option value="{{ $c->id }}" @selected((int) old('community_id', $project->community_id) === $c->id)>{{ $c->name }}{{ $c->county ? ' — '.$c->county : '' }}</option>
            @endforeach
        </select>
    </div>
    <div class="field">
        <label>Linked programme project</label>
        <select name="project_id">
            <option value="">Runs on its own</option>
            @foreach ($projects as $p)
                <option value="{{ $p->id }}" @selected((int) old('project_id', $project->project_id) === $p->id)>{{ $p->reference }} — {{ str($p->title)->limit(40) }}</option>
            @endforeach
        </select>
    </div>
</div>
<div class="field">
    <label>Summary</label>
    <textarea name="summary" rows="2" maxlength="5000">{{ old('summary', $project->summary) }}</textarea>
</div>
<div class="field">
    <label>The problem it tackles</label>
    <textarea name="problem" rows="2" maxlength="5000">{{ old('problem', $project->problem) }}</textarea>
</div>
