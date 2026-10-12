<?php

namespace App\Support;

use App\Models\ContentSection;
use Illuminate\Support\Facades\File;

class Content
{
    /** @var array<string, array> page => hydrated tree */
    protected static array $pages = [];

    public static function forget(?string $page = null): void
    {
        if ($page === null) {
            self::$pages = [];
        } else {
            unset(self::$pages[$page]);
        }
    }

    protected static function load(string $page): array
    {
        if (isset(self::$pages[$page])) {
            return self::$pages[$page];
        }

        $sections = ContentSection::with(['fields', 'entries.values'])
            ->where('page', $page)
            ->where('is_visible', true)
            ->orderBy('sort_order')
            ->get();

        $tree = [];
        foreach ($sections as $section) {
            $fieldDefs = $section->fields->keyBy('id');
            $entries = [];
            foreach ($section->entries as $entry) {
                $row = [];
                foreach ($entry->values as $value) {
                    $def = $fieldDefs->get($value->field_id);
                    if (! $def) continue;
                    $row[$def->key] = $def->type === 'image'
                        ? ($value->image_path ? asset('uploads/content/'.$value->image_path) : null)
                        : $value->text_value;
                }
                $entries[] = $row;
            }
            $tree[$section->key] = [
                'kind' => $section->kind,
                'name' => $section->name,
                'rows' => $entries,
                'single' => $entries[0] ?? [],
            ];
        }

        return self::$pages[$page] = $tree;
    }

    /** Value of one field in a single section, or $default when empty/missing. */
    public static function get(string $page, string $section, string $field, ?string $default = null): ?string
    {
        $v = self::load($page)[$section]['single'][$field] ?? null;
        return ($v === null || $v === '') ? $default : $v;
    }

    /** Rows of a repeater section (each an array field => value). */
    public static function rows(string $page, string $section): array
    {
        return self::load($page)[$section]['rows'] ?? [];
    }

    /** First image URL of a repeater row set (hero slides etc). */
    public static function images(string $page, string $section, string $field = 'image'): array
    {
        return array_values(array_filter(array_map(
            fn ($r) => $r[$field] ?? null,
            self::rows($page, $section)
        )));
    }

    public static function price(string $page, string $section, string $field, ?string $default = null): ?string
    {
        return self::get($page, $section, $field, $default);
    }

    /**
     * Single-section field values the template does not render itself
     * (fields an admin added later), so they still appear on the page.
     *
     * @param string[] $used field keys already rendered by the template
     */
    public static function extraFields(string $page, string $section, array $used): array
    {
        $single = self::load($page)[$section]['single'] ?? [];
        return array_diff_key($single, array_flip($used));
    }

    /**
     * Whole sections on a page that no template block claims (admin-created ones),
     * ready to render generically.
     *
     * @param string[] $used section keys already rendered by the page
     */
    public static function extraSections(string $page, array $used): array
    {
        $tree = self::load($page);
        $out = [];
        foreach ($tree as $key => $section) {
            if (in_array($key, $used, true)) continue;
            $rows = array_values(array_filter($section['rows'], fn ($r) => array_filter($r ?? []) !== []));
            if ($rows) {
                $out[] = ['key' => $key, 'name' => $section['name'], 'kind' => 'repeater', 'rows' => $rows];
            } elseif (! empty($section['single'])) {
                $out[] = ['key' => $key, 'name' => $section['name'], 'kind' => 'single', 'single' => $section['single']];
            }
        }
        return $out;
    }
}
