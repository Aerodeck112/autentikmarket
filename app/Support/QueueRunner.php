<?php

namespace App\Support;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Procesarea cozii ÎN ACELAȘI PROCES (fără proc_open / exec, des dezactivate pe hostingul partajat).
 * Folosită de cron (routes/console.php) și de butonul „Trimite acum” din CRM.
 * Rezultatul ultimei rulări rămâne în cache pentru diagnostic (CRM → Sistem / Automatizări).
 */
class QueueRunner
{
    public const LAST_RUN_KEY = 'queue.last_run';

    public const HEARTBEAT_INFO_KEY = 'scheduler.info';

    /**
     * @return array{at: string, source: string, processed: int, remaining: int, failed: int, error: ?string}
     */
    public static function run(int $maxSeconds, string $source = 'cron'): array
    {
        $before = DB::table('jobs')->count();
        $failedBefore = DB::table('failed_jobs')->count();
        $error = null;

        try {
            Artisan::call('queue:work', [
                '--stop-when-empty' => true,
                '--max-time' => $maxSeconds,
                '--tries' => 3,
                '--sleep' => 1,
                // Fără alarmă pcntl: un timeout ar opri tot procesul (care rulează și restul programului).
                '--timeout' => 0,
            ]);
        } catch (Throwable $e) {
            report($e);
            $error = $e->getMessage();
        }

        $remaining = DB::table('jobs')->count();
        $result = [
            'at' => now()->format('d.m.Y H:i:s'),
            'source' => $source,
            'processed' => max(0, $before - $remaining),
            'remaining' => $remaining,
            'failed' => max(0, DB::table('failed_jobs')->count() - $failedBefore),
            'error' => $error,
        ];
        Cache::put(self::LAST_RUN_KEY, $result, 7 * 86400);

        return $result;
    }

    /** Bătaia de inimă a cron-ului + cu ce PHP rulează (diferit uneori de PHP-ul site-ului pe cPanel). */
    public static function heartbeat(): void
    {
        Cache::put('scheduler.heartbeat', now()->format('d.m.Y H:i'), 86400);
        Cache::put(self::HEARTBEAT_INFO_KEY, [
            'php' => PHP_VERSION,
            'binary' => PHP_BINARY,
            'proc_open' => self::procOpenAvailable(),
        ], 86400);
    }

    public static function procOpenAvailable(): bool
    {
        $disabled = array_map('trim', explode(',', (string) ini_get('disable_functions')));

        return function_exists('proc_open') && ! in_array('proc_open', $disabled, true);
    }

    public static function lastRun(): ?array
    {
        return Cache::get(self::LAST_RUN_KEY);
    }
}
