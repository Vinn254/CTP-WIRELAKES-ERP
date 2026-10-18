@extends('layouts.admin')
@section('title', $pageTitle ?? 'Edit content')

@php
    use App\Http\Controllers\Admin\ContentController;
    $pageTitle = ContentController::PAGES[$page];
    $visibles = $sections->where('is_visible', true)->count();
    $publicUrl = $page === 'site' ? route('home') : ($page === 'membership' ? route('register.ctp') : route($page));
    $fieldTypes = ['text' => 'Short text', 'textarea' => 'Long text', 'image' => 'Image upload', 'url' => 'URL / link', 'price' => 'Price (KSh)'];
@endphp

@section('content')
<a class="back" href="{{ route('admin.content.index') }}">Back to all pages</a>

<div class="page-head">
    <div>
        <h1>{{ $pageTitle }}</h1>
        <p class="sub">{{ $sections->count() }} section(s), {{ $visibles }} visible</p>
    </div>
    <a class="btn btn-outline btn-sm" href="{{ $publicUrl }}" target="_blank">View on public site ↗</a>
</div>

<p class="hint" style="margin:-6px 0 18px;">
    Change any text, upload pictures, reorder with the <b>Order</b> numbers or switch a section off — then press
    <b>Save changes</b>. Use <b>Add field</b> to create brand-new fields; they appear on the public page automatically.
</p>

<form method="POST" action="{{ route('admin.content.update', $page) }}" enctype="multipart/form-data" class="content-form">
    @csrf

    @forelse ($sections as $section)
        <fieldset class="sec">
            <legend>
                <span class="sec-name">{{ $section->name }}</span>
                <span class="tag {{ $section->kind === 'repeater' ? 'gold' : '' }}">{{ $section->kind }}</span>
                <label class="sec-vis"><input type="checkbox" name="visible[{{ $section->id }}]" value="1" @checked($section->is_visible)> Visible</label>
                <label class="ord-wrap">Order <input class="ord" type="number" name="section_order[{{ $section->id }}]" value="{{ $section->sort_order }}"></label>
                <button type="button" class="btn btn-sm btn-danger del" form="delsec-{{ $section->id }}">Delete section</button>
            </legend>

            @if ($section->kind === 'single')
                @php $entry = $section->entries->sortBy('sort_order')->first(); @endphp
                @if ($entry)
                    <div class="fields-grid">
                        @foreach ($section->fields->sortBy('sort_order') as $field)
                            @include('admin.content.partials.field', ['section' => $section, 'entry' => $entry, 'field' => $field])
                        @endforeach
                    </div>
                @else
                    <p class="hint">This section has no entry yet — press “Add row”.</p>
                @endif
            @else
                @foreach ($section->entries->sortBy('sort_order') as $i => $entry)
                    <div class="repeater-row">
                        <div class="row-head">
                            <b>Item {{ $i + 1 }}</b>
                            <label class="ord-wrap">Order <input class="ord" type="number" name="entry_order[{{ $entry->id }}]" value="{{ $entry->sort_order }}"></label>
                            <button type="button" class="btn btn-sm btn-ghost del-row" form="delrow-{{ $entry->id }}">Delete row</button>
                        </div>
                        <div class="fields-grid">
                            @foreach ($section->fields->sortBy('sort_order') as $field)
                                @include('admin.content.partials.field', ['section' => $section, 'entry' => $entry, 'field' => $field])
                            @endforeach
                        </div>
                    </div>
                @endforeach
            @endif

            <div class="row-tools">
                <button type="button" class="btn btn-sm btn-outline" form="addrow-{{ $section->id }}">Add row</button>
                <span class="new-field">
                    <input type="text" name="label" form="addfld-{{ $section->id }}" placeholder="New field name, e.g. Mission" maxlength="120" aria-label="New field name">
                    <select name="type" form="addfld-{{ $section->id }}" aria-label="New field type">
                        @foreach ($fieldTypes as $k => $lbl)
                            <option value="{{ $k }}">{{ $lbl }}</option>
                        @endforeach
                    </select>
                    <button type="button" class="btn btn-sm btn-outline" form="addfld-{{ $section->id }}">Add field</button>
                </span>
            </div>
        </fieldset>
    @empty
        <div class="card"><p style="margin:0;">This page has no sections yet — add one below.</p></div>
    @endforelse

    <div class="save-bar">
        <button type="submit" class="btn btn-primary btn-lg">Save changes</button>
    </div>
</form>

<h2 class="sec-title">Add a new section</h2>
<div class="card">
    <form method="POST" action="{{ route('admin.content.section.add', $page) }}" class="inline-form">
        @csrf
        <span class="fld"><label>Section name <span class="req">*</span></label><input type="text" name="name" placeholder="e.g. Success stories" maxlength="120" required></span>
        <span class="fld"><label>Kind</label>
            <select name="kind">
                <option value="single">Single block (one set of fields)</option>
                <option value="repeater">Repeater (many rows / cards)</option>
            </select>
        </span>
        <button class="btn btn-primary btn-sm" type="submit">Add section</button>
    </form>
    <div class="hint" style="margin-top:10px;">A starter “Title” field is created for you — add more fields afterwards. The section appears on the public page once saved.</div>
</div>

{{-- Structure forms live outside the save form; the buttons above hook in via the HTML form attribute. --}}
<div class="structure-forms">
    @foreach ($sections as $section)
        <form id="addrow-{{ $section->id }}" method="POST" action="{{ route('admin.content.entry.add', $section->id) }}">@csrf</form>
        <form id="addfld-{{ $section->id }}" method="POST" action="{{ route('admin.content.field.add', $section->id) }}">@csrf</form>
        <form id="delsec-{{ $section->id }}" method="POST" action="{{ route('admin.content.section.delete', [$page, $section->id]) }}"
              data-danger data-confirm="Delete the section “{{ $section->name }}” and all of its content? This cannot be undone.">
            @csrf @method('DELETE')
        </form>
        @foreach ($section->fields as $field)
            <form id="delfld-{{ $field->id }}" method="POST" action="{{ route('admin.content.field.delete', $field->id) }}"
                  data-danger data-confirm="Delete the “{{ $field->label }}” field and every value saved in it?">
                @csrf @method('DELETE')
            </form>
        @endforeach
        @foreach ($section->entries as $entry)
            <form id="delrow-{{ $entry->id }}" method="POST" action="{{ route('admin.content.entry.delete', $entry->id) }}"
                  data-danger data-confirm="Delete this row and everything typed into it?">
                @csrf @method('DELETE')
            </form>
        @endforeach
    @endforeach
</div>
@endsection
