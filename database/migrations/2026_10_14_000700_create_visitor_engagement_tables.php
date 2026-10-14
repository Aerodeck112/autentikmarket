<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // O vizită = un check-in. UNIQUE(event_id, user_id) => imposibil două vizite la același eveniment.
        Schema::create('checkins', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('operator_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('method', 20)->default('qr'); // qr | manual
            $table->timestamp('checked_in_at');
            $table->timestamp('thanked_at')->nullable();
            $table->timestamps();
            $table->unique(['event_id', 'user_id']);
            $table->index('checked_in_at');
        });

        Schema::create('producer_customer_interactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->foreignId('producer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('source', 20); // customer_scan | producer_scan | staff
            $table->timestamp('review_requested_at')->nullable();
            $table->timestamps();
            $table->unique(['event_id', 'producer_id', 'user_id'], 'pci_unique');
        });

        Schema::create('loyalty_rules', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('threshold')->unique();
            $table->string('title_ro', 150);
            $table->string('title_hu', 150);
            $table->text('description_ro')->nullable();
            $table->text('description_hu')->nullable();
            $table->unsignedInteger('valid_days')->nullable(); // NULL = nu expiră
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        Schema::create('rewards', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('loyalty_rule_id')->constrained()->cascadeOnDelete();
            $table->foreignId('earned_event_id')->nullable()->constrained('events')->nullOnDelete();
            // available | redeemed | expired | cancelled
            $table->string('status', 20)->default('available')->index();
            $table->timestamp('earned_at');
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
            $table->unique(['user_id', 'loyalty_rule_id']);
        });

        // UNIQUE(reward_id) => o recompensă nu poate fi ridicată de două ori.
        Schema::create('reward_redemptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('reward_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('event_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('operator_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('redeemed_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reward_redemptions');
        Schema::dropIfExists('rewards');
        Schema::dropIfExists('loyalty_rules');
        Schema::dropIfExists('producer_customer_interactions');
        Schema::dropIfExists('checkins');
    }
};
