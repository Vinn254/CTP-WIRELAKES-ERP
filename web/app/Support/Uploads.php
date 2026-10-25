<?php

namespace App\Support;

use Illuminate\Http\UploadedFile;

/**
 * What a visitor hands us — a logo, a photo, a link — is stored under a name and a form we choose, never the one
 * they typed. A file called x.php that carries PNG bytes used to land on the public disk as x.php, which a
 * misconfigured host will happily run; a link beginning with javascript: used to sit inside an href.
 */
class Uploads
{
    private const IMAGE_EXTENSIONS = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
        'image/gif' => 'gif',
    ];

    /** The extension comes from the bytes in the file, not from its name. */
    public static function imageExtension(UploadedFile $file): string
    {
        return self::IMAGE_EXTENSIONS[$file->guessMimeType()] ?? 'png';
    }

    /** Only the picture formats the programme asks for. SVG is left out on purpose: it can carry a script. */
    public const IMAGE_MIMES = 'jpg,jpeg,png,webp';
}
