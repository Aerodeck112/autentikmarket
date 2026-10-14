<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExportAuditTest extends TestCase
{
    use RefreshDatabase;

    public function test_every_export_is_written_to_the_audit_log(): void
    {
        $admin = $this->admin();
        $this->approvedProducer();

        $this->actingAs($admin)->get(route('crm.producers.export', ['format' => 'xlsx']))->assertOk();

        $log = AuditLog::where('action', 'export.downloaded')->latest('id')->firstOrFail();
        $this->assertSame($admin->id, $log->user_id);
        $this->assertStringEndsWith('.xlsx', $log->new_values['file'] ?? '');
    }
}
