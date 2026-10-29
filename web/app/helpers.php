<?php

use App\Support\Content;
use App\Support\Departments;

if (! function_exists('dept_link')) {
    /** Link to a record kept by another module, opened inside the department you are standing in. */
    function dept_link(string $routeName, array $params = []): string
    {
        return Departments::inside($routeName, $params);
    }
}

if (! function_exists('dept_ref')) {
    /**
     * A named mention of another department's screen inside a page: linked when this staff member can open it (their
     * own copy included), plain words when they cannot. Prose that points at a module the reader has no access to
     * must not offer a click that lands on "outside your department access".
     */
    function dept_ref(string $routeName, string $label, array $params = []): string
    {
        return Departments::reachable(request()->user(), $routeName)
            ? '<a href="'.Departments::inside($routeName, $params).'">'.e($label).'</a>'
            : e($label);
    }
}

if (! function_exists('content')) {
    /** Field value from a single section, or $default when missing/empty. */
    function content(string $page, string $section, string $field, ?string $default = null): ?string
    {
        try {
            return Content::get($page, $section, $field, $default);
        } catch (\Throwable) {
            return $default;
        }
    }
}

if (! function_exists('content_rows')) {
    /** Rows of a repeater section; empty array when the section is missing. */
    function content_rows(string $page, string $section): array
    {
        try {
            return Content::rows($page, $section);
        } catch (\Throwable) {
            return [];
        }
    }
}

if (! function_exists('content_extra_fields')) {
    function content_extra_fields(string $page, string $section, array $used): array
    {
        try {
            return Content::extraFields($page, $section, $used);
        } catch (\Throwable) {
            return [];
        }
    }
}

if (! function_exists('content_extra_sections')) {
    function content_extra_sections(string $page, array $used): array
    {
        try {
            return Content::extraSections($page, $used);
        } catch (\Throwable) {
            return [];
        }
    }
}

if (! function_exists('safe_href')) {
    /** A stored link going into an href: a real web address or this programme's own page, never a script. */
    function safe_href(mixed $value, string $fallback = '#'): string
    {
        return \App\Support\SafeUrl::href($value, $fallback);
    }
}

if (! function_exists('download_href')) {
    /** The same document url that opens on screen, written so the browser saves the file instead. */
    function download_href(string $url): string
    {
        return \App\Support\Documents::downloadUrl($url);
    }
}

if (! function_exists('css_href')) {
    /**
     * The stylesheet with its own timestamp glued on. A browser that cached the previous copy of app.css
     * otherwise keeps styling an office screen from before the change was uploaded.
     */
    function css_href(string $file = 'css/app.css'): string
    {
        $path = public_path($file);

        return asset($file).'?v='.((is_file($path) ? filemtime($path) : null) ?: config('app.build', 1));
    }
}
