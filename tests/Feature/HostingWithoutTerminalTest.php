<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

/** Funcții pentru găzduirile cPanel fără Terminal. */
class HostingWithoutTerminalTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_images_are_served_without_storage_link(): void
    {
        $dir = storage_path('app/public/test-fallback');
        File::ensureDirectoryExists($dir);
        file_put_contents($dir.'/a.png', base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNkYAAAAAYAAjCB0C8AAAAASUVORK5CYII='));
        file_put_contents($dir.'/x.php', '<?php echo 1;');
        file_put_contents(storage_path('app/private/secret.png'), 'x');

        try {
            $this->get('/storage/test-fallback/a.png')->assertOk()->assertHeader('Content-Type', 'image/png')->assertHeader('X-Content-Type-Options', 'nosniff');
            $this->get('/storage/test-fallback/x.php')->assertNotFound();
            $this->get('/storage/test-fallback/missing.png')->assertNotFound();
            $this->get('/storage/../private/secret.png')->assertNotFound();
            $this->get('/storage/test-fallback/..%2F..%2Fprivate%2Fsecret.png')->assertNotFound();
        } finally {
            File::deleteDirectory($dir);
            @unlink(storage_path('app/private/secret.png'));
        }
    }

    public function test_config_can_be_reloaded_from_the_crm(): void
    {
        Artisan::shouldReceive('call')->once()->with('optimize:clear');
        Artisan::shouldReceive('call')->once()->with('optimize');

        $this->actingAs($this->admin())->post(route('crm.system.refresh'))->assertRedirect()->assertSessionHas('success');
    }

    public function test_config_reload_requires_the_update_permission(): void
    {
        Artisan::shouldReceive('call')->never();

        $this->actingAs($this->admin('staff'))->post(route('crm.system.refresh'))->assertForbidden();
    }
}
