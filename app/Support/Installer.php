<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Contracts\Console\Kernel;
use PDO;
use RuntimeException;
use Throwable;

/**
 * INSTALATOR WEB (public/install.php) — pentru găzduiri cPanel fără Terminal.
 *
 * Rulează ÎNAINTE ca Laravel să poată porni (fără .env, fără APP_KEY), deci nu folosește helperi Laravel
 * până la pasul setup(). Protecții:
 *  - funcționează doar cât timp aplicația NU este instalată (lacăt în storage/app/installed.lock);
 *  - cere un cod de instalare generat aleator în storage/app/private/install-token.txt, pe care doar
 *    cineva cu acces la File Manager îl poate citi (dovada că e proprietarul găzduirii);
 *  - o instalare manuală existentă (.env cu APP_KEY) este considerată instalată.
 */
class Installer
{
    public const MIN_PHP = '8.3.0';

    public const EXTENSIONS = ['pdo_mysql', 'mbstring', 'openssl', 'zip', 'fileinfo', 'sodium', 'ctype', 'tokenizer', 'xml', 'dom', 'curl'];

    public function __construct(protected string $base) {}

    public function envPath(): string
    {
        return $this->base.'/.env';
    }

    protected function lockPath(): string
    {
        return $this->base.'/storage/app/installed.lock';
    }

    protected function progressPath(): string
    {
        return $this->base.'/storage/app/install-in-progress';
    }

    public function tokenPath(): string
    {
        return $this->base.'/storage/app/private/install-token.txt';
    }

    /** Instalată = lacăt prezent, sau un .env cu cheie care NU provine dintr-o instalare web neterminată. */
    public function installed(): bool
    {
        if (is_file($this->lockPath())) {
            return true;
        }
        if (is_file($this->progressPath())) {
            return false;
        }

        return is_file($this->envPath()) && preg_match('/^APP_KEY=\S+/m', (string) file_get_contents($this->envPath())) === 1;
    }

    /** Codul de instalare: generat o singură dată, citit de proprietar din File Manager. */
    public function ensureToken(): void
    {
        if (is_file($this->tokenPath())) {
            return;
        }
        if (! is_dir(dirname($this->tokenPath()))) {
            mkdir(dirname($this->tokenPath()), 0755, true);
        }
        file_put_contents($this->tokenPath(), strtoupper(bin2hex(random_bytes(6)))."\n");
        @chmod($this->tokenPath(), 0600);
    }

