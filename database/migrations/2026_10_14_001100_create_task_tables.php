<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('task_templates', function (Blueprint $table) {
            $table->id();
            $table->string('key', 80)->unique();
            $table->string('title_ro', 200);
            $table->string('title_hu', 200);
            $table->text('description_ro')->nullable();
            $table->text('description_hu')->nullable();
            $table->string('phase', 20)->default('pre_event'); // pre_event | post_event
            // Zile relative la data evenimentului: -30 = cu 30 zile înainte, +2 = la 2 zile după.
            $table->integer('relative_days');
            $table->string('responsible_role', 50)->nullable();
            $table->string('priority', 20)->default('normal'); // low | normal | high | critical
            $table->boolean('active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('tasks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('task_template_id')->nullable()->constrained()->nullOnDelete();
            $table->string('title_ro', 200);
            $table->string('title_hu', 200)->nullable();
            $table->text('description')->nullable();
            $table->string('phase', 20)->default('general'); // pre_event | post_event | general
            $table->date('due_date')->nullable()->index();
            $table->string('responsible_role', 50)->nullable();
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->string('priority', 20)->default('normal');
            // open | in_progress | done | cancelled
            $table->string('status', 20)->default('open')->index();
            $table->timestamp('completed_at')->nullable();
            $table->foreignId('completed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reminder_sent_at')->nullable();
            $table->timestamps();
            $table->unique(['event_id', 'task_template_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tasks');
        Schema::dropIfExists('task_templates');
    }
};
