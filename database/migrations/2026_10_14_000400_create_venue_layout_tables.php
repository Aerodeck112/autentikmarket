<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('venues', function (Blueprint $table) {
            $table->id();
            $table->string('name', 150);
            $table->string('slug', 170)->unique();
            $table->string('code', 10)->unique(); // prefix standuri: TM, RG…
            $table->string('city', 120)->index();
            $table->string('city_hu', 120)->nullable();
            $table->string('county', 120)->nullable();
            $table->string('address', 255)->nullable();
            $table->string('address_hu', 255)->nullable();
            $table->decimal('lat', 10, 7)->nullable();
            $table->decimal('lng', 10, 7)->nullable();
            $table->text('description_ro')->nullable();
            $table->text('description_hu')->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        // Layout master (editabil) per locație. Coordonatele sunt în METRI.
        Schema::create('venue_layouts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('venue_id')->constrained()->cascadeOnDelete();
            $table->string('name', 150);
            $table->boolean('is_master')->default(false)->index();
            $table->decimal('width_m', 8, 2)->default(40);
            $table->decimal('height_m', 8, 2)->default(30);
            $table->string('background_path')->nullable();
            $table->decimal('background_opacity', 3, 2)->default(0.6);
            $table->unsignedInteger('version')->default(1);
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('layout_slots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('venue_layout_id')->constrained()->cascadeOnDelete();
            $table->string('code', 20);
            $table->string('label', 60)->nullable();
            $table->decimal('x', 8, 2);
            $table->decimal('y', 8, 2);
            $table->decimal('width', 8, 2);
            $table->decimal('height', 8, 2);
            $table->decimal('rotation', 6, 2)->default(0);
            $table->decimal('price', 10, 2)->nullable(); // NULL = tarif la cerere (nu inventăm prețuri)
            $table->boolean('has_electricity')->default(false);
            $table->json('features')->nullable();
            $table->boolean('is_blocked')->default(false);
            $table->string('notes', 255)->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
            $table->unique(['venue_layout_id', 'code']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('layout_slots');
        Schema::dropIfExists('venue_layouts');
        Schema::dropIfExists('venues');
    }
};
