<?php

namespace App\Support;

use App\Models\Invoice;
use App\Models\Setting;

/**
 * The invoice on paper: the branded sheet the office reads on screen, laid out again for a file a member can keep.
 * One place builds it, so the download from the Finance desk, the one from a member's own page and the copy mailed
 * with the invoice are the same document byte for byte.
 */
class InvoicePdf
{
    private const MARGIN = 56.0;
    private const RIGHT = Pdf::W - self::MARGIN;
    private const WIDTH = Pdf::W - (self::MARGIN * 2);

    /** The money column: the description folds short of it, the figures hang off the right edge. */
    private const MONEY = 372.0;

    private const COLUMN = 320.0;

    public static function render(Invoice $invoice): string
    {
        $invoice->loadMissing(['ctp:id,full_name,ctp_code', 'fundi:id,full_name,ef_code', 'project:id,title',
            'payments', 'raiser:id,name', 'canceller:id,name']);

        $pdf = new Pdf(self::MARGIN);
        $pdf->onPage(function (Pdf $sheet) use ($invoice): void {
            $sheet->at($invoice->reference.' - continued', self::MARGIN, $sheet->cursor(), 8.5, true, Pdf::MUTED, 1.2);
            $sheet->moveDown(26);
        });

        self::heading($pdf, $invoice);
        self::parties($pdf, $invoice);
        self::charges($pdf, $invoice);
        self::paying($pdf, $invoice);
        self::closing($pdf, $invoice);
        self::foot($pdf, $invoice);

        return $pdf->output();
    }

    private static function heading(Pdf $pdf, Invoice $invoice): void
    {
        $band = 108.0;
        $pdf->boxAt(0, 0, Pdf::W, $band, Pdf::GREEN);

        $x = self::MARGIN;
        if ($logo = self::logo()) {
            $pdf->boxAt(28, $x, 54, 54, Pdf::WHITE, null, 9);
            $pdf->image($logo, $x + 8, 36, 38);
            $x += 70;
        }

        $brand = content('site', 'branding', 'brand_name', 'WireLakes Technologies');
        $pdf->at($brand, $x, 34, 17, true, Pdf::WHITE);
        if ($contact = self::contactLine()) {
            $pdf->at($contact, $x, 60, 9.5, false, Pdf::PALE);
        }

        $pdf->at('INVOICE', self::RIGHT - Pdf::measure('INVOICE', 8.5, true, 1.8), 38, 8.5, true, Pdf::PALE, 1.8);
        $pdf->at($invoice->reference, self::RIGHT - Pdf::measure($invoice->reference, 20, true), 56, 20, true, Pdf::WHITE);

        $pdf->place($band + 26);
    }

    /** Who owes it on the left; when it was raised and where it stands on the right. */
    private static function parties(Pdf $pdf, Invoice $invoice): void
    {
        $top = $pdf->cursor();

        $pdf->at('BILLED TO', self::MARGIN, $top, 8, true, Pdf::MUTED, 1.2);
        $left = $top + 20;
        $left += $pdf->wrap($invoice->memberName(), self::MARGIN, $left, 226, 14, true) + 5;
        foreach (self::billTo($invoice) as $detail) {
            $left += $pdf->wrap($detail, self::MARGIN, $left, 226, 10, false, Pdf::MUTED) + 1;
        }

        $right = self::COLUMN;
        $bottom = $top + 2;
        self::pair($pdf, $right, $bottom, 'Issued', $invoice->issued_on->format('d M Y'));
        $bottom += 18;
        self::pair($pdf, $right, $bottom, 'Due by', $invoice->due_on?->format('d M Y') ?: 'on receipt');
        $bottom += 18;
        if ($invoice->project) {
            $bottom += $pdf->wrap($invoice->project->title, $right, $bottom, self::RIGHT - $right, 10, false, Pdf::MUTED) + 8;
        } else {
            $bottom += 4;
        }

        $label = $invoice->statusLabel().($invoice->status === 'open' && $invoice->isOverdue() ? ' - overdue' : '');
        [$fill, $ink] = self::chip($invoice);
        $chip = Pdf::measure($label, 10, true) + 24;
        $pdf->boxAt($bottom, $right, $chip, 22, $fill, null, 11);
        $pdf->at($label, $right + 12, $bottom + 6, 10, true, $ink);
        $bottom += 22;

        $pdf->place(max($left, $bottom) + 18);
        $pdf->rule(self::MARGIN, self::WIDTH);
        $pdf->moveDown(20);
    }

    /** "Issued 27 Oct 2026" — the word in the muted voice, the figure in its own. */
    private static function pair(Pdf $pdf, float $x, float $fromTop, string $label, string $value): void
    {
        self::runs($pdf, [[$label.' ', 10, false, Pdf::MUTED], [$value, 10, true, Pdf::INK]], $x, $fromTop);
    }

