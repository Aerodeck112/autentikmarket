<?php

namespace Tests\Feature;

use App\Enums\UpdateStatus;
use App\Models\ApplicationUpdate;
use App\Services\Updates\ApplicationRuntime;
use App\Services\Updates\SignatureVerifier;
use App\Services\Updates\UpdateManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Tests\Support\FakeRuntime;
use Tests\Support\PackageBuilder;
use Tests\TestCase;
use ZipArchive;

class UpdateManagerTest extends TestCase
{
    use RefreshDatabase;

    protected string $root;

    protected string $work;

    protected FakeRuntime $runtime;

    protected string $storage;

    protected function setUp(): void
    {
        parent::setUp();
        // Aplicație „țintă” izolată: testele NU ating codul real.
        $this->root = sys_get_temp_dir().'/am-update-root-'.uniqid();
        $this->work = sys_get_temp_dir().'/am-update-work-'.uniqid();
        File::ensureDirectoryExists($this->root.'/app/Models');
        File::ensureDirectoryExists($this->root.'/routes');
        File::ensureDirectoryExists($this->work);
        file_put_contents($this->root.'/VERSION', "1.0.0\n");
        file_put_contents($this->root.'/.env', "APP_KEY=secret-production-key\nDB_PASSWORD=secret\n");
        file_put_contents($this->root.'/app/Models/Old.php', "<?php // v1.0.0\n");
        file_put_contents($this->root.'/app/Models/Obsolete.php', "<?php // va fi șters\n");
        file_put_contents($this->root.'/routes/web.php', "<?php // rute v1\n");
        config(['update.target_path' => $this->root, 'update.backup_database' => true]);
        // Storage izolat: backup-urile și pachetele de test nu ajung în storage-ul real.
        $this->storage = sys_get_temp_dir().'/am-update-storage-'.uniqid();
        File::ensureDirectoryExists($this->storage.'/framework');
        File::ensureDirectoryExists($this->storage.'/app/private');
        $this->app->useStoragePath($this->storage);

        $this->runtime = new FakeRuntime;
        $this->app->instance(ApplicationRuntime::class, $this->runtime);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->root);
        File::deleteDirectory($this->work);
        File::deleteDirectory($this->storage);
        parent::tearDown();
    }

    protected function manager(): UpdateManager
    {
        return app(UpdateManager::class);
    }

    protected function receive(string $zip, string $name = 'autentikmarket-update-1.1.0.zip'): ApplicationUpdate
    {
        return $this->manager()->receive($zip, $name, $this->admin());
    }

    // ---------------------------------------------------------------- VALIDARE

    public function test_invalid_zip_is_rejected(): void
    {
        $fake = $this->work.'/not-a-zip.zip';
        file_put_contents($fake, 'this is not a zip archive');

        $u = $this->receive($fake);

        $this->assertSame(UpdateStatus::Rejected, $u->status);
        $this->assertStringContainsString('ZIP', $u->message);
        $this->assertSame('1.0.0', trim(file_get_contents($this->root.'/VERSION')));
    }

    public function test_wrong_extension_is_rejected(): void
    {
        $zip = (new PackageBuilder)->build($this->work);
        $u = $this->receive($zip, 'update.tar.gz');
        $this->assertSame(UpdateStatus::Rejected, $u->status);
    }

    public function test_zip_without_manifest_is_rejected(): void
    {
        $path = $this->work.'/no-manifest.zip';
        $zip = new ZipArchive;
        $zip->open($path, ZipArchive::CREATE);
        $zip->addFromString('files/VERSION', '1.1.0');
        $zip->close();

        $u = $this->receive($path);
        $this->assertSame(UpdateStatus::Rejected, $u->status);
        $this->assertStringContainsString('update.json', $u->message);
    }

    public function test_incompatible_version_is_rejected(): void
    {
        // necesită minimum 1.3.0, instalată 1.0.0
        $u = $this->receive((new PackageBuilder('1.4.0', '1.3.0'))->build($this->work));
        $this->assertSame(UpdateStatus::Rejected, $u->status);
        $this->assertStringContainsString('incompatibil', strtolower($u->message));
    }

    public function test_older_or_same_version_is_rejected(): void
    {
        $u = $this->receive((new PackageBuilder('1.0.0', '0.9.0'))->build($this->work));
        $this->assertSame(UpdateStatus::Rejected, $u->status);
    }

    public function test_wrong_application_id_is_rejected(): void
    {
        $b = new PackageBuilder;
        $b->manifest['application'] = 'other-app';
        $u = $this->receive($b->build($this->work));
        $this->assertSame(UpdateStatus::Rejected, $u->status);
    }

    public function test_tampered_file_checksum_is_rejected(): void
    {
        $b = (new PackageBuilder)->file('app/Models/Old.php', "<?php // v1.1\n");
        $zip = $b->build($this->work);
        // modificăm conținutul după calcularea hash-urilor
        $za = new ZipArchive;
        $za->open($zip);
        $za->addFromString('files/app/Models/Old.php', "<?php // COD MALIȚIOS\n");
        $za->close();

        $u = $this->receive($zip);
        $this->assertSame(UpdateStatus::Rejected, $u->status);
        $this->assertStringContainsString('SHA-256', $u->message);
    }

    public function test_tampered_manifest_checksum_is_rejected(): void
    {
        $b = (new PackageBuilder)->file('app/Models/Old.php', 'x');
        $b->manifest['checksum'] = str_repeat('a', 64);
        $b->recomputeChecksum = false;
        $u = $this->receive($b->build($this->work));
        $this->assertSame(UpdateStatus::Rejected, $u->status);
    }

    public function test_version_file_must_match_manifest(): void
    {
        $b = new PackageBuilder('1.1.0');
        $b->files['VERSION'] = "9.9.9\n";
        $u = $this->receive($b->build($this->work));
        $this->assertSame(UpdateStatus::Rejected, $u->status);
    }

    // ---------------------------------------------------------------- SECURITATE

    public function test_zip_slip_path_traversal_is_blocked(): void
    {
        foreach (['../../evil.php', 'files/../../evil.php', 'files/app/../../../evil.php', '/etc/passwd', 'files/C:/evil.php'] as $entry) {
            $zip = (new PackageBuilder)->raw($entry, '<?php system($_GET["c"]);')->build($this->work);
            $u = $this->receive($zip);

            $this->assertSame(UpdateStatus::Rejected, $u->status, "Intrarea {$entry} trebuia respinsă.");
            $this->assertFileDoesNotExist(dirname($this->root).'/evil.php');
            $this->assertFileDoesNotExist($this->root.'/evil.php');
        }
    }

    public function test_env_cannot_be_overwritten_by_package(): void
    {
        $zip = (new PackageBuilder)->file('.env', "APP_KEY=hacked\n")->build($this->work);
        $u = $this->receive($zip);

        $this->assertSame(UpdateStatus::Rejected, $u->status);
        $this->assertStringContainsString('protejat', $u->message);
        $this->assertSame("APP_KEY=secret-production-key\nDB_PASSWORD=secret\n", file_get_contents($this->root.'/.env'));
    }

    public function test_protected_and_non_whitelisted_paths_are_rejected(): void
    {
        foreach (['storage/app/private/x.pdf', 'public/storage/x.jpg', 'public/uploads/x.jpg', '.htaccess', 'random/file.php', 'public/build/shell.php'] as $path) {
            $u = $this->receive((new PackageBuilder)->file($path, 'x')->build($this->work));
            $this->assertSame(UpdateStatus::Rejected, $u->status, "Calea {$path} trebuia respinsă.");
        }
    }

    public function test_symlink_entries_are_rejected(): void
    {
        $zip = (new PackageBuilder)->file('app/link.php', '/etc/passwd')->build($this->work);
        $za = new ZipArchive;
        $za->open($zip);
        $za->setExternalAttributesName('files/app/link.php', ZipArchive::OPSYS_UNIX, (0120777 << 16));
        $za->close();

        $u = $this->receive($zip);
        $this->assertSame(UpdateStatus::Rejected, $u->status);
        $this->assertStringContainsString('Symlink', $u->message);
    }

    public function test_invalid_signature_is_rejected_and_required_signature_enforced(): void
    {
        $pair = sodium_crypto_sign_keypair();
        config(['update.public_key' => base64_encode(sodium_crypto_sign_publickey($pair))]);

        $bad = (new PackageBuilder)->raw('update.sig', base64_encode(str_repeat('x', 64)))->build($this->work);
        $this->assertSame(UpdateStatus::Rejected, $this->receive($bad)->status);

        config(['update.require_signature' => true]);
        $unsigned = (new PackageBuilder)->build($this->work);
        $this->assertSame(UpdateStatus::Rejected, $this->receive($unsigned)->status);
    }

    public function test_valid_signature_is_accepted(): void
    {
        $pair = sodium_crypto_sign_keypair();
        config(['update.public_key' => base64_encode(sodium_crypto_sign_publickey($pair)), 'update.require_signature' => true]);
        $b = new PackageBuilder;
        $zip = $b->build($this->work);
        $za = new ZipArchive;
        $za->open($zip);
        $json = $za->getFromName('update.json');
        $za->addFromString('update.sig', SignatureVerifier::sign($json, base64_encode(sodium_crypto_sign_secretkey($pair))));
        $za->close();

        $u = $this->receive($zip);
        $this->assertSame(UpdateStatus::Validated, $u->status, (string) $u->message);
        $this->assertTrue($u->signature_valid);
    }

    // ---------------------------------------------------------------- INSTALARE

    public function test_full_install_creates_backup_first_preserves_env_and_updates_version(): void
    {
        $b = (new PackageBuilder('1.1.0', '1.0.0'))
            ->file('app/Models/Old.php', "<?php // v1.1.0\n")
            ->file('app/Models/NewModel.php', "<?php // nou în 1.1.0\n");
        $b->delete = ['app/Models/Obsolete.php'];
        $u = $this->receive($b->build($this->work));
        $this->assertSame(UpdateStatus::Validated, $u->status, (string) $u->message);

        $steps = [];
        $result = $this->manager()->runAll($u, $this->admin(), function ($r) use (&$steps) {
            $steps[] = $r['step'];
        });

        $this->assertTrue($result['ok'], $result['message']);
        $this->assertSame(UpdateManager::STEPS, $steps);
        $u->refresh();
        $this->assertSame(UpdateStatus::Completed, $u->status);

        // backup creat ÎNAINTE de instalare: conține versiunea veche a fișierelor
        $backup = $u->backup;
        $this->assertNotNull($backup);
        $this->assertFileExists($backup->files_archive);
        $this->assertTrue($backup->database_included);
        $za = new ZipArchive;
        $za->open($backup->files_archive);
        $this->assertSame("<?php // v1.0.0\n", $za->getFromName('app/Models/Old.php'));
        $this->assertSame("1.0.0\n", $za->getFromName('VERSION'));
        $this->assertSame("<?php // va fi șters\n", $za->getFromName('app/Models/Obsolete.php'));
        $za->close();
        $this->assertContains('app/Models/NewModel.php', $backup->new_files);
        $this->assertStringStartsWith(storage_path('app/private/backups/updates'), $backup->directory);

        // fișiere noi instalate, șterse cele declarate, versiunea actualizată
        $this->assertSame('1.1.0', trim(file_get_contents($this->root.'/VERSION')));
        $this->assertSame("<?php // v1.1.0\n", file_get_contents($this->root.'/app/Models/Old.php'));
        $this->assertFileExists($this->root.'/app/Models/NewModel.php');
        $this->assertFileDoesNotExist($this->root.'/app/Models/Obsolete.php');

        // .env NEATINS
        $this->assertSame("APP_KEY=secret-production-key\nDB_PASSWORD=secret\n", file_get_contents($this->root.'/.env'));

        // ordinea operațiilor: maintenance → migrate → optimize → up
        $this->assertSame(['down', 'clear', 'migrate', 'clear', 'optimize', 'up'], $this->runtime->calls);
        $this->assertFalse($this->runtime->down);
        $this->assertDatabaseHas('audit_logs', ['action' => 'update.installed']);
    }

    public function test_backup_is_created_before_any_file_is_written(): void
    {
        $u = $this->receive((new PackageBuilder)->file('app/Models/Old.php', 'NEW')->build($this->work));
        $m = $this->manager();
        $admin = $this->admin();

        $this->assertTrue($m->runStep($u, 'preflight', $admin)['ok']);
        $this->assertSame("<?php // v1.0.0\n", file_get_contents($this->root.'/app/Models/Old.php'));
        $this->assertTrue($m->runStep($u->fresh(), 'backup', $admin)['ok']);
        $this->assertNotNull($u->fresh()->backup);
        $this->assertSame("<?php // v1.0.0\n", file_get_contents($this->root.'/app/Models/Old.php'), 'Backup-ul nu modifică fișierele.');
        $this->assertSame(UpdateStatus::BackupCreated, $u->fresh()->status);
    }

    public function test_steps_cannot_be_skipped(): void
    {
        $u = $this->receive((new PackageBuilder)->build($this->work));
        $r = $this->manager()->runStep($u, 'install', $this->admin());

        $this->assertFalse($r['ok']);
        $this->assertSame("1.0.0\n", file_get_contents($this->root.'/VERSION'));
    }

    public function test_failed_migration_rolls_back_files_and_keeps_app_online(): void
    {
        $this->runtime->failMigrate = true;
        $b = (new PackageBuilder)->file('app/Models/Old.php', "<?php // v1.1.0\n")->file('app/Models/Brand.php', 'new');
        $u = $this->receive($b->build($this->work));

        $result = $this->manager()->runAll($u, $this->admin());

        $this->assertFalse($result['ok']);
        $u->refresh();
        $this->assertSame(UpdateStatus::RolledBack, $u->status);
        $this->assertSame("1.0.0\n", file_get_contents($this->root.'/VERSION'));
        $this->assertSame("<?php // v1.0.0\n", file_get_contents($this->root.'/app/Models/Old.php'));
        $this->assertFileDoesNotExist($this->root.'/app/Models/Brand.php');
        $this->assertFalse($this->runtime->down, 'Aplicația trebuie să revină online după eșec.');
        $this->assertDatabaseHas('audit_logs', ['action' => 'update.failed']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'update.rollback']);
    }

    public function test_destructive_migrations_require_confirmation(): void
    {
        $migration = "<?php\nreturn new class extends Illuminate\\Database\\Migrations\\Migration {\n public function up(): void { Schema::dropIfExists('producers'); }\n public function down(): void {}\n};\n";
        $u = $this->receive((new PackageBuilder)->file('database/migrations/2026_11_01_000000_drop.php', $migration)->build($this->work));
        $this->assertSame(UpdateStatus::Validated, $u->status);
        $this->assertNotEmpty($u->destructive_migrations['files']);

        $r = $this->manager()->runStep($u, 'preflight', $this->admin());
        $this->assertFalse($r['ok']);
        $this->assertStringContainsString('distructive', $r['message']);
    }

    public function test_http_upload_and_step_endpoints_for_super_admin(): void
    {
        $admin = $this->admin();
        $zip = (new PackageBuilder)->file('routes/web.php', "<?php // rute v1.1\n")->build($this->work);
        $file = new UploadedFile($zip, 'autentikmarket-update-1.1.0.zip', 'application/zip', null, true);

        $this->actingAs($admin)->post(route('crm.updates.upload'), ['package' => $file])->assertRedirect();
        $u = ApplicationUpdate::latest('id')->first();
        $this->assertSame(UpdateStatus::Validated, $u->status, (string) $u->message);
        $this->actingAs($admin)->get(route('crm.updates.show', $u))->assertOk()->assertSee('INSTALARE ACTUALIZARE');

        foreach (UpdateManager::STEPS as $step) {
            $this->actingAs($admin)->postJson(route('crm.updates.step', [$u, $step]))->assertOk()->assertJson(['ok' => true]);
            $u->refresh();
        }
        $this->assertSame(UpdateStatus::Completed, $u->status);
        $this->assertSame("<?php // rute v1.1\n", file_get_contents($this->root.'/routes/web.php'));

        $this->actingAs($this->admin('organizer'))->postJson(route('crm.updates.step', [$u, 'preflight']))->assertForbidden();
    }
}
