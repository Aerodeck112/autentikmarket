<?php

namespace App\Support;

/**
 * Sursa unică a versiunii aplicației: fișierul VERSION din rădăcină.
 * Update Manager-ul îl înlocuiește la fiecare actualizare reușită.
 */
class AppVersion
{
    public static function current(?string $basePath = null): string
    {
        $file = rtrim($basePath ?? base_path(), '/').'/VERSION';
        $v = is_file($file) ? trim((string) file_get_contents($file)) : '';

        return Semver::isValid($v) ? $v : '0.0.0';
    }
}
