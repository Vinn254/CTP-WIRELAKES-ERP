<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ContentEntry;
use App\Models\ContentField;
use App\Models\ContentSection;
use App\Models\ContentValue;
use App\Support\Content;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ContentController extends Controller
{
    public const PAGES = [
        'site' => 'Global branding, logo & footer',
        'home' => 'Home page',
        'about' => 'About page',
        'what-we-do' => 'What We Do page',
        'faq' => 'FAQ page',
        'donate' => 'Donate page',
        'partners' => 'Partners page',
        'contact' => 'Contact page',
        'membership' => 'Membership (fees, tracks, tiers)',
        'training' => 'Training page',
        'marketplace' => 'Marketplace page',
        'e-waste' => 'E-Waste page',
        'entrepreneurship' => 'Entrepreneurship page',
        'green-economy' => 'Green Economy page',
    ];

    public function index()
    {
        $counts = ContentSection::selectRaw('page, count(*) c')->groupBy('page')->pluck('c', 'page');

        return view('admin.content.index', [
            'pages' => self::PAGES,
            'counts' => $counts,
        ]);
    }

    public function edit(string $page)
    {
        abort_unless(isset(self::PAGES[$page]), 404);

        $sections = ContentSection::with(['fields', 'entries.values'])
            ->where('page', $page)->orderBy('sort_order')->get();

        return view('admin.content.edit', compact('page', 'sections'));
    }

    /** Bulk-save field values (text + image uploads) and sort orders. */
    public function update(Request $request, string $page)
    {
        abort_unless(isset(self::PAGES[$page]), 404);

        foreach ($request->input('values', []) as $entryId => $fields) {
            $entry = ContentEntry::with('section')->find($entryId);
            if (! $entry || $entry->section->page !== $page) continue;
            foreach ($fields as $fieldId => $text) {
                $field = ContentField::find($fieldId);
                if (! $field || $field->section_id !== $entry->section_id) continue;
                if (in_array($field->type, ['image', 'video'], true)) continue; // handled below
                ContentValue::updateOrCreate(
                    ['entry_id' => $entry->id, 'field_id' => $field->id],
                    ['text_value' => is_string($text) ? trim($text) : $text]
                );
            }
        }

        foreach ($request->file('images', []) as $entryId => $fields) {
            $entry = ContentEntry::find($entryId);
            if (! $entry) continue;
            foreach ($fields as $fieldId => $file) {
                $field = ContentField::find($fieldId);
                if (! $field || $field->type !== 'image' || ! $file) continue;
                $request->validate(["images.$entryId.$fieldId" => 'image|mimes:'.\App\Support\Uploads::IMAGE_MIMES.'|max:4096']);
                $name = ContentField::slugName($field, $file);
                $file->move(public_path('uploads/content'), $name);
                ContentValue::updateOrCreate(
                    ['entry_id' => $entry->id, 'field_id' => $field->id],
                    ['image_path' => $name]
                );
            }
        }

        // A short clip is stored the same way as a photo: renamed from its bytes, into the same public folder.
        foreach ($request->file('videos', []) as $entryId => $fields) {
            $entry = ContentEntry::find($entryId);
            if (! $entry) continue;
            foreach ($fields as $fieldId => $file) {
                $field = ContentField::find($fieldId);
                if (! $field || $field->type !== 'video' || ! $file) continue;
                $request->validate(["videos.$entryId.$fieldId" => 'file|mimes:'.\App\Support\Uploads::VIDEO_MIMES.'|max:20480']);
                $name = ContentField::slugName($field, $file);
                $file->move(public_path('uploads/content'), $name);
                ContentValue::updateOrCreate(
                    ['entry_id' => $entry->id, 'field_id' => $field->id],
                    ['image_path' => $name]
                );
            }
        }

        foreach ($request->input('remove_image', []) as $entryId => $fields) {
            $entry = ContentEntry::find($entryId);
            if (! $entry) continue;
            foreach (array_keys($fields) as $fieldId) {
                ContentValue::where('entry_id', $entry->id)->where('field_id', $fieldId)->delete();
            }
        }

        foreach ($request->input('section_order', []) as $id => $order) {
            ContentSection::where('page', $page)->where('id', $id)->update(['sort_order' => (int) $order]);
        }
        foreach ($request->input('entry_order', []) as $id => $order) {
            ContentEntry::where('id', $id)->update(['sort_order' => (int) $order]);
        }

        foreach ($request->input('visible', []) as $id => $_) {
            ContentSection::where('page', $page)->where('id', $id)->update(['is_visible' => true]);
        }
        foreach (ContentSection::where('page', $page)->pluck('id') as $id) {
            if (! $request->has("visible.$id")) {
                ContentSection::where('id', $id)->update(['is_visible' => false]);
            }
        }

        Content::forget($page);

        return back()->with('status', 'Content saved. The public site is updated.');
    }

    // ---- structure: sections ----

    public function addSection(Request $request, string $page)
    {
        abort_unless(isset(self::PAGES[$page]), 404);
        $data = $request->validate([
            'name' => 'required|string|max:120',
            'kind' => 'required|in:single,repeater',
        ]);
        $key = Str::slug($data['name']);
        $base = $key; $i = 1;
        while (ContentSection::where(['page' => $page, 'key' => $key])->exists()) { $key = $base.'-'.(++$i); }

        $section = ContentSection::create([
            'page' => $page, 'key' => $key, 'name' => $data['name'], 'kind' => $data['kind'],
            'sort_order' => (int) ContentSection::where('page', $page)->max('sort_order') + 1,
        ]);
        // one starter field so the section is immediately usable
        ContentField::create(['section_id' => $section->id, 'key' => 'title', 'label' => 'Title', 'type' => 'text', 'sort_order' => 0]);
        ContentEntry::create(['section_id' => $section->id, 'sort_order' => 0]);

        Content::forget($page);

        return back()->with('status', 'Section “'.$data['name'].'” added.');
    }

    public function deleteSection(string $page, ContentSection $section)
    {
        abort_unless($section->page === $page, 404);
        $section->delete();
        Content::forget($page);

        return back()->with('status', 'Section deleted.');
    }

    // ---- structure: fields ----

    public function addField(Request $request, ContentSection $section)
    {
        $data = $request->validate([
            'label' => 'required|string|max:120',
            'type' => 'required|in:text,textarea,image,video,url,price',
        ]);
        $key = Str::slug($data['label'], '_');
        $base = $key; $i = 1;
        while (ContentField::where(['section_id' => $section->id, 'key' => $key])->exists()) { $key = $base.'_'.(++$i); }

        ContentField::create([
            'section_id' => $section->id, 'key' => $key, 'label' => $data['label'], 'type' => $data['type'],
            'sort_order' => (int) ContentField::where('section_id', $section->id)->max('sort_order') + 1,
        ]);
        Content::forget($section->page);

        return back()->with('status', 'Field “'.$data['label'].'” added.');
    }

    public function deleteField(ContentField $field)
    {
        $page = $field->section->page;
        $field->delete();
        Content::forget($page);

        return back()->with('status', 'Field deleted.');
    }

    // ---- structure: entries (repeater rows) ----

    public function addEntry(ContentSection $section)
    {
        ContentEntry::create(['section_id' => $section->id, 'sort_order' => (int) ContentEntry::where('section_id', $section->id)->max('sort_order') + 1]);
        Content::forget($section->page);

        return back()->with('status', 'Item added — fill it in and Save.');
    }

    public function deleteEntry(ContentEntry $entry)
    {
        $page = $entry->section->page;
        $entry->delete();
        Content::forget($page);

        return back()->with('status', 'Item deleted.');
    }
}
