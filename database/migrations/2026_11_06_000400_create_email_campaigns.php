<?php

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Campanii e-mail din CRM: campania (texte RO/HU, public țintă), destinatarii (instantaneu la pornire,
 * cu stare per adresă) și renunțarea producătorilor la campanii. Permisiunea campaigns.manage.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('email_campaigns')) {
            Schema::create('email_campaigns', function (Blueprint $table) {
                $table->id();
                $table->string('name', 150);
                $table->string('subject_ro', 200);
                $table->string('subject_hu', 200);
                $table->text('body_ro');
                $table->text('body_hu');
                $table->string('audience', 40);
                $table->foreignId('event_id')->nullable()->constrained()->nullOnDelete();
                // draft | sending | paused | sent | cancelled
                $table->string('status', 20)->default('draft')->index();
                $table->unsignedInteger('recipients_count')->default(0);
                $table->unsignedInteger('sent_count')->default(0);
                $table->unsignedInteger('failed_count')->default(0);
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('started_at')->nullable();
                $table->timestamp('finished_at')->nullable();
                $table->timestamps();
            });
        }
        if (! Schema::hasTable('email_campaign_recipients')) {
            Schema::create('email_campaign_recipients', function (Blueprint $table) {
                $table->id();
                $table->foreignId('email_campaign_id')->constrained()->cascadeOnDelete();
                $table->string('email');
                $table->string('name', 200)->nullable();
                $table->char('language', 2)->default('ro');
                $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
                $table->foreignId('producer_id')->nullable()->constrained()->nullOnDelete();
                $table->foreignId('newsletter_subscriber_id')->nullable()->constrained()->nullOnDelete();
                // queued | sent | failed
                $table->string('status', 20)->default('queued');
                $table->text('error')->nullable();
                $table->unsignedTinyInteger('attempts')->default(0);
                $table->timestamp('sent_at')->nullable();
                $table->timestamp('unsubscribed_at')->nullable();
                $table->timestamps();
                $table->unique(['email_campaign_id', 'email']);
                $table->index(['email_campaign_id', 'status']);
            });
        }
        if (! Schema::hasColumn('producers', 'campaigns_opt_out')) {
            Schema::table('producers', function (Blueprint $table) {
                $table->boolean('campaigns_opt_out')->default(false);
            });
        }

        $permission = Permission::firstOrCreate(['name' => 'campaigns.manage'], ['group' => 'content', 'label_ro' => 'Campanii e-mail', 'label_hu' => 'E-mail kampányok']);
        foreach (['organizer', 'admin', 'super_admin'] as $name) {
            Role::where('name', $name)->first()?->permissions()->syncWithoutDetaching([$permission->id]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('email_campaign_recipients');
        Schema::dropIfExists('email_campaigns');
        if (Schema::hasColumn('producers', 'campaigns_opt_out')) {
            Schema::table('producers', function (Blueprint $table) {
                $table->dropColumn('campaigns_opt_out');
            });
        }
    }
};
