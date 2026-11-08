<?php

namespace App\Support;

use App\Models\Setting;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;

/**
 * The installable app the office puts in front of the public site. One Android package lives in the website's own
 * public folder — the host serves it as a plain file, so no PHP sits between a phone and the download — and the
 * settings rows hold the version, the note and an optional store link. Nothing here needs a table: keys are
 * created the first time the desk saves.
 */
class MobileApp
{
    public const FOLDER = 'uploads/app';

    /** The folder the app itself is served from — on the host this is public_html/app. */
    public const WEB_FOLDER = 'app';

    /** A package this size is already a very generous app; anything bigger is the wrong file. */
    public const MAX_KB = 200 * 1024;

    /** Where a phone opens the app in its own browser. Always answers, so the site never shows a dead button. */
    public static function webUrl(): string
    {
        return url('/'.self::WEB_FOLDER);
    }

    /** The stored package, but only while the file is really on disk — a missing file never offers a dead link. */
    public static function package(): ?string
    {
        $name = Setting::get('app_android_file');

        return $name && File::exists(public_path(self::FOLDER.'/'.$name)) ? $name : null;
    }

    public static function url(): ?string
    {
        $name = self::package();

        return $name ? asset(self::FOLDER.'/'.$name) : null;
    }

    public static function version(): ?string
    {
        return static::text('app_android_version');
    }

    public static function notes(): ?string
    {
        return static::text('app_android_notes');
    }

    /** When the current package went up, or null while nothing has been put out. */
    public static function publishedAt(): ?string
    {
        return static::text('app_android_at');
    }

    /** The Play Store link the office prefers over the file, or null. */
    public static function playUrl(): ?string
    {
        $url = static::text('app_play_url');

        return $url ? (SafeUrl::href($url, '') ?: null) : null;
    }

    /** How many times the button above has been taken, as the desk sees it. */
    public static function downloads(): int
    {
        return (int) Setting::get('app_android_downloads', '0');
    }

    /** A tap on the public Download button is counted here, so the desk can tell the file is reaching phones. */
    public static function countDownload(): void
    {
        Setting::set('app_android_downloads', (string) (self::downloads() + 1));
    }

    private static function text(string $key): ?string
    {
        $value = Setting::get($key);

        return ($value === null || trim($value) === '') ? null : $value;
    }

    public static function published(): bool
    {
        return self::url() !== null || self::playUrl() !== null;
    }

    /** "12.4 MB", or null when no package is on the shelf. */
    public static function sizeMb(): ?string
    {
        $name = self::package();

        return $name ? number_format(File::size(public_path(self::FOLDER.'/'.$name)) / 1024 / 1024, 1).' MB' : null;
    }

    /** Saving a new package overwrites the old one: the previous file is deleted in the same breath. */
    public static function putPackage(UploadedFile $file): void
    {
        $old = Setting::get('app_android_file');
        if ($old) {
            File::delete(public_path(self::FOLDER.'/'.$old));
        }

        $name = Uploads::apkName();
        $file->move(public_path(self::FOLDER), $name);
        Setting::set('app_android_file', $name);
        Setting::set('app_android_at', now()->format('d M Y, H:i'));
    }

    /** Taking the app down pulls the file off the server, not just the setting. */
    public static function removePackage(): void
    {
        $old = Setting::get('app_android_file');
        if ($old) {
            File::delete(public_path(self::FOLDER.'/'.$old));
        }

        Setting::where('key', 'app_android_file')->delete();
        Setting::where('key', 'app_android_at')->delete();
    }
}