    /** The charge, then every payment the office has recorded against it, then the balance. */
    private static function charges(Pdf $pdf, Invoice $invoice): void
    {
        $pdf->at('WHAT IS BEING INVOICED', self::MARGIN, $pdf->cursor(), 8, true, Pdf::MUTED, 1.2);
        $pdf->at('AMOUNT', self::RIGHT - Pdf::measure('AMOUNT', 8, true, 1.2), $pdf->cursor(), 8, true, Pdf::MUTED, 1.2);
        $pdf->moveDown(14);
        $pdf->rule(self::MARGIN, self::WIDTH);
        $pdf->moveDown(16);

        $pdf->need(48);
        $line = $pdf->cursor();
        $rows = $pdf->wrap($invoice->title, self::MARGIN, $line, self::MONEY - self::MARGIN - 12, 12.5, true) + 6;
        $pdf->at($invoice->amountLabel(), self::RIGHT - Pdf::measure($invoice->amountLabel(), 12.5, true), $line, 12.5, true);
        if ($invoice->details) {
            $rows += $pdf->wrap($invoice->details, self::MARGIN, $line + $rows, self::WIDTH, 10, false, Pdf::MUTED) + 4;
        }
        $pdf->moveDown($rows + 4);

        foreach ($invoice->payments->whereNull('voided_at') as $p) {
            $pdf->need(20);
            $line = $pdf->cursor();
            $pdf->at('Paid '.$p->happened_on->format('d M Y').' - '.$p->methodLabel()
                .($p->reference ? ' - '.$p->reference : ''), self::MARGIN, $line, 10, false, Pdf::MUTED);
            $pdf->at('- '.$p->amountLabel(), self::RIGHT - Pdf::measure('- '.$p->amountLabel(), 10), $line, 10, false, Pdf::MUTED);
            $pdf->moveDown(16);
        }

        $pdf->need(70);
        $line = $pdf->cursor();
        $pdf->at('Billed', self::MARGIN, $line, 10, false, Pdf::MUTED);
        $pdf->at($invoice->amountLabel(), self::RIGHT - Pdf::measure($invoice->amountLabel(), 10), $line, 10);
        $pdf->moveDown(18);
        $line = $pdf->cursor();
        $pdf->at('Received so far', self::MARGIN, $line, 10, false, Pdf::MUTED);
        $pdf->at($invoice->paidLabel(), self::RIGHT - Pdf::measure($invoice->paidLabel(), 10), $line, 10);
        $pdf->moveDown(20);

        $pdf->rule(self::MARGIN, self::WIDTH, Pdf::GREEN, 2.0);
        $pdf->moveDown(14);
        $line = $pdf->cursor();
        $cancelled = $invoice->status === 'cancelled';
        $pdf->at($cancelled ? 'Invoice cancelled' : 'Balance due', self::MARGIN, $line, 12.5, true, Pdf::GREEN);
        $figure = $cancelled ? '-' : $invoice->balanceLabel();
        $pdf->at($figure, self::RIGHT - Pdf::measure($figure, 16, true), $line - 3, 16, true, Pdf::GREEN);
        $pdf->moveDown(34);
    }

    private static function paying(Pdf $pdf, Invoice $invoice): void
    {
        if ($invoice->status === 'cancelled') {
            return;
        }

        $paybill = Finance::paybill();
        $pdf->need(150);
        $top = $pdf->cursor();
        $height = 86.0;
        $pdf->boxAt($top, self::MARGIN, self::WIDTH, $height, '#eef8ec', '#cfe6bd', 10);
        $pdf->at('HOW TO PAY', self::MARGIN + 18, $top + 14, 8.5, true, Pdf::GREEN, 1.3);

        $x = self::MARGIN + 18;
        $x = self::runs($pdf, [
            ['For any payment use this Paybill ', 10.5, false, Pdf::INK],
            [$paybill['number'], 11.5, true, Pdf::INK],
            [', account number ', 10.5, false, Pdf::INK],
            [$paybill['account'], 11.5, true, Pdf::INK],
            ['.', 10.5, false, Pdf::INK],
        ], $x, $top + 36);
        if ($x > self::RIGHT - 18) {
            $pdf->at('For any payment use this Paybill '.$paybill['number'].', account number '.$paybill['account'].'.',
                self::MARGIN + 18, $top + 36, 10.5);
        }

        $pdf->wrap('Put '.$invoice->reference.' as the reason so the office can match your payment to this invoice.',
            self::MARGIN + 18, $top + 58, self::WIDTH - 36, 9.5, false, Pdf::MUTED);
        $pdf->place($top + $height + 16);
    }

