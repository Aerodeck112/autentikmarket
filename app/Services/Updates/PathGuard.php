<?php

namespace App\Services\Updates;

/**
 * Validarea căilor din pachetele de update.
 *
 * Protecție ZIP SLIP / path traversal: orice cale este respinsă dacă:
 *  - este absolută (/etc/passwd, C:\…), conține backslash, octet NUL sau caractere de control;
 *  - conține segmente "..", "." sau goale ("a//b");
 *  - iese din lista albă (allowed_paths) sau atinge o cale protejată (.env, storage/, uploads…);
 *  - ar plasa un fișier PHP executabil în public/ (exceptând public/index.php).
 * În plus, la scriere se verifică realpath-ul directorului părinte (protecție la symlink-uri).
 */
class PathGuard
{
    public function __construct(
        protected array $allowed,
        protected array $protected,
    ) {}

    public static function fromConfig(): self
    {
        return new self(config('update.allowed_paths', []), config('update.protected_paths', []));
    }

    /** @throws UpdateException */
    public function assertSafeRelative(string $path): string
    {
        if ($path === '' || strlen($path) > 400) {
            throw new UpdateException("Cale invalidă (goală sau prea lungă): {$this->display($path)}");
        }
        if (str_contains($path, "\0") || preg_match('/[\x00-\x1F\x7F]/', $path)) {
            throw new UpdateException("Cale invalidă (caractere de control): {$this->display($path)}");
        }
        if (str_contains($path, '\\')) {
            throw new UpdateException("Cale invalidă (backslash): {$this->display($path)}");
        }
        if (str_starts_with($path, '/') || preg_match('/^[a-zA-Z]:/', $path) || str_contains($path, ':')) {
            throw new UpdateException("Cale absolută sau invalidă respinsă: {$this->display($path)}");
        }
        $trimmed = rtrim($path, '/');
        foreach (explode('/', $trimmed) as $segment) {
            if ($segment === '' || $segment === '.' || $segment === '..') {
                throw new UpdateException("Path traversal (Zip Slip) blocat: {$this->display($path)}");
            }
        }

        return $path;
    }

    public function isProtected(string $relative): bool
    {
        $rel = strtolower($relative);
        foreach ($this->protected as $p) {
            $p = strtolower($p);
            if (str_ends_with($p, '/')) {
                if (str_starts_with($rel, $p) || $rel.'/' === $p) {
                    return true;
                }
            } elseif (str_ends_with($p, '.')) {
                if (str_starts_with($rel, $p)) {
                    return true;
                }
            } elseif ($rel === $p) {
                return true;
            }
        }

        return false;
    }

    public function isAllowed(string $relative): bool
    {
        foreach ($this->allowed as $a) {
            if (str_ends_with($a, '/') ? str_starts_with($relative, $a) : $relative === $a) {
                return true;
            }
        }

        return false;
    }

    /** @throws UpdateException */
    public function assertWritable(string $relative): void
    {
        $this->assertSafeRelative($relative);
        if ($this->isProtected($relative)) {
            throw new UpdateException("Cale protejată (nu poate fi modificată de un update): {$relative}");
        }
        if (! $this->isAllowed($relative)) {
            throw new UpdateException("Cale în afara directoarelor permise: {$relative}");
        }
        if (str_starts_with($relative, 'public/') && $relative !== 'public/index.php'
            && preg_match('/\.(php\d?|phtml|phar|pht|phps|cgi|pl|py|sh)$/i', $relative)) {
            throw new UpdateException("Fișier executabil interzis în directorul public: {$relative}");
        }
    }

    /**
     * Calea absolută finală, după verificarea că directorul părinte real (realpath)
     * se află în interiorul rădăcinii aplicației (protecție symlink).
     *
     * @throws UpdateException
     */
    public function resolveInside(string $root, string $relative): string
    {
        $this->assertWritable($relative);
        $rootReal = realpath($root);
        if ($rootReal === false) {
            throw new UpdateException('Directorul aplicației nu există.');
        }
        $target = $rootReal.'/'.$relative;

        // Cel mai adânc director existent din calea țintă trebuie să fie în interiorul rădăcinii.
        $dir = dirname($target);
        while (! is_dir($dir) && $dir !== dirname($dir)) {
            $dir = dirname($dir);
        }
        $dirReal = realpath($dir);
        if ($dirReal === false || ($dirReal !== $rootReal && ! str_starts_with($dirReal.'/', $rootReal.'/'))) {
            throw new UpdateException("Calea iese din directorul aplicației (symlink?): {$relative}");
        }
        if (is_link($target)) {
            throw new UpdateException("Ținta este un symlink și nu va fi suprascrisă: {$relative}");
        }

        return $target;
    }

    protected function display(string $p): string
    {
        return addcslashes(mb_substr($p, 0, 120), "\0..\37");
    }
}
