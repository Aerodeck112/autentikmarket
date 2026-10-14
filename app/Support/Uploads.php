<?php

namespace App\Support;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;

/**
 * Upload-uri sigure: nume UUID (fără numele original pe disc), extensie din MIME-ul real,
 * imagini pe discul public (fără PHP executabil — vezi .htaccess din storage), documente pe discul privat.
 */
class Uploads
{
    public static function storeImage(UploadedFile $file, string $dir): string
    {
        $ext = self::safeExtension($file, ['jpg', 'jpeg', 'png', 'webp']);

        return $file->storeAs($dir, Str::uuid().'.'.$ext, 'public');
    }

    public static function storePrivate(UploadedFile $file, string $dir, array $allowed = ['pdf', 'jpg', 'jpeg', 'png']): string
    {
        $ext = self::safeExtension($file, $allowed);

        return $file->storeAs($dir, Str::uuid().'.'.$ext, 'private');
    }

    protected static function safeExtension(UploadedFile $file, array $allowed): string
    {
        $ext = strtolower($file->guessExtension() ?: $file->getClientOriginalExtension());
        $ext = $ext === 'jpeg' ? 'jpg' : $ext;
        if (! in_array($ext, $allowed, true)) {
            abort(422, __('Tip de fișier neacceptat.'));
        }

        return $ext;
    }

    public static function cleanName(string $name): string
    {
        $name = preg_replace('/[^\pL\pN._ -]+/u', '', $name) ?: 'document';

        return mb_substr($name, 0, 200);
    }
}
