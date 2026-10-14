<?php

namespace App\Support;

/**
 * Semantic Versioning minimal (MAJOR.MINOR.PATCH, fără pre-release la pachetele de update).
 */
class Semver
{
    public const PATTERN = '/^(0|[1-9]\d*)\.(0|[1-9]\d*)\.(0|[1-9]\d*)$/';

    public static function isValid(?string $v): bool
    {
        return is_string($v) && preg_match(self::PATTERN, $v) === 1;
    }

    public static function compare(string $a, string $b): int
    {
        return version_compare($a, $b);
    }

    public static function gt(string $a, string $b): bool
    {
        return self::compare($a, $b) > 0;
    }

    public static function gte(string $a, string $b): bool
    {
        return self::compare($a, $b) >= 0;
    }

    /**
     * Constrângeri simple: ">=8.3", ">=1.2.0", "8.3.*" (doar operatori >=, >, <=, <, =).
     */
    public static function satisfies(string $version, string $constraint): bool
    {
        $constraint = trim($constraint);
        if ($constraint === '' || $constraint === '*') {
            return true;
        }
        foreach (preg_split('/\s*,\s*|\s+/', $constraint) as $part) {
            if (! preg_match('/^(>=|<=|>|<|=|==)?\s*([0-9][0-9.]*)$/', $part, $m)) {
                return false;
            }
            $op = $m[1] ?: '>=';
            if (! version_compare($version, $m[2], $op === '=' ? '==' : $op)) {
                return false;
            }
        }

        return true;
    }
}
