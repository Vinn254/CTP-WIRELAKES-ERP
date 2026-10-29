<?php

namespace App\Support;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Every file a screen hands over goes through here, so one link does both jobs: opened in the browser as it always
 * was, or saved as a file when the url carries ?download=1. A Download button is then the same url with one word
 * added — nothing about how a document is stored or guarded changes.
 */
class Documents
{
    /** A paper on the disk: shown inline, or downloaded when asked for. */
    public static function file(Request $request, string $path, ?string $name = null, string $disk = 'local')
    {
        $store = Storage::disk($disk);
        abort_unless($store->exists($path), 404, 'That file is no longer on record.');

        $name = static::named($name ?: basename($path), $path);

        return $request->boolean('download') ? $store->download($path, $name) : $store->response($path, $name);
    }

    /**
     * A page the system lays out itself — an invoice, a purchase order, a quotation. Downloading it saves the very
     * page that prints, so the file opened later looks like the one on screen and still prints to a PDF.
     */
    public static function page(Request $request, string $html, string $reference)
    {
        if (! $request->boolean('download')) {
            return $html;
        }

        return response($html, 200, [
            'Content-Type' => 'text/html; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="'.static::slug($reference).'.html"',
        ]);
    }

    /** The url a Download button points at: the document's own url with the word that saves it instead of showing it. */
    public static function downloadUrl(string $url): string
    {
        return $url.(str_contains($url, '?') ? '&' : '?').'download=1';
    }

    /** A name safe to write on someone's disk — the reference, not the punctuation a filename cannot hold. */
    public static function slug(string $reference): string
    {
        return Str::slug($reference) ?: 'document';
    }

    private static function named(string $name, string $path): string
    {
        $extension = pathinfo($path, PATHINFO_EXTENSION);

        if ($extension && ! str_ends_with(strtolower($name), '.'.$extension)) {
            $name .= '.'.$extension;
        }

        return $name;
    }
}
