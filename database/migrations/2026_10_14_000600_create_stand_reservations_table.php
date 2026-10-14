<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stand_reservations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->foreignId('event_slot_id')->constrained()->cascadeOnDelete();
            $table->foreignId('producer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('event_registration_id')->nullable()->constrained()->nullOnDelete();
            // hold | requested | approved | rejected | expired | cancelled | released
            $table->string('status', 20)->index();
            // = event_slot_id cât timp rezervarea este activă; NULL altfel.
            // UNIQUE => un singur rând activ per stand, garantat de baza de date.
            $table->unsignedBigInteger('active_slot_id')->nullable()->unique();
            // = "{event_id}:{producer_id}" cât timp e activă => max. o rezervare activă / producător / eveniment.
            $table->string('active_producer_key', 50)->nullable()->unique();
            $table->timestamp('hold_expires_at')->nullable()->index();
            $table->timestamp('requested_at')->nullable();
            $table->timestamp('decided_at')->nullable();
            $table->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->decimal('price', 10, 2)->nullable();
            $table->text('notes')->nullable();
            $table->text('admin_notes')->nullable();
            $table->timestamps();
            $table->index(['event_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stand_reservations');
    }
};
