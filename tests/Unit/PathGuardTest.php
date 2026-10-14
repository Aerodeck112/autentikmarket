<?php

namespace Tests\Unit;

use App\Services\Updates\PathGuard;
use App\Services\Updates\UpdateException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class PathGuardTest extends TestCase
{
    protected function guard(): PathGuard
    {
        return new PathGuard(['app/', 'public/build/', 'public/index.php', 'VERSION'], ['.env', '.env.', 'storage/', 'public/storage', '.htaccess']);
    }

    public static function unsafe(): array
    {
        return [['../x'], ['a/../../x'], ['/etc/passwd'], ['C:/x'], ['a\\b'], ["a\0b"], ['a//b'], ['./x'], ['']];
    }

    #[DataProvider('unsafe')]
    public function test_rejects_traversal_and_absolute_paths(string $p): void
    {
        $this->expectException(UpdateException::class);
        $this->guard()->assertSafeRelative($p);
    }

    public function test_protected_and_allowed(): void
    {
        $g = $this->guard();
        $this->assertTrue($g->isProtected('.env'));
        $this->assertTrue($g->isProtected('.ENV'));
        $this->assertTrue($g->isProtected('.env.production'));
        $this->assertTrue($g->isProtected('storage/logs/x.log'));
        $this->assertFalse($g->isProtected('app/Models/User.php'));
        $this->assertTrue($g->isAllowed('app/Models/User.php'));
        $this->assertFalse($g->isAllowed('vendor/x.php'));
    }

    public function test_php_in_public_build_is_rejected(): void
    {
        $this->expectException(UpdateException::class);
        $this->guard()->assertWritable('public/build/x.php');
    }

    public function test_symlinked_directory_escape_is_blocked(): void
    {
        $root = sys_get_temp_dir().'/pg-'.uniqid();
        $outside = sys_get_temp_dir().'/pg-out-'.uniqid();
        mkdir($root.'/app', 0777, true);
        mkdir($outside);
        symlink($outside, $root.'/app/evil');

        try {
            $this->expectException(UpdateException::class);
            $this->guard()->resolveInside($root, 'app/evil/x.php');
        } finally {
            unlink($root.'/app/evil');
            rmdir($root.'/app');
            rmdir($root);
            rmdir($outside);
        }
    }
}
