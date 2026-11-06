<?php

namespace App\Support;

/**
 * A4 paper written straight onto the response — no library, no shell, nothing for the host to install.
 * The programme's own documents (an invoice today) are laid out here so a member who taps Download receives the
 * same branded paper the office prints, in a format every phone opens.
 *
 * Every distance is measured from the TOP of the sheet, in points, and the cursor runs down the page. A block that
 * outgrows the paper starts another page, so nothing can fall off the bottom.
 */
class Pdf
{
    public const W = 595.28;
    public const H = 841.89;

    public const INK = '#1d2b23';
    public const MUTED = '#5b6b5f';
    public const GREEN = '#066722';
    public const PALE = '#d8f3c8';
    public const LINE = '#dce6dc';
    public const MIST = '#eef8ec';
    public const WHITE = '#ffffff';

    /** @var string[] finished page streams */
    private array $pages = [];

    /** @var array<int, array{bytes: string, columns: int, rows: int}> */
    private array $images = [];

    private string $ops = '';

    private float $top;

    private float $marginTop;

    private ?\Closure $onPage = null;

    public function __construct(float $top = 56.0)
    {
        $this->top = $top;
        $this->marginTop = $top;
    }

    public function cursor(): float
    {
        return $this->top;
    }

    /** Free paper left below the cursor, so a block can decide to start the next page instead of splitting. */
    public function room(): float
    {
        return self::H - 56.0 - $this->top;
    }

    public function moveDown(float $points): void
    {
        $this->top += $points;
    }

    /** Place the cursor at a distance from the top, without changing where a fresh page starts. */
    public function place(float $fromTop): void
    {
        $this->top = $fromTop;
    }

    /** One line at the cursor, then the cursor steps below it. */
    public function text(string $s, float $x, float $size = 11, bool $bold = false, string $color = self::INK,
        float $spacing = 0.0): void
    {
        $this->at($s, $x, $this->top, $size, $bold, $color, $spacing);
        $this->top += $size * 1.42;
    }

    /** A figure pushed to the right edge — the money column of a bill. */
    public function right(string $s, float $xEnd, float $size = 11, bool $bold = false, string $color = self::INK,
        float $spacing = 0.0): void
    {
        $this->at($s, $xEnd - self::measure($s, $size, $bold, $spacing), $this->top, $size, $bold, $color, $spacing);
        $this->top += $size * 1.42;
    }

    /**
     * One line at a fixed distance from the top, whatever the cursor is doing — a second column.
     * The tracking is always written: it is text state, so a spaced label would otherwise leak into every line
     * drawn after it and the words would sit wider than the width that was measured for them.
     */
    public function at(string $s, float $x, float $fromTop, float $size = 11, bool $bold = false,
        string $color = self::INK, float $spacing = 0.0): void
    {
        $this->ops .= sprintf("BT %s %s %.2F Tf %.2F Tc %.2F %.2F Td (%s) Tj ET\n",
            self::fill($color), '/F'.($bold ? 2 : 1), $size, $spacing,
            $x, self::baseline($fromTop, $size), self::escape($s));
    }

    /** Words folded to a width, drawn from a fixed distance from the top. Returns the height taken. */
    public function wrap(string $s, float $x, float $fromTop, float $width, float $size = 11, bool $bold = false,
        string $color = self::INK, float $leading = 1.42): float
    {
        $lines = self::chunk($s, $width, $size, $bold);
        foreach ($lines as $i => $frag) {
            $this->at($frag, $x, $fromTop + ($i * $size * $leading), $size, $bold, $color);
        }

        return count($lines) * $size * $leading;
    }

    /**
     * A paragraph that flows down the page and carries on to the next sheet when the paper runs out — the cursor
     * finishes below the last line.
     */
    public function flow(string $s, float $x, float $width, float $size = 11, bool $bold = false,
        string $color = self::INK, float $leading = 1.42): void
    {
        foreach (self::chunk($s, $width, $size, $bold) as $frag) {
            $this->need($size * $leading);
            $this->text($frag, $x, $size, $bold, $color);
        }
    }

    /** A hairline across the paper at the cursor. */
    public function rule(float $x, float $width, string $color = self::LINE, float $weight = 0.8): void
    {
        $this->ruleAt($this->top, $x, $width, $color, $weight);
    }

    public function ruleAt(float $fromTop, float $x, float $width, string $color = self::LINE, float $weight = 0.8): void
    {
        $y = self::H - $fromTop;
        $this->ops .= sprintf("%.2f %.2f m %.2f %.2f l %s %.2f w S\n",
            $x, $y, $x + $width, $y, self::stroke($color), $weight);
    }

    /** A band across the paper (the heading strip) or a boxed note, drawn from the cursor down. */
    public function box(float $x, float $width, float $height, string $fill, ?string $stroke = null, float $radius = 0.0): void
    {
        $this->boxAt($this->top, $x, $width, $height, $fill, $stroke, $radius);
        $this->top += $height;
    }

