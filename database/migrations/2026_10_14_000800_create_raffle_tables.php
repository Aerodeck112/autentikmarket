<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('raffles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->string('name_ro', 200);
            $table->string('name_hu', 200);
            $table->text('description_ro')->nullable();
            $table->text('description_hu')->nullable();
            $table->text('conditions_ro')->nullable();
            $table->text('conditions_hu')->nullable();
            $table->dateTime('entry_start')->nullable();
            $table->dateTime('entry_end')->nullable();
            $table->dateTime('draw_at')->nullable();
            $table->unsignedInteger('winners_count')->default(1);
            $table->boolean('requires_checkin')->default(true);
            $table->unsignedInteger('min_visits')->default(0);
            $table->boolean('auto_enroll_on_checkin')->default(true);
            // draft | open | closed | drawn | cancelled
            $table->string('status', 20)->default('draft')->index();
            $table->timestamps();
        });

        Schema::create('raffle_prizes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('raffle_id')->constrained()->cascadeOnDelete();
            $table->string('name_ro', 200);
            $table->string('name_hu', 200);
            $table->unsignedInteger('quantity')->default(1);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('raffle_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('raffle_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('source', 20)->default('self'); // self | checkin | staff
            $table->timestamps();
            $table->unique(['raffle_id', 'user_id']);
        });

        // Fiecare extragere este imuabilă: snapshot participanți + hash + operator.
        Schema::create('raffle_draws', function (Blueprint $table) {
            $table->id();
            $table->foreignId('raffle_id')->constrained()->cascadeOnDelete();
            $table->foreignId('performed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('performed_at');
            $table->unsignedInteger('participants_count');
            $table->json('participants_snapshot');
            $table->char('snapshot_hash', 64);
            $table->string('algorithm', 80);
            $table->string('status', 20)->default('valid'); // valid | voided
            $table->text('void_reason')->nullable();
            $table->foreignId('voided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('voided_at')->nullable();
            $table->timestamps();
        });

        Schema::create('raffle_winners', function (Blueprint $table) {
            $table->id();
            $table->foreignId('raffle_draw_id')->constrained()->cascadeOnDelete();
            $table->foreignId('raffle_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('raffle_prize_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedInteger('position');
            // won | claimed | forfeited
            $table->string('status', 20)->default('won');
            $table->timestamp('claimed_at')->nullable();
            $table->foreignId('claimed_operator_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['raffle_draw_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('raffle_winners');
        Schema::dropIfExists('raffle_draws');
        Schema::dropIfExists('raffle_entries');
        Schema::dropIfExists('raffle_prizes');
        Schema::dropIfExists('raffles');
    }
};
