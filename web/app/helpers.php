<?php

use App\Support\Content;

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