    public function boxAt(float $fromTop, float $x, float $width, float $height, string $fill,
        ?string $stroke = null, float $radius = 0.0): void
    {
        $y = self::H - ($fromTop + $height);
        $r = min($radius, $width / 2, $height / 2);
        $path = $r > 0
            ? sprintf("%.2F %.2F m", $x + $r, $y)
               .sprintf(" %.2F %.2F %.2F %.2F %.2F %.2F c", $x + $width - $r, $y, $x + $width, $y, $x + $width, $y + $r)
               .sprintf(" %.2F %.2F l", $x + $width, $y + $height - $r)
               .sprintf(" %.2F %.2F %.2F %.2F %.2F %.2F c", $x + $width, $y + $height, $x + $width, $y + $height, $x + $width - $r, $y + $height)
               .sprintf(" %.2F %.2F l", $x + $r, $y + $height)
               .sprintf(" %.2F %.2F %.2F %.2F %.2F %.2F c", $x, $y + $height, $x, $y + $height, $x, $y + $height - $r)
               .sprintf(" %.2F %.2F l", $x, $y + $r)
               .sprintf(" %.2F %.2F %.2F %.2F %.2F %.2F c", $x, $y, $x, $y, $x + $r, $y)
            : sprintf("%.2F %.2F m %.2F %.2F l %.2F %.2F l %.2F %.2F l h",
                $x, $y, $x + $width, $y, $x + $width, $y + $height, $x, $y + $height);

        $this->ops .= sprintf('q %s %s f', self::fill($fill), $path);
        if ($stroke !== null) {
            $this->ops .= sprintf(' %s 0.8 w S', self::stroke($stroke));
        }
        $this->ops .= " Q\n";
    }

    /**
     * A picture — the bytes must already be a JPEG, which is what a phone camera and the CMS both give. It is placed
     * at the given width and keeps its own shape.
     */
    public function image(string $jpeg, float $x, float $fromTop, float $width): void
    {
        [$columns, $rows] = array_values(getimagesizefromstring($jpeg));
        $this->images[] = ['bytes' => $jpeg, 'columns' => $columns, 'rows' => $rows];
        $height = $width * $rows / max($columns, 1);
        $name = 'Im'.count($this->images);
        $this->ops .= sprintf("q %.2F 0 0 %.2F %.2F %.2F cm /%s Do Q\n",
            $width, $height, $x, self::H - $fromTop - $height, $name);
    }

    /** Start the next page now; anything drawn after this sits on it. */
    public function next(): void
    {
        $this->pages[] = $this->ops;
        $this->ops = '';
        $this->top = $this->marginTop;

        if ($this->onPage) {
            ($this->onPage)($this);
        }
    }

    /** A word to mark every sheet after the first, such as the reference of a bill that runs on. */
    public function onPage(?\Closure $mark): void
    {
        $this->onPage = $mark;
    }

    /** Make sure this much paper is still free before the next block, starting a fresh page if it is not. */
    public function need(float $points): void
    {
        if ($this->room() < $points) {
            $this->next();
        }
    }

    public function output(): string
    {
        if ($this->ops !== '') {
            $this->pages[] = $this->ops;
        }
        if (! $this->pages) {
            $this->pages[] = '';
        }

        $objects = [];
        $streams = [];
        $imageAt = 5;
        $firstPage = $imageAt + count($this->images);
        $kids = [];
        for ($i = 0; $i < count($this->pages); $i++) {
            $kids[] = ($firstPage + ($i * 2)).' 0 R';
        }

        $objects[1] = '<< /Type /Catalog /Pages 2 0 R >>';
        $objects[2] = '<< /Type /Pages /Kids ['.implode(' ', $kids).'] /Count '.count($kids).' >>';
        $objects[3] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>';
        $objects[4] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold /Encoding /WinAnsiEncoding >>';

        $xobjects = '';
        foreach ($this->images as $n => $image) {
            $id = $imageAt + $n;
            $streams[$id] = $image['bytes'];
            $objects[$id] = sprintf('<< /Type /XObject /Subtype /Image /Width %d /Height %d /ColorSpace /DeviceRGB '
                .'/BitsPerComponent 8 /Filter /DCTDecode /Length %d >>',
                    $image['columns'], $image['rows'], strlen($image['bytes']));
            $xobjects .= ' /Im'.($n + 1).' '.$id.' 0 R';
        }

        $resources = '<< /Font << /F1 3 0 R /F2 4 0 R >>'.($xobjects ? ' /XObject <<'.$xobjects.' >>' : '').' >>';
        foreach ($this->pages as $i => $ops) {
            $pageId = $firstPage + ($i * 2);
            $contentId = $pageId + 1;
            $objects[$pageId] = sprintf('<< /Type /Page /Parent 2 0 R /MediaBox [0 0 %.2F %.2F] /Resources %s /Contents %d 0 R >>',
                self::W, self::H, $resources, $contentId);
            $streams[$contentId] = $ops;
            $objects[$contentId] = '<< /Length '.strlen($ops).' >>';
        }

        $out = "%PDF-1.4\n";
        $offsets = [];
        ksort($objects);
        foreach ($objects as $id => $dict) {
            $offsets[$id] = strlen($out);
            $out .= isset($streams[$id])
                ? $id." 0 obj\n".$dict."\nstream\n".$streams[$id]."endstream\nendobj\n"
                : $id." 0 obj\n".$dict."\nendobj\n";
        }

        $xref = strlen($out);
        $count = max(array_keys($offsets)) + 1;
        $out .= "xref\n0 ".$count."\n0000000000 65535 f \n";
        for ($id = 1; $id < $count; $id++) {
            $out .= sprintf("%010d 00000 n \n", $offsets[$id] ?? 0);
        }
        $out .= "trailer\n<< /Size ".$count." /Root 1 0 R >>\nstartxref\n".$xref."\n%%EOF\n";

        return $out;
    }

