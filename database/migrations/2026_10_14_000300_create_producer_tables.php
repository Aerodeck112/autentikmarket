<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('producer_categories', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 80)->unique();
            $table->string('name_ro', 120);
            $table->string('name_hu', 120);
            $table->string('icon', 40)->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        Schema::create('producers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->unique()->constrained()->nullOnDelete();
            $table->foreignId('producer_category_id')->nullable()->constrained()->nullOnDelete();
            $table->string('brand_name', 150);
            $table->string('slug', 170)->unique();
            $table->string('legal_name', 200)->nullable();
            $table->string('cui', 30)->nullable()->index();
            $table->string('trade_register_no', 50)->nullable();
            $table->string('representative_name', 150)->nullable();
            $table->string('address', 255)->nullable();
            $table->string('city', 120)->nullable()->index();
            $table->string('county', 120)->nullable();
            $table->string('phone', 40)->nullable();
            $table->string('email')->nullable()->index();
            $table->string('website')->nullable();
            $table->string('facebook')->nullable();
            $table->string('instagram')->nullable();
            $table->string('iban', 40)->nullable();
            $table->string('bank_name', 120)->nullable();
            $table->char('preferred_language', 2)->default('ro')->index();
            $table->text('description_ro')->nullable();
            $table->text('description_hu')->nullable();
            $table->text('story_ro')->nullable();
            $table->text('story_hu')->nullable();
            $table->text('products_ro')->nullable();
            $table->text('products_hu')->nullable();
            $table->string('logo_path')->nullable();
            $table->string('cover_path')->nullable();
            $table->string('shop_url')->nullable();
            // new | incomplete | documents_submitted | in_review | approved | rejected
            $table->string('status', 30)->default('new')->index();
            $table->timestamp('status_changed_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('rejection_reason')->nullable();
            $table->text('internal_notes')->nullable();
            $table->string('qr_token', 64)->unique();
            $table->boolean('is_public')->default(true);
            $table->timestamps();
            $table->index(['status', 'preferred_language']);
        });

        Schema::create('producer_status_history', function (Blueprint $table) {
            $table->id();
            $table->foreignId('producer_id')->constrained()->cascadeOnDelete();
            $table->string('from_status', 30)->nullable();
            $table->string('to_status', 30);
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->text('note')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });

        Schema::create('producer_document_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('producer_id')->constrained()->cascadeOnDelete();
            $table->string('document_type', 40);
            $table->text('note')->nullable();
            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('fulfilled_at')->nullable();
            $table->timestamps();
        });

        Schema::create('producer_documents', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('producer_id')->constrained()->cascadeOnDelete();
            // company | authorization | dsvsa | certification | photo | other
            $table->string('type', 40)->index();
            $table->string('title', 200);
            $table->string('path');
            $table->string('original_name', 255);
            $table->string('mime', 100);
            $table->unsignedBigInteger('size');
            // pending | accepted | rejected
            $table->string('status', 20)->default('pending')->index();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('review_note')->nullable();
            $table->date('expires_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('producer_documents');
        Schema::dropIfExists('producer_document_requests');
        Schema::dropIfExists('producer_status_history');
        Schema::dropIfExists('producers');
        Schema::dropIfExists('producer_categories');
    }
};
