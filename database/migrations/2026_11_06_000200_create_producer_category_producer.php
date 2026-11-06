<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Un producător poate fi în mai multe categorii. producers.producer_category_id rămâne categoria principală
 * (afișată pe card); tabela de legătură le conține pe toate, inclusiv pe cea principală.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('producer_category_producer')) {
            Schema::create('producer_category_producer', function (Blueprint $table) {
                $table->foreignId('producer_id')->constrained()->cascadeOnDelete();
                $table->foreignId('producer_category_id')->constrained()->cascadeOnDelete();
                $table->primary(['producer_id', 'producer_category_id']);
                $table->index('producer_category_id');
            });
        }
        DB::table('producers')->whereNotNull('producer_category_id')->orderBy('id')->select(['id', 'producer_category_id'])
            ->chunkById(500, function ($rows) {
                DB::table('producer_category_producer')->insertOrIgnore($rows->map(fn ($r) => [
                    'producer_id' => $r->id, 'producer_category_id' => $r->producer_category_id,
                ])->all());
            });
    }

    public function down(): void
    {
        Schema::dropIfExists('producer_category_producer');
    }
};