    /** @return string[] whole lines, still in the text's own encoding — self::escape() folds them when drawn */
    public static function chunk(string $s, float $width, float $size, bool $bold): array
    {
        $words = preg_split('/\s+/', trim($s)) ?: [];
        $lines = [];
        $line = '';
        foreach ($words as $w) {
            $try = $line === '' ? $w : $line.' '.$w;
            if ($line !== '' && self::measure($try, $size, $bold) > $width) {
                $lines[] = $line;
                $line = $w;
            } else {
                $line = $try;
            }
        }
        if ($line !== '') {
            $lines[] = $line;
        }

        return $lines ?: [''];
    }

    public static function measure(string $s, float $size, bool $bold = false, float $spacing = 0.0): float
    {
        $bytes = self::ascii($s);
        // Every glyph carries the tracking, the last one included: a line built from runs must hand the next run
        // the full room the previous one actually took on the paper.
        return self::width($bytes, $size, $bold) + $spacing * strlen($bytes);
    }

    /** Width of bytes already folded to WinAnsi — see self::ascii(). */
    public static function width(string $bytes, float $size, bool $bold): float
    {
        $total = 0.0;
        foreach (unpack('C*', $bytes) ?: [] as $c) {
            $total += self::GLYPHS[$c] ?? 556;
        }

        return $total * $size / 1000 * ($bold ? 1.03 : 1.0);
    }

    /** The text sits this far below the top line of its block. */
    private static function baseline(float $fromTop, float $size): float
    {
        return self::H - $fromTop - $size * 0.82;
    }

    private static function escape(string $s): string
    {
        return str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], self::ascii($s));
    }

    /** The paper is written in WinAnsi: the dashes and dots our own text uses are folded to what it holds. */
    private static function ascii(string $s): string
    {
        $s = strtr($s, [
            '—' => '-', '–' => '-', '·' => '-', '•' => '-', '‘' => "'", '’' => "'",
            '“' => '"', '”' => '"', '…' => '...', ' ' => ' ', '​' => '',
        ]);

        return iconv('UTF-8', 'CP1252//TRANSLIT//IGNORE', $s) ?: preg_replace('/[^\x20-\x7E]/', '', $s);
    }

    private static function fill(string $hex): string
    {
        return self::rgb($hex).' rg';
    }

    private static function stroke(string $hex): string
    {
        return self::rgb($hex).' RG';
    }

    private static function rgb(string $hex): string
    {
        return sprintf('%.3F %.3F %.3F',
            hexdec(substr($hex, 1, 2)) / 255, hexdec(substr($hex, 3, 2)) / 255, hexdec(substr($hex, 5, 2)) / 255);
    }

    /** Helvetica widths per Adobe's core font metrics, in thousandths of the size. */
    private const GLYPHS = [
        32 => 278, 33 => 278, 34 => 355, 35 => 556, 36 => 556, 37 => 889, 38 => 667, 39 => 191,
        40 => 333, 41 => 333, 42 => 389, 43 => 584, 44 => 278, 45 => 333, 46 => 278, 47 => 278,
        48 => 556, 49 => 556, 50 => 556, 51 => 556, 52 => 556, 53 => 556, 54 => 556, 55 => 556,
        56 => 556, 57 => 556, 58 => 278, 59 => 278, 60 => 584, 61 => 584, 62 => 584, 63 => 556,
        64 => 737, 65 => 667, 66 => 667, 67 => 722, 68 => 722, 69 => 667, 70 => 611, 71 => 778,
        72 => 722, 73 => 278, 74 => 500, 75 => 667, 76 => 556, 77 => 833, 78 => 722, 79 => 778,
        80 => 667, 81 => 778, 82 => 722, 83 => 667, 84 => 611, 85 => 722, 86 => 667, 87 => 944,
        88 => 667, 89 => 667, 90 => 611, 91 => 278, 92 => 278, 93 => 278, 94 => 469, 95 => 556,
        96 => 333, 97 => 556, 98 => 556, 99 => 500, 100 => 556, 101 => 556, 102 => 278, 103 => 556,
        104 => 556, 105 => 222, 106 => 222, 107 => 500, 108 => 222, 109 => 833, 110 => 556, 111 => 556,
        112 => 556, 113 => 556, 114 => 333, 115 => 500, 116 => 278, 117 => 556, 118 => 500, 119 => 722,
        120 => 500, 121 => 500, 122 => 500, 123 => 334, 124 => 260, 125 => 334, 126 => 584,
        160 => 278, 183 => 333,
    ];
}
