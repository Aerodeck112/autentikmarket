<?php

namespace Tests\Feature;

use App\Services\Updates\PathGuard;
use App\Services\Updates\ReleaseBuilder;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

/**
 * Regresie: tot ce pune ReleaseBuilder într-un pachet de update trebuie să treacă de PathGuard-ul
 * instalării; altfel FIECARE update ar fi respins la încărcare (ex. un PHP nou în public/).
 */
class ReleasePathsTest extends TestCase
{
    public function test_every_file_of_an_update_package_is_accepted_by_the_installer(): void
    {
        $guard = PathGuard::fromConfig();
        $checked = 0;
        foreach (ReleaseBuilder::APP_PATHS as $path) {
            $abs = base_path($path);
            $files = is_dir($abs) ? File::allFiles($abs, true) : (is_file($abs) ? [new \SplFileInfo($abs)] : []);
            foreach ($files as $file) {
                $relative = ltrim(str_replace('\\', '/', substr($file->getPathname(), strlen(base_path()))), '/');
                $guard->assertWritable($guard->assertSafeRelative($relative));
                $checked++;
            }
        }

        $this->assertGreaterThan(300, $checked);
    }

    public function test_the_web_installer_ships_only_in_the_install_package(): void
    {
        $this->assertNotContains('public/install.php', ReleaseBuilder::APP_PATHS);
        $this->assertContains('public/install.php', ReleaseBuilder::INSTALL_EXTRA);
    }
}