    /** The office's own words about the bill, then why it stopped being a bill when it did. */
    private static function closing(Pdf $pdf, Invoice $invoice): void
    {
        if ($invoice->note) {
            $pdf->need(96);
            $pdf->moveDown(6);
            $pdf->flow($invoice->note, self::MARGIN, self::WIDTH, 10, false, Pdf::MUTED);
        }
        if ($invoice->status === 'cancelled') {
            $pdf->need(96);
            $pdf->moveDown(6);
            $pdf->flow('Cancelled by '.($invoice->canceller?->name ?: 'the office')
                .($invoice->cancel_reason ? ': '.$invoice->cancel_reason : ''),
                self::MARGIN, self::WIDTH, 10, false, '#a4650a');
        }
        $pdf->moveDown(18);
    }

    private static function foot(Pdf $pdf, Invoice $invoice): void
    {
        if ($pdf->room() < 46) {
            $pdf->next();
        }

        if ($pdf->room() > 130) {
            // The foot keeps the bottom edge of the sheet, so a short bill still reads as a finished page.
            $pdf->place(Pdf::H - 96);
        } else {
            $pdf->moveDown(12);
        }

        $top = $pdf->cursor();
        $pdf->rule(self::MARGIN, self::WIDTH);
        $email = Setting::get('programme_email');
        $pdf->wrap('Raised by '.($invoice->raiser?->name ?: 'the office')
            .' - '.content('site', 'branding', 'brand_name', 'WireLakes Technologies')
            .($email ? ' - '.$email : ''),
            self::MARGIN, $top + 14, self::WIDTH, 9, false, Pdf::MUTED);
    }

    /**
     * Words that change voice mid-line — a figure set bolder than the sentence around it. Drawn left to right from
     * the given point; returns where the line ended.
     *
     * @param  array<int, array{0: string, 1: float, 2: bool, 3: string}>  $runs
     */
    private static function runs(Pdf $pdf, array $runs, float $x, float $fromTop): float
    {
        foreach ($runs as [$text, $size, $bold, $color]) {
            $pdf->at($text, $x, $fromTop, $size, $bold, $color);
            $x += Pdf::measure($text, $size, $bold);
        }

        return $x;
    }

    /** @return array{0: string, 1: string} the chip's fill and its ink */
    private static function chip(Invoice $invoice): array
    {
        return match ($invoice->status) {
            'settled' => ['#e7f4e6', Pdf::GREEN],
            'cancelled' => ['#f2f4f6', Pdf::MUTED],
            default => ['#fff3e0', '#a4650a'],
        };
    }

    /** @return string[] */
    private static function billTo(Invoice $invoice): array
    {
        $lines = [];
        if ($invoice->party_name && $invoice->party_name !== $invoice->memberName()) {
            $lines[] = $invoice->party_name;
        }
        if ($code = $invoice->ctp?->ctp_code ?: $invoice->fundi?->ef_code) {
            $lines[] = $code;
        }
        if ($email = $invoice->email()) {
            $lines[] = $email;
        }
        if ($invoice->party_phone) {
            $lines[] = $invoice->party_phone;
        }

        return $lines;
    }

    private static function contactLine(): ?string
    {
        return collect([Setting::get('programme_email'), Setting::get('programme_phone')])
            ->filter()->implode('  -  ') ?: null;
    }

    /** The mark the office keeps for the website, folded to a JPEG the paper can carry. */
    private static function logo(): ?string
    {
        try {
            $stored = content('site', 'branding', 'logo');
            // The CMS stores either a path or a full URL back to this very site; the paper needs the file, not the link.
            $relative = $stored ? (string) parse_url($stored, PHP_URL_PATH) : '';
            $path = public_path(ltrim($relative, '/') ?: 'logo.png');
            if (! is_file($path)) {
                $path = public_path('logo.png');
            }
            if (! is_file($path) || filesize($path) > 3_000_000) {
                return null;
            }
            $source = @imagecreatefromstring((string) file_get_contents($path));
            if (! $source) {
                return null;
            }
            $canvas = imagecreatetruecolor($width = imagesx($source), $height = imagesy($source));
            imagefilledrectangle($canvas, 0, 0, $width, $height, imagecolorallocate($canvas, 255, 255, 255));
            imagealphablending($canvas, true);
            imagecopy($canvas, $source, 0, 0, 0, 0, $width, $height);
            ob_start();
            imagejpeg($canvas, null, 92);
            $bytes = (string) ob_get_clean();
            imagedestroy($source);
            imagedestroy($canvas);

            return strlen($bytes) > 400_000 ? null : $bytes;
        } catch (\Throwable) {
            return null;
        }
    }
}
