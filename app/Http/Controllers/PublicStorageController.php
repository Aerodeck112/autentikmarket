<?php

namespace App\Http\Controllers;

use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Rezervă pentru găzduirile unde `storage:link` nu se poate crea (fără Terminal / symlink interzis):
 * servește imaginile publice din storage/app/public la aceeași adresă /storage/….
 * Când linkul există, Apache servește fișierul direct și această rută nu este atinsă.
 * Doar imagini (listă albă), fără traversare de directoare.
 */
class PublicStorageController extends Controller
{
    protected const TYPES = [
        'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png',
        'webp' => 'image/webp', 'gif' => 'image/gif', 'avif' => 'image/avif',
    ];

    public function __invoke(string $path): BinaryFileResponse
    {
        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        abort_unless(isset(self::TYPES[$ext]) && ! str_contains($path, '..') && ! str_contains($path, "\0"), 404);

        $root = realpath(storage_path('app/public'));
        $file = realpath($root.'/'.$path);
        abort_unless($root && $file && str_starts_with($file, $root.DIRECTORY_SEPARATOR) && is_file($file), 404);

        return response()->file($file, [
            'Content-Type' => self::TYPES[$ext],
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'public, max-age=2592000',
        ]);
    }
}
