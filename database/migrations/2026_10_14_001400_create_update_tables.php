<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('application_updates', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('version', 30)->nullable();
            $table->string('previous_version', 30)->nullable();
            // uploaded | validated | backup_created | installing | completed | failed | rolled_back | rejected
            $table->string('status', 20)->index();
            $table->string('current_step', 30)->nullable();
            $table->string('package_path')->nullable();
            $table->string('original_name')->nullable();
            $table->char('package_sha256', 64)->nullable();
            $table->unsignedBigInteger('package_size')->nullable();
            $table->json('manifest')->nullable();
            $table->longText('release_notes')->nullable();
            $table->boolean('signature_valid')->nullable();
            $table->json('destructive_migrations')->nullable();
            $table->json('log')->nullable();
            $table->text('message')->nullable();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('installed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->unsignedInteger('duration_ms')->nullable();
            $table->timestamps();
        });

        Schema::create('update_backups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('application_update_id')->nullable()->constrained()->nullOnDelete();
            $table->string('version', 30);
            $table->string('directory');
            $table->string('files_archive')->nullable();
            $table->string('database_dump')->nullable();
            $table->boolean('database_included')->default(false);
            $table->json('new_files')->nullable();
            $table->unsignedBigInteger('size_bytes')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('update_backups');
        Schema::dropIfExists('application_updates');
    }
};
