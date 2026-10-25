<?php

namespace App\Support;

/**
 * One answer for every export the programme writes: a comma, a quote or a line break inside a field would otherwise
 * split a row in two, and a value that starts with =, +, - or @ is run as a formula the moment Excel opens it.
 */
class Csv
{
    /** @param array<int, string> $row */
    public static function row(array $row): array
    {
        return array_map(static fn ($value) => static::cell((string) $value), $row);
    }

    public static function cell(string $value): string
    {
        // A cell that opens like a formula is not one here: it is a name, a note or a reference the office typed.
        return preg_match('/^[=+\-@\t\r]/', $value) ? "'".$value : $value;
    }
}
