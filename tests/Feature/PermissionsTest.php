<?php

namespace Tests\Feature;

use App\Models\Permission;
use App\Models\ProducerDocument;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

class PermissionsTest extends TestCase
{
    use RefreshDatabase;

    public function test_producer_a_cannot_see_documents_of_producer_b(): void
    {
        $a = $this->approvedProducer();
        $b = $this->approvedProducer();

        $this->actingAs($b->user)->post(route('producer.documents.store'), [
            'type' => 'dsvsa',
            'file' => UploadedFile::fake()->create('autorizatie.pdf', 50, 'application/pdf'),
        ])->assertRedirect();
        $doc = ProducerDocument::where('producer_id', $b->id)->sole();

        $this->actingAs($b->user)->get(route('files.producer-document', $doc))->assertOk();
        $this->actingAs($a->user)->get(route('files.producer-document', $doc))->assertForbidden();
        $this->actingAs($a->user)->delete(route('producer.documents.destroy', $doc))->assertForbidden();
        $this->actingAs($this->visitor())->get(route('files.producer-document', $doc))->assertForbidden();
        $this->actingAs($this->admin('organizer'))->get(route('files.producer-document', $doc))->assertOk();
        $this->assertFileDoesNotExist(public_path('storage/'.$doc->path));
    }

    public function test_crm_access_is_role_based(): void
    {
        $this->actingAs($this->visitor())->get(route('crm.dashboard'))->assertForbidden();
        $this->actingAs($this->approvedProducer()->user)->get(route('crm.dashboard'))->assertForbidden();
        $this->actingAs($this->admin('staff'))->get(route('crm.dashboard'))->assertForbidden();
        $this->actingAs($this->admin('staff'))->get(route('staff.index'))->assertOk();
        $this->actingAs($this->admin('organizer'))->get(route('crm.dashboard'))->assertOk();
        Auth::logout();
        $this->get(route('crm.dashboard'))->assertRedirect();
    }

    public function test_only_super_admin_or_system_update_can_access_updates(): void
    {
        $this->actingAs($this->admin('organizer'))->get(route('crm.updates.index'))->assertForbidden();
        $this->actingAs($this->admin('admin'))->get(route('crm.updates.index'))->assertForbidden();
        $this->actingAs($this->approvedProducer()->user)->get(route('crm.updates.index'))->assertForbidden();
        $this->actingAs($this->admin('super_admin'))->get(route('crm.updates.index'))->assertOk();

        // Rol explicit cu permisiunea system.update
        $role = Role::create(['name' => 'release_manager', 'label_ro' => 'Release', 'label_hu' => 'Release']);
        $role->permissions()->attach(Permission::whereIn('name', ['crm.access', 'system.update'])->pluck('id'));
        $user = User::factory()->create();
        $user->assignRole('release_manager');
        $this->actingAs($user)->get(route('crm.updates.index'))->assertOk();
    }

    public function test_producer_cannot_modify_other_producers_media(): void
    {
        $a = $this->approvedProducer();
        $b = $this->approvedProducer();
        $media = $b->gallery()->create(['collection' => 'gallery', 'disk' => 'public', 'path' => 'x.jpg', 'mime' => 'image/jpeg', 'size' => 1]);

        $this->actingAs($a->user)->delete(route('producer.media.destroy', $media))->assertForbidden();
    }

    public function test_visitor_cannot_access_producer_area(): void
    {
        $this->actingAs($this->visitor())->get(route('producer.dashboard'))->assertForbidden();
    }
}
