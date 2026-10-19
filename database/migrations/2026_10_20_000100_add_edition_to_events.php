<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Numărul ediției târgului („Ediția a 12-a”), per locație. Editabil din CRM: târgul a avut ediții
 * și înainte de platformă. Evenimentele existente primesc numere în ordinea datei (fără cele anulate).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->unsignedSmallInteger('edition')->nullable()->after('slug');
            $table->index(['venue_id', 'edition']);
        });

        $counters = [];
        DB::table('events')->where('status', '!=', 'cancelled')->orderBy('event_date')->orderBy('id')
            ->get(['id', 'venue_id', 'city'])
            ->each(function ($e) use (&$counters) {
                $key = $e->venue_id ? 'v'.$e->venue_id : 'c'.mb_strtolower((string) $e->city);
                $counters[$key] = ($counters[$key] ?? 0) + 1;
                DB::table('events')->where('id', $e->id)->update(['edition' => $counters[$key]]);
            });
    }

    public function down(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->dropIndex(['venue_id', 'edition']);
            $table->dropColumn('edition');
        });
    }
};
