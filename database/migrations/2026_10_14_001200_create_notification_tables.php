<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notification_templates', function (Blueprint $table) {
            $table->id();
            $table->string('key', 80);
            $table->string('channel', 20)->default('mail'); // mail | database
            $table->string('subject_ro', 255);
            $table->string('subject_hu', 255);
            $table->text('body_ro');
            $table->text('body_hu');
            $table->string('description', 255)->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();
            $table->unique(['key', 'channel']);
        });

        // Jurnal unic al tuturor comunicărilor (și inbox in-app pentru canalul database).
        Schema::create('notification_logs', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('template_key', 80)->index();
            $table->string('channel', 20);
            $table->string('notifiable_type', 120);
            $table->unsignedBigInteger('notifiable_id');
            $table->string('recipient_email')->nullable();
            $table->char('language', 2);
            $table->string('subject', 255);
            $table->text('body');
            // queued | processing | sent | failed | skipped
            $table->string('status', 20)->default('queued')->index();
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->text('error')->nullable();
            $table->string('dedupe_key', 191)->nullable()->unique();
            $table->string('related_type', 120)->nullable();
            $table->unsignedBigInteger('related_id')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
            $table->index(['notifiable_type', 'notifiable_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_logs');
        Schema::dropIfExists('notification_templates');
    }
};
