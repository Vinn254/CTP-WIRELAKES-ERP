<?php

namespace App\Support;

use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * The app sends a picture as base64 inside the JSON body. Every portal writer already reads its photo off the
 * request as an uploaded file, so the bytes are re-attached here before anything validates — the model's own
 * store*() methods and their image rules then run unchanged.
 */
trait UploadsFromJson
{
    /** @param string[] $inputs form input names that may carry a "<input>_b64" picture */
    protected function liftUploads(Request $request, array $inputs): void
    {
        foreach ($inputs as $input) {
            $payload = $request->input($input.'_b64');

            if (! is_string($payload) || $payload === '') {
                continue;
            }

            $mime = 'image/jpeg';

            if (preg_match('#^data:([\w/+.-]+);base64,#', $payload, $m) === 1) {
                $mime = $m[1];
                $payload = substr($payload, strpos($payload, ',') + 1);
            }

            $bytes = base64_decode($payload, true);
            abort_if($bytes === false || $bytes === '', 422, 'That picture could not be read — take it again.');
            abort_if(strlen($bytes) > 8 * 1024 * 1024, 422, 'A picture is up to 8 MB.');

            $extension = match (true) {
                str_contains($mime, 'png') => 'png',
                str_contains($mime, 'pdf') => 'pdf',
                str_contains($mime, 'webp') => 'webp',
                default => 'jpg',
            };

            $scratch = tempnam(sys_get_temp_dir(), 'ctp-');
            file_put_contents($scratch, $bytes);

            $name = (string) ($request->input($input.'_name') ?: $input);

            $request->files->set($input, new UploadedFile($scratch, Str::slug($name, '-').'.'.$extension, $mime, null, true));
        }
    }

    /** A private file off the disk, sent inline so the app needs no second request to open it. */
    protected function inline(?string $path, string $fallback = 'document'): array
    {
        if (! $path || ! Storage::disk('local')->exists($path)) {
            return ['file' => null];
        }

        $mime = match (true) {
            str_ends_with($path, '.pdf') => 'application/pdf',
            str_ends_with($path, '.png') => 'image/png',
            default => 'image/jpeg',
        };

        return [
            'name' => $fallback,
            'file' => 'data:'.$mime.';base64,'.base64_encode(Storage::disk('local')->get($path)),
        ];
    }
}
