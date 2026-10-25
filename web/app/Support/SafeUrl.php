<?php

namespace App\Support;

/**
 * A link a person types is only ever a web link. Anything else — javascript:, data:, file: — is refused on the way
 * in and dropped on the way out, so a stored value can never turn into a script inside someone else's href.
 */
class SafeUrl
{
    public static function href(mixed $value, string $fallback = '#'): string
    {
        $value = trim((string) $value);

        if ($value === '') {
            return $fallback;
        }

        // A root-relative path is the programme's own page; anything else has to name a web scheme.
        if (str_starts_with($value, '/') && ! str_starts_with($value, '//')) {
            return $value;
        }

        return preg_match('#^(?:https?:)?//#i', $value) ? $value : $fallback;
    }

    /** The validation rule for a link field. Pass false when the field may be left empty. */
    public static function rule(bool $required = false): array
    {
        $rules = [$required ? 'required' : 'nullable', 'max:200'];

        return array_merge($rules, [function (string $attribute, $value, \Closure $fail) {
            if (filled($value) && static::href($value, '') === '') {
                $fail('The '.str_replace('_', ' ', $attribute).' must be a normal web address that starts with https://.');
            }
        }]);
    }
}
