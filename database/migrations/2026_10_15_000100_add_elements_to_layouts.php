<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Decor vectorial al hărții (zone verzi, clădiri, drumuri, etichete, marcaje) — JSON validat
 * de App\Support\LayoutElements. Se copiază în snapshot-ul evenimentului împreună cu standurile.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('venue_layouts', function (Blueprint $table) {
            $table->json('elements')->nullable()->after('background_opacity');
        });
        Schema::table('event_layouts', function (Blueprint $table) {
            $table->json('elements')->nullable()->after('background_opacity');
        });
    }

    public function down(): void
    {
        Schema::table('event_layouts', fn (Blueprint $table) => $table->dropColumn('elements'));
        Schema::table('venue_layouts', fn (Blueprint $table) => $table->dropColumn('elements'));
    }
};
