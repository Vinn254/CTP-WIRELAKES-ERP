<?php

namespace App\Support;

/**
 * §15.4 — the Kenya-wide view. Records carry a county name, not a map coordinate, so the country is drawn as one
 * square per county placed roughly where it sits (north at the top, the coast on the right). A square is shaded by
 * how many records the chosen layer holds there, and opening it lists those records. No county is invented and no
 * square is drawn for a county the register does not know.
 */
class KenyaMap
{
    public const COLS = 10;
    public const ROWS = 8;

    /** county name => [column, row] on a 10 x 8 board. */
    public const TILES = [
        'Mandera' => [10, 1], 'Turkana' => [4, 1], 'Marsabit' => [6, 1],
        'Wajir' => [9, 2], 'Isiolo' => [6, 2], 'Samburu' => [5, 2], 'West Pokot' => [4, 2],
        'Lamu' => [10, 3], 'Garissa' => [8, 3], 'Meru' => [7, 3], 'Laikipia' => [6, 3],
        'Elgeyo-Marakwet' => [5, 3], 'Uasin Gishu' => [4, 3], 'Trans Nzoia' => [3, 3],
        'Bungoma' => [1, 3], 'Busia' => [2, 3],
        'Tana River' => [10, 4], 'Tharaka-Nithi' => [8, 4], 'Nyeri' => [6, 4], 'Kirinyaga' => [7, 4],
        'Baringo' => [5, 4], 'Nakuru' => [4, 4], 'Nandi' => [3, 4], 'Kakamega' => [1, 4], 'Vihiga' => [2, 4],
        'Kilifi' => [10, 5], 'Embu' => [8, 5], 'Kitui' => [9, 5], 'Kiambu' => [7, 5], "Murang'a" => [6, 5],
        'Nyandarua' => [5, 5], 'Bomet' => [4, 5], 'Nyamira' => [3, 5], 'Kisumu' => [2, 5], 'Siaya' => [1, 5],
        'Mombasa' => [10, 6], 'Machakos' => [8, 6], 'Makueni' => [9, 6], 'Nairobi' => [7, 6],
        'Kericho' => [4, 6], 'Kisii' => [3, 6], 'Migori' => [2, 6], 'Homa Bay' => [1, 6],
        'Taita Taveta' => [9, 7], 'Kajiado' => [6, 7], 'Narok' => [5, 7],
        'Kwale' => [9, 8],
    ];

    /** Every county in the register, in board order, with its count on the chosen layer. */
    public static function tiles(array $counts): array
    {
        $known = collect(self::TILES)->map(fn ($pos, $name) => [
            'county' => $name,
            'col' => $pos[0],
            'row' => $pos[1],
            'count' => (int) ($counts[$name] ?? 0),
        ])->values();

        // A record whose county is spelled some other way still has to be seen, not silently dropped off the map.
        $offBoard = collect($counts)->filter(fn ($n, $name) => ! isset(self::TILES[$name]) && $n > 0)
            ->map(fn ($n, $name) => ['county' => $name, 'col' => null, 'row' => null, 'count' => (int) $n])
            ->sortByDesc('count')->values();

        return ['board' => $known, 'unmatched' => $offBoard];
    }

    /** 0 to 4 — how dark a square is drawn for its share of the busiest county on the layer. */
    public static function band(int $count, int $peak): int
    {
        if ($count <= 0) {
            return 0;
        }

        $share = $count / max(1, $peak);

        return match (true) {
            $share > 0.66 => 4,
            $share > 0.33 => 3,
            $share > 0.1 => 2,
            default => 1,
        };
    }

    /**
     * The counties sitting beside one on the board — §9.6 widening a search to neighbouring counties. The geography
     * is read off the same tile positions the map draws, so the two can never disagree about what borders what.
     *
     * @return list<string>
     */
    public static function neighbours(?string $county, int $ring = 1): array
    {
        $here = static::tileFor($county);

        if (! $here) {
            return [];
        }

        return collect(self::TILES)
            ->filter(fn (array $pos, string $name) => $name !== $here['name']
                && max(abs($pos[0] - $here['col']), abs($pos[1] - $here['row'])) <= $ring)
            ->keys()->sort()->values()->all();
    }

    /** The square a county name points at, however it was spelled on the record. */
    public static function tileFor(?string $county): ?array
    {
        $word = trim((string) $county);

        if ($word === '') {
            return null;
        }

        $name = collect(self::TILES)->keys()->first(fn (string $k) => strcasecmp($k, $word) === 0);

        return $name ? ['name' => $name, 'col' => self::TILES[$name][0], 'row' => self::TILES[$name][1]] : null;
    }
}
