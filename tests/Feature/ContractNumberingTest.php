<?php

namespace Tests\Feature;

use App\Models\Contract;
use App\Services\Documents\DocumentNumbering;
use App\Support\Settings;
use Database\Seeders\DocumentTemplateSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** Contracte existente încărcate + numerotare în continuarea registrului organizatorului. */
class ContractNumberingTest extends TestCase
{
    use RefreshDatabase;

    public function test_default_format_matches_the_old_numbering(): void
    {
        $n = app(DocumentNumbering::class);
        $year = now()->year;
        $this->assertSame("AM-C-{$year}-0001", $n->format('contract', $n->peek('contract')));
        $this->assertSame(125, $n->parse('contract', "AM-C-{$year}-0125"));
        $this->assertNull($n->parse('contract', 'altceva'));
    }

    public function test_generated_contracts_continue_the_existing_register(): void
    {
        Storage::fake('private');
        $this->seed(DocumentTemplateSeeder::class);
        $admin = $this->admin();
        $year = now()->year;

        // registrul pe hârtie a ajuns la 157/an → următorul 158
        $this->actingAs($admin)->put(route('crm.settings.update'), $this->settingsForm([
            'documents_contract_format' => '{nr}/{an}', 'documents_contract_next' => 158,
        ]))->assertSessionHasNoErrors();

        $p = $this->approvedProducer();
        $this->post(route('crm.producers.contract', $p))->assertSessionHas('success');
        $this->assertSame("158/{$year}", Contract::latest('id')->value('number'));
        $this->post(route('crm.producers.contract', $this->approvedProducer()))->assertSessionHas('success');
        $this->assertSame("159/{$year}", Contract::latest('id')->value('number'));
        $this->get(route('crm.settings.index'))->assertSee("160/{$year}");
    }

    public function test_existing_contract_is_uploaded_and_moves_the_counter(): void
    {
        Storage::fake('private');
        $this->seed(DocumentTemplateSeeder::class);
        Settings::setMany(['documents.contract_format' => '{nr}/{an}']);
        $year = now()->year;
        $p = $this->approvedProducer();
        $this->actingAs($this->admin('organizer'));

        $this->post(route('crm.producers.contracts.upload', $p), [
            'number' => "212/{$year}", 'signed_at' => now()->subMonths(2)->toDateString(), 'status' => 'signed',
            'file' => UploadedFile::fake()->create('contract.pdf', 120, 'application/pdf'),
        ])->assertSessionHasNoErrors()->assertSessionHas('success');

        $c = Contract::where('number', "212/{$year}")->firstOrFail();
        $this->assertSame('signed', $c->status);
        $this->assertNotNull($c->signed_path);
        Storage::disk('private')->assertExists($c->path);
        $this->get(route('files.contract', $c))->assertOk();
        $this->assertSame(213, app(DocumentNumbering::class)->peek('contract'), 'numerotarea continuă după contractul încărcat');

        // număr duplicat refuzat
        $this->post(route('crm.producers.contracts.upload', $p), [
            'number' => "212/{$year}", 'status' => 'signed', 'file' => UploadedFile::fake()->create('c.pdf', 10, 'application/pdf'),
        ])->assertSessionHasErrors('number');

        // un număr vechi (mai mic) nu dă numărătoarea înapoi; un număr în alt format e acceptat așa cum e
        $this->post(route('crm.producers.contracts.upload', $p), ['number' => "15/{$year}", 'status' => 'signed', 'file' => UploadedFile::fake()->create('c.pdf', 10, 'application/pdf')]);
        $this->post(route('crm.producers.contracts.upload', $p), ['number' => 'C-VECHI-7', 'status' => 'signed', 'file' => UploadedFile::fake()->create('c.pdf', 10, 'application/pdf')]);
        $this->assertSame(213, app(DocumentNumbering::class)->peek('contract'));
        $this->assertSame(3, $p->contracts()->count());
    }

    protected function settingsForm(array $over): array
    {
        return array_merge([
            'reservations_hold_minutes' => 15, 'contracts_number_prefix' => 'AM',
            'documents_contract_format' => '{prefix}-C-{an}-{nr:4}', 'documents_annex_format' => '{prefix}-A-{an}-{nr:4}',
            'documents_reset_yearly' => '1',
        ], $over);
    }
}
