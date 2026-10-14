<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('media', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->morphs('mediable');
            $table->string('collection', 40)->default('gallery')->index();
            $table->string('disk', 20)->default('public');
            $table->string('path');
            $table->string('thumb_path')->nullable();
            $table->string('mime', 100);
            $table->unsignedBigInteger('size');
            $table->unsignedInteger('width')->nullable();
            $table->unsignedInteger('height')->nullable();
            $table->string('caption_ro', 255)->nullable();
            $table->string('caption_hu', 255)->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('blog_posts', function (Blueprint $table) {
            $table->id();
            $table->string('slug_ro', 190)->unique();
            $table->string('slug_hu', 190)->unique();
            $table->string('title_ro', 255);
            $table->string('title_hu', 255);
            $table->text('excerpt_ro')->nullable();
            $table->text('excerpt_hu')->nullable();
            $table->longText('body_ro')->nullable();
            $table->longText('body_hu')->nullable();
            $table->string('meta_title_ro', 255)->nullable();
            $table->string('meta_title_hu', 255)->nullable();
            $table->string('meta_description_ro', 255)->nullable();
            $table->string('meta_description_hu', 255)->nullable();
            $table->string('cover_path')->nullable();
            $table->foreignId('event_id')->nullable()->constrained()->nullOnDelete();
            // draft | review | published
            $table->string('status', 20)->default('draft')->index();
            $table->string('generated_by', 20)->default('manual'); // manual | template | ai
            $table->timestamp('published_at')->nullable()->index();
            $table->foreignId('author_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('newsletter_subscribers', function (Blueprint $table) {
            $table->id();
            $table->string('email')->unique();
            $table->char('preferred_language', 2)->default('ro')->index();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('token', 64)->unique();
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamp('unsubscribed_at')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->timestamps();
        });

        Schema::create('contact_messages', function (Blueprint $table) {
            $table->id();
            $table->string('name', 150);
            $table->string('email');
            $table->string('phone', 40)->nullable();
            $table->string('subject', 200)->nullable();
            $table->text('message');
            $table->char('language', 2)->default('ro');
            $table->string('ip_address', 45)->nullable();
            $table->timestamp('handled_at')->nullable();
            $table->foreignId('handled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contact_messages');
        Schema::dropIfExists('newsletter_subscribers');
        Schema::dropIfExists('blog_posts');
        Schema::dropIfExists('media');
    }
};
