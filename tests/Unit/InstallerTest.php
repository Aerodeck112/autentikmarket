<?php

namespace Tests\Unit;

use App\Support\Installer;
use Dotenv\Dotenv;
use Illuminate\Support\Facades\File;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class InstallerTest extends TestCase
{
    protected string $dir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->dir = sys_get_temp_dir().'/am-installer-'.bin2hex(random_bytes(4));
        File::ensureDirectoryExists($this->dir.'/storage/app/private');
        file_put_contents($this->dir.'/.env.example', "APP_NAME=\"Autentik Market\"\nAPP_KEY=\nDB_PASSWORD=\nMAIL_MAILER=smtp\n");
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->dir);
        parent::tearDown();
    }

    public static function secrets(): array
    {
        return [
            'simplu' => ['Parola123'],
            'spațiu și diez' => ['pa ss#word'],
            'dolar' => ['ab$HOME${X}cd'],
            'ghilimele duble' => ['a"b"c'],
            'apostrof + dolar' => ["it's \$x \"q\" \\n"],
            'unicode' => ['ăîșțâ€'],
            'gol' => [''],
        ];
    }

    #[DataProvider('secrets')]
    public function test_env_values_round_trip_through_dotenv(string $secret): void
    {
        $parsed = Dotenv::parse('DB_PASSWORD='.Installer::envValue($secret)."\n");

        $this->assertSame($secret, $parsed['DB_PASSWORD']);
    }

    public function test_write_env_replaces_keys_and_keeps_the_rest(): void
    {
        $installer = new Installer($this->dir);
        $installer->writeEnv(['APP_KEY' => Installer::generateKey(), 'DB_PASSWORD' => 'p@ss w$rd', 'NEW_KEY' => 'x']);

        $env = Dotenv::parse(file_get_contents($this->dir.'/.env'));
        $this->assertSame('Autentik Market', $env['APP_NAME']);
        $this->assertSame('p@ss w$rd', $env['DB_PASSWORD']);
        $this->assertSame('x', $env['NEW_KEY']);
        $this->assertSame(32, strlen(base64_decode(substr($env['APP_KEY'], 7))));
        $this->assertSame(1, substr_count(file_get_contents($this->dir.'/.env'), 'DB_PASSWORD='));
    }

    public function test_newlines_cannot_inject_env_lines(): void
    {
        $this->expectException(\RuntimeException::class);
        Installer::envValue("x\nAPP_DEBUG=true");
    }

    public function test_installed_state_and_token(): void
    {
        $installer = new Installer($this->dir);
        $this->assertFalse($installer->installed());

        $installer->ensureToken();
        $token = trim(file_get_contents($installer->tokenPath()));
        $this->assertMatchesRegularExpression('/^[A-F0-9]{12}$/', $token);
        $this->assertTrue($installer->checkToken(strtolower($token)), 'nu contează majusculele/spațiile');
        $this->assertFalse($installer->checkToken('000000000000'));
        $installer->ensureToken();
        $this->assertSame($token, trim(file_get_contents($installer->tokenPath())), 'codul nu se schimbă la reîncărcare');

        // instalare web începută: .env cu cheie, dar se poate relua
        $installer->markInProgress();
        $installer->writeEnv(['APP_KEY' => Installer::generateKey()]);
        $this->assertFalse($installer->installed());

        // instalare manuală (fără marcajul de progres) = instalată → instalatorul se blochează
        unlink($this->dir.'/storage/app/install-in-progress');
        $this->assertTrue($installer->installed());
    }

    public function test_ini_sizes(): void
    {
        $this->assertSame(64 * 1024 * 1024, Installer::bytes('64M'));
        $this->assertSame(2 * 1024 ** 3, Installer::bytes('2G'));
        $this->assertSame(-1, Installer::bytes('-1'));
    }
}
