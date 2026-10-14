<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('events', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 190)->unique();
            $table->string('title_ro', 200);
            $table->string('title_hu', 200);
            $table->foreignId('venue_id')->nullable()->constrained()->nullOnDelete();
            $table->string('city', 120)->index();
            $table->string('venue_name', 150)->nullable();
            $table->string('address', 255)->nullable();
            $table->date('event_date')->index();
            $table->time('start_time')->nullable();
            $table->time('end_time')->nullable();
            $table->text('description_ro')->nullable();
            $table->text('description_hu')->nullable();
            $table->text('program_ro')->nullable();
            $table->text('program_hu')->nullable();
            $table->dateTime('registration_start')->nullable();
            $table->dateTime('registration_end')->nullable();
            $table->string('cover_path')->nullable();
            // draft | planning | registrations_open | registrations_closed | active | completed | cancelled
            $table->string('status', 30)->default('draft')->index();
            $table->unsignedInteger('max_producers')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('post_event_processed_at')->nullable();
            $table->timestamp('reminders_sent_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['status', 'event_date']);
        });

        // Snapshot al layout-ului master la crearea evenimentului (versionare).
        Schema::create('event_layouts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('source_layout_id')->nullable()->constrained('venue_layouts')->nullOnDelete();
            $table->unsignedInteger('source_version')->nullable();
            $table->decimal('width_m', 8, 2);
            $table->decimal('height_m', 8, 2);
            $table->string('background_path')->nullable();
            $table->decimal('background_opacity', 3, 2)->default(0.6);
            $table->timestamps();
        });

        Schema::create('event_slots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_layout_id')->constrained()->cascadeOnDelete();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->foreignId('source_slot_id')->nullable()->constrained('layout_slots')->nullOnDelete();
            $table->string('code', 20);
            $table->string('label', 60)->nullable();
            $table->decimal('x', 8, 2);
            $table->decimal('y', 8, 2);
            $table->decimal('width', 8, 2);
            $table->decimal('height', 8, 2);
            $table->decimal('rotation', 6, 2)->default(0);
            $table->decimal('price', 10, 2)->nullable();
            $table->boolean('has_electricity')->default(false);
            $table->json('features')->nullable();
            // available | hold | requested | reserved | occupied | blocked | unavailable
            $table->string('status', 20)->default('available')->index();
            $table->string('notes', 255)->nullable();
            $table->timestamps();
            $table->unique(['event_id', 'code']);
        });

        // Participarea unui producător la un eveniment.
        Schema::create('event_registrations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->foreignId('producer_id')->constrained()->cascadeOnDelete();
            // draft | submitted | approved | rejected | cancelled
            $table->string('status', 20)->default('draft')->index();
            $table->boolean('needs_electricity')->default(false);
            $table->boolean('needs_table')->default(false);
            $table->boolean('needs_tent')->default(false);
            $table->text('products_note')->nullable();
            $table->text('notes')->nullable();
            $table->timestamp('data_confirmed_at')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('decided_at')->nullable();
            $table->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('admin_notes')->nullable();
            // NULL = nemarcat, true = prezent, false = absent
            $table->boolean('attended')->nullable();
            $table->timestamp('thanked_at')->nullable();
            $table->timestamps();
            $table->unique(['event_id', 'producer_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_registrations');
        Schema::dropIfExists('event_slots');
        Schema::dropIfExists('event_layouts');
        Schema::dropIfExists('events');
    }
};
