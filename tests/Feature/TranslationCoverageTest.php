<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\File;
use Tests\TestCase;

/**
 * Fiecare text din interfață (cheile __('…') scrise în română) TREBUIE să aibă traducere în lang/hu.json.
 * Astfel nu poate ajunge în producție un text netradus.
 */
class TranslationCoverageTest extends TestCase
{
    public static function extractKeys(): array
    {
        $keys = [];
        $files = array_merge(File::allFiles(resource_path('views')), File::allFiles(app_path()));
        foreach ($files as $file) {
            $src = $file->getContents();
            preg_match_all("/__\\(\\s*'((?:[^'\\\\]|\\\\.)+)'\\s*[,)]/u", $src, $m);
            foreach ($m[1] as $k) {
                $keys[stripslashes($k)] = $file->getRelativePathname();
            }
        }

        return $keys;
    }

    public function test_every_ui_string_has_a_hungarian_translation(): void
    {
        $hu = json_decode(File::get(lang_path('hu.json')), true, flags: JSON_THROW_ON_ERROR);
        $missing = array_diff_key(self::extractKeys(), $hu);

        $this->assertSame([], array_keys($missing), 'Chei fără traducere HU: '.implode(' | ', array_slice(array_keys($missing), 0, 30)));
    }

    public function test_hungarian_translations_are_not_empty_and_keep_placeholders(): void
    {
        $hu = json_decode(File::get(lang_path('hu.json')), true);
        foreach ($hu as $ro => $translation) {
            $this->assertNotSame('', trim($translation), "Traducere goală: {$ro}");
            preg_match_all('/:[a-z_]+/', $ro, $a);
            preg_match_all('/:[a-z_]+/', $translation, $b);
            sort($a[0]);
            sort($b[0]);
            $this->assertSame($a[0], $b[0], "Placeholder-e diferite în traducerea pentru: {$ro}");
        }
    }
}
