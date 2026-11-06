<?php

namespace Tests\Feature;

use App\Models\NotificationLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** Contul meu, editarea oricărui cont de către super admin, fișiere încărcate de organizator pentru producător. */
class AccountEditingTest extends TestCase
{
    use RefreshDatabase;

    public function test_team_member_edits_own_account_and_password(): void
    {
        $me = $this->admin('organizer');
        $me->update(['password' => Hash::make('Veche12345')]);

        $this->actingAs($me)->get(route('crm.me'))->assertOk()->assertSee('Contul meu');
        $this->put(route('crm.me.update'), [
            'first_name' => 'Maria', 'last_name' => 'Pop', 'email' => 'Maria@Example.test', 'phone' => '0740 111 000', 'preferred_language' => 'hu',
        ])->assertSessionHas('success');
        $me->refresh();
        $this->assertSame(['Maria', 'maria@example.test', '40740111000', 'hu'], [$me->first_name, $me->email, $me->phone_key, $me->preferred_language->value]);

        $this->put(route('crm.me.password'), ['current_password' => 'gresit', 'password' => 'NouaParola123', 'password_confirmation' => 'NouaParola123'])->assertSessionHasErrors('current_password');
        $this->put(route('crm.me.password'), ['current_password' => 'Veche12345', 'password' => 'NouaParola123', 'password_confirmation' => 'NouaParola123'])->assertSessionHas('success');
        $this->assertTrue(Hash::check('NouaParola123', $me->fresh()->password));
    }

    public function test_super_admin_edits_any_account_and_sets_a_password(): void
    {
        $buyer = $this->visitor();
        $producer = $this->approvedProducer();
        $admin = $this->admin();

        $this->actingAs($admin)->get(route('crm.users.index', ['role' => 'all', 'q' => $producer->user->email]))->assertOk()->assertSee($producer->brand_name);
        $this->get(route('crm.users.edit', $producer->user))->assertOk()->assertSee('Fișa producătorului');

        $this->put(route('crm.users.update', $buyer), [
            'first_name' => 'Ana', 'last_name' => 'Nagy', 'email' => '', 'phone' => '0745 000 111', 'preferred_language' => 'hu', 'is_active' => '1',
            'new_password' => 'Telefon2026', 'new_password_confirmation' => 'Telefon2026',
        ])->assertSessionHasNoErrors()->assertSessionHas('success');
        $buyer->refresh();
        $this->assertNull($buyer->email);
        $this->assertTrue(Hash::check('Telefon2026', $buyer->password));

        // persoana intră acum cu telefonul și parola setată de organizator
        auth()->logout();
        $this->post(lroute('login.store', [], 'ro'), ['email' => '0745000111', 'password' => 'Telefon2026'])->assertRedirect();
        $this->assertAuthenticatedAs($buyer);
    }

    public function test_password_link_and_guards(): void
    {
        $producer = $this->approvedProducer('hu');
        $this->actingAs($this->admin())->post(route('crm.users.password-link', $producer->user))->assertSessionHas('success');
        $this->assertSame(1, NotificationLog::where('template_key', 'PRODUCER_INVITE')->count());

        // un admin (nu super admin) nu poate edita un super admin; nimeni nu își dezactivează singur contul
        $super = $this->admin();
        $admin = $this->admin('admin');
        $this->actingAs($admin)->get(route('crm.users.edit', $super))->assertForbidden();
        $this->put(route('crm.users.update', $admin), ['first_name' => 'A', 'last_name' => 'B', 'email' => $admin->email, 'preferred_language' => 'ro'])->assertSessionHas('error');
        $this->assertTrue($admin->fresh()->is_active);

        // un organizator fără users.manage nu are acces
        $this->actingAs($this->admin('organizer'))->get(route('crm.users.edit', $producer->user))->assertForbidden();
    }

    public function test_buyer_without_email_can_edit_profile_with_phone(): void
    {
        $buyer = $this->visitor();
        $buyer->forceFill(['email' => null, 'phone' => '0740123123'])->save();
        $this->actingAs($buyer)->get(route('account.profile'))->assertOk()->assertDontSee('Te rugăm să confirmi adresa de e-mail');
        $this->put(route('account.profile.update'), ['first_name' => 'Ion', 'last_name' => 'Pop', 'email' => '', 'phone' => '0740123123', 'preferred_language' => 'ro'])
            ->assertSessionHasNoErrors();
        $this->put(route('account.profile.update'), ['first_name' => 'Ion', 'last_name' => 'Pop', 'email' => '', 'phone' => '', 'preferred_language' => 'ro'])
            ->assertSessionHasErrors('email');
    }

    public function test_organizer_uploads_photos_and_documents_into_producer_account(): void
    {
        Storage::fake('public');
        Storage::fake('private');
        $producer = $this->approvedProducer();
        $this->actingAs($this->admin('organizer'));

        $this->post(route('crm.producers.media.store', $producer), ['kind' => 'logo', 'file' => UploadedFile::fake()->image('logo.jpg', 400, 400)])->assertSessionHas('success');
        $this->post(route('crm.producers.media.store', $producer), ['kind' => 'gallery', 'file' => UploadedFile::fake()->image('stand.jpg', 800, 600)])->assertSessionHas('success');
        $producer->refresh();
        $this->assertNotNull($producer->logo_path);
        $this->assertCount(1, $producer->gallery);

        $this->post(route('crm.producers.documents.store', $producer), ['type' => 'other', 'title' => 'Certificat', 'file' => UploadedFile::fake()->create('cert.pdf', 100, 'application/pdf')])->assertSessionHas('success');
        $doc = $producer->documents()->firstOrFail();
        $this->assertSame('accepted', $doc->status);

        // apare și în contul producătorului
        $this->actingAs($producer->user)->get(route('producer.documents'))->assertOk()->assertSee('Certificat');

        $this->actingAs($this->admin('organizer'));
        $this->get(route('crm.producers.show', $producer))->assertOk()->assertSee('Galerie (1/12)', false);
        $this->delete(route('crm.producers.media.destroy', [$producer, $producer->gallery->first()->id]))->assertSessionHas('success');
        $this->delete(route('crm.producers.media.destroy', [$producer, 'logo']))->assertSessionHas('success');
        $this->delete(route('crm.producers.documents.destroy', [$producer, $doc]))->assertSessionHas('success');
        $producer->refresh();
        $this->assertNull($producer->logo_path);
        $this->assertCount(0, $producer->gallery);
        $this->assertSame(0, $producer->documents()->count());

        // izolare: media altui producător nu poate fi ștearsă prin acest producător
        $other = $this->approvedProducer();
        $this->post(route('crm.producers.media.store', $other), ['kind' => 'gallery', 'file' => UploadedFile::fake()->image('x.jpg', 400, 400)]);
        $this->delete(route('crm.producers.media.destroy', [$producer, $other->gallery()->first()->id]))->assertForbidden();
    }
}