    public function checkToken(string $given): bool
    {
        $expected = is_file($this->tokenPath()) ? trim((string) file_get_contents($this->tokenPath())) : '';
        $given = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $given));

        return $expected !== '' && hash_equals($expected, $given);
    }

    /**
     * @return array<int, array{label: string, ok: bool, required: bool, detail: string}>
     */
    public function requirements(): array
    {
        $checks = [[
            'label' => 'PHP ≥ '.self::MIN_PHP,
            'ok' => version_compare(PHP_VERSION, self::MIN_PHP, '>='),
            'required' => true,
            'detail' => 'versiunea curentă: '.PHP_VERSION.' (cPanel → MultiPHP Manager)',
        ]];
        foreach (self::EXTENSIONS as $ext) {
            $checks[] = ['label' => "Extensia PHP {$ext}", 'ok' => extension_loaded($ext), 'required' => true, 'detail' => 'cPanel → Select PHP Version → Extensions'];
        }
        foreach (['.' => 'directorul aplicației (pentru .env)', 'storage' => 'storage/', 'storage/app' => 'storage/app/', 'storage/framework' => 'storage/framework/', 'storage/logs' => 'storage/logs/', 'bootstrap/cache' => 'bootstrap/cache/'] as $dir => $label) {
            $checks[] = ['label' => "Scriere în {$label}", 'ok' => is_writable($this->base.'/'.$dir), 'required' => true, 'detail' => 'permisiuni 755 pentru directoare'];
        }
        $upload = min(self::bytes((string) ini_get('upload_max_filesize')), self::bytes((string) ini_get('post_max_size')));
        $checks[] = [
            'label' => 'Limită upload ≥ 64 MB (pentru pachetele de update)',
            'ok' => $upload >= 64 * 1024 * 1024,
            'required' => false,
            'detail' => 'acum: '.ini_get('upload_max_filesize').' / '.ini_get('post_max_size').' (cPanel → MultiPHP INI Editor)',
        ];
        $memory = self::bytes((string) ini_get('memory_limit'));
        $checks[] = ['label' => 'memory_limit ≥ 256 MB', 'ok' => $memory < 0 || $memory >= 256 * 1024 * 1024, 'required' => false, 'detail' => 'acum: '.ini_get('memory_limit')];

        return $checks;
    }

    public function requirementsMet(): bool
    {
        foreach ($this->requirements() as $c) {
            if ($c['required'] && ! $c['ok']) {
                return false;
            }
        }

        return true;
    }

    /** @return string|null mesaj de eroare sau null dacă merge */
    public function testDatabase(array $db): ?string
    {
        try {
            $pdo = new PDO(
                sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', $db['host'], (int) $db['port'], $db['database']),
                $db['username'],
                $db['password'],
                [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_TIMEOUT => 5]
            );
            $pdo->query('SELECT 1');

            return null;
        } catch (Throwable $e) {
            return 'Conectarea la baza de date a eșuat: '.$e->getMessage();
        }
    }

    /**
     * Scrie .env pornind de la .env.example (valorile date înlocuiesc cheile existente).
     *
     * @param  array<string, string>  $values
     */
    public function writeEnv(array $values): void
    {
        $template = $this->base.'/.env.example';
        $content = is_file($template) ? (string) file_get_contents($template) : '';
        foreach ($values as $key => $value) {
            if (! preg_match('/^[A-Z][A-Z0-9_]*$/', $key)) {
                throw new RuntimeException("Cheie .env invalidă: {$key}");
            }
            $line = $key.'='.self::envValue((string) $value);
            $pattern = '/^'.preg_quote($key, '/').'=.*$/m';
            $content = preg_match($pattern, $content)
                ? preg_replace_callback($pattern, fn () => $line, $content, 1)
                : rtrim($content)."\n".$line."\n";
        }
        if (file_put_contents($this->envPath(), $content) === false) {
            throw new RuntimeException('Fișierul .env nu a putut fi scris (verificați permisiunile directorului aplicației).');
        }
        @chmod($this->envPath(), 0600);
    }

    /** Valoare .env sigură pentru phpdotenv: fără ghilimele când nu e nevoie, altfel citată fără interpolare. */
    public static function envValue(string $value): string
    {
        if (preg_match('/[\r\n\0]/', $value)) {
            throw new RuntimeException('Valorile din .env nu pot conține caractere de rând nou.');
        }
        if ($value === '' || preg_match('/^[A-Za-z0-9_.:\/@+,-]+$/', $value)) {
            return $value;
        }
        if (! str_contains($value, "'")) {
            return "'".$value."'"; // ghilimele simple = text literal ($, #, spații, " sunt păstrate)
        }

        return '"'.str_replace(['\\', '"', '$'], ['\\\\', '\\"', '\\$'], $value).'"';
    }

    public static function generateKey(): string
    {
        return 'base64:'.base64_encode(random_bytes(32));
    }

    /** Începutul instalării: din acest moment .env există, dar instalarea nu e încheiată (se poate relua). */
    public function markInProgress(): void
    {
        file_put_contents($this->progressPath(), date('c'));
    }

    /**
     * Pornește Laravel cu noul .env și rulează pașii de instalare (echivalentul comenzilor din Terminal).
     *
     * @param  array{email: string, first_name: string, last_name: string, password: string, language: string}  $admin
     * @return array{ok: bool, log: array<int, array{step: string, ok: bool, output: string}>}
     */
    public function setup(array $admin): array
    {
        @set_time_limit(300);
        foreach (glob($this->base.'/bootstrap/cache/*.php') ?: [] as $cached) {
            @unlink($cached); // config/rute din altă instalare nu trebuie să interfereze
        }

        $app = require $this->base.'/bootstrap/app.php';
        $kernel = $app->make(Kernel::class);
        $kernel->bootstrap();

        $steps = [
            'migrate' => ['migrate', ['--force' => true]],
            'seed' => ['db:seed', ['--force' => true]],
            'storage' => ['storage:link', []],
            'admin' => ['app:create-super-admin', [
                '--email' => $admin['email'], '--first-name' => $admin['first_name'], '--last-name' => $admin['last_name'],
                '--password' => $admin['password'], '--language' => $admin['language'], '--no-interaction' => true,
            ]],
            'optimize' => ['optimize', []],
        ];

        $log = [];
        foreach ($steps as $name => [$command, $params]) {
            // Contul admin există deja dacă instalarea este reluată după o eroare ulterioară.
            if ($name === 'admin' && User::where('email', $admin['email'])->exists()) {
                $log[] = ['step' => $name, 'ok' => true, 'output' => 'Contul există deja.'];

                continue;
            }
            try {
                $code = $kernel->call($command, $params);
                $output = trim($kernel->output());
            } catch (Throwable $e) {
                $code = 1;
                $output = $e->getMessage();
            }
            // storage:link poate fi interzis de găzduire (symlink dezactivat): fișierele publice sunt
            // servite atunci de aplicație (ruta /storage/{path}), deci nu blocăm instalarea.
            $ok = $code === 0 || $name === 'storage';
            $log[] = ['step' => $name, 'ok' => $ok, 'output' => $output];
            if (! $ok) {
                return ['ok' => false, 'log' => $log];
            }
        }

        file_put_contents($this->lockPath(), date('c'));
        @unlink($this->progressPath());
        @unlink($this->tokenPath());

        return ['ok' => true, 'log' => $log];
    }

    /** Calea probabilă a PHP CLI pentru cron (din binarul PHP-FPM/CGI care servește pagina). */
    public static function guessPhpCli(): string
    {
        $bin = PHP_BINARY;
        if (preg_match('#^(.*)/sbin/php-fpm[0-9.]*$#', $bin, $m)) {
            return $m[1].'/bin/php';
        }
        if (preg_match('#^(.*)/php-cgi([0-9.]*)$#', $bin, $m)) {
            return $m[1].'/php'.$m[2];
        }

        return $bin !== '' && ! str_contains($bin, 'httpd') && ! str_contains($bin, 'apache') ? $bin : '/usr/local/bin/php';
    }

    public static function bytes(string $v): int
    {
        $v = trim($v);
        if ($v === '' || $v === '-1') {
            return -1;
        }
        $n = (int) $v;

        return match (strtolower(substr($v, -1))) {
            'g' => $n * 1024 ** 3,
            'm' => $n * 1024 ** 2,
            'k' => $n * 1024,
            default => $n,
        };
    }
}
