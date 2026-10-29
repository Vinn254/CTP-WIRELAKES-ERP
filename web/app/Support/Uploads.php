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

    private const VIDEO_EXTENSIONS = [
        'video/mp4' => 'mp4',
        'video/webm' => 'webm',
        'video/quicktime' => 'mov',
    ];

    /** The extension comes from the bytes in the file, not from its name. */
    public static function imageExtension(UploadedFile $file): string
    {
        return self::IMAGE_EXTENSIONS[$file->getMimeType()] ?? 'png';
    }

    /** A clip is renamed the same way — a .mp4 whose bytes are something else never reaches the public folder. */
    public static function videoExtension(UploadedFile $file): string
    {
        return self::VIDEO_EXTENSIONS[$file->getMimeType()] ?? 'mp4';
    }

    /** Only the picture formats the programme asks for. SVG is left out on purpose: it can carry a script. */
    public const IMAGE_MIMES = 'jpg,jpeg,png,webp';

    /** The three phone- or camera-friendly formats a browser will actually play back. */
    public const VIDEO_MIMES = 'mp4,webm,mov';
}
