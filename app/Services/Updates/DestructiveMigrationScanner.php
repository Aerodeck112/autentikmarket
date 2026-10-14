<?php

namespace App\Services\Updates;

/**
 * Detectează operații potențial distructive în metoda up() a unei migrații.
 * Nu blochează automat — cere confirmare explicită din partea administratorului.
 */
class DestructiveMigrationScanner
{
    protected const PATTERNS = [
        '/Schema::drop(IfExists)?\s*\(/i' => 'Schema::drop',
        '/->drop(Column|IfExists|Columns)?\s*\(/i' => 'drop / dropColumn',
        '/->truncate\s*\(/i' => 'truncate',
        '/->delete\s*\(/i' => 'delete',
        '/\b(DROP|TRUNCATE)\s+(TABLE|DATABASE|COLUMN)\b/i' => 'SQL DROP/TRUNCATE',
        '/\bDELETE\s+FROM\b/i' => 'SQL DELETE',
        '/->renameColumn\s*\(/i' => 'renameColumn',
        '/Schema::rename\s*\(/i' => 'Schema::rename',
    ];

    /** @return list<string> */
    public static function scan(string $php): array
    {
        $up = $php;
        if (preg_match('/function\s+up\s*\([^)]*\)\s*(?::\s*\w+)?\s*\{/i', $php, $m, PREG_OFFSET_CAPTURE)) {
            $start = $m[0][1] + strlen($m[0][0]);
            $downPos = stripos($php, 'function down', $start);
            $up = substr($php, $start, $downPos === false ? null : $downPos - $start);
        }
        // Ignorăm comentariile.
        $up = preg_replace(['~//[^\n]*~', '~/\*.*?\*/~s', '~#[^\n]*~'], '', $up) ?? $up;

        $found = [];
        foreach (self::PATTERNS as $regex => $label) {
            if (preg_match($regex, $up)) {
                $found[] = $label;
            }
        }

        return array_values(array_unique($found));
    }
}
