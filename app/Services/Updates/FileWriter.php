<?php

namespace App\Services\Updates;

/**
 * Scriere atomică: fișier temporar în același director + rename().
 */
class FileWriter
{
    /** @param resource|false $stream */
    public static function writeStream(string $target, $stream): void
    {
        if ($stream === false) {
            throw new UpdateException('Flux de date indisponibil pentru '.basename($target));
        }
        $dir = dirname($target);
        if (! is_dir($dir) && ! @mkdir($dir, 0755, true) && ! is_dir($dir)) {
            throw new UpdateException("Nu pot crea directorul {$dir}");
        }
        $tmp = $dir.'/.am-update-'.bin2hex(random_bytes(6)).'.tmp';
        $out = @fopen($tmp, 'wb');
        if ($out === false) {
            throw new UpdateException("Nu pot scrie în {$dir} (permisiuni?)");
        }
        stream_copy_to_stream($stream, $out);
        fclose($out);
        fclose($stream);
        @chmod($tmp, 0644);
        if (! @rename($tmp, $target)) {
            @unlink($tmp);
            throw new UpdateException('Nu pot înlocui fișierul '.$target);
        }
        if (function_exists('opcache_invalidate') && str_ends_with($target, '.php')) {
            @opcache_invalidate($target, true);
        }
    }
}
