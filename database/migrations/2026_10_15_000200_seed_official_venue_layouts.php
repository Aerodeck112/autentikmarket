<?php

use Database\Seeders\VenueSeeder;
use Illuminate\Database\Migrations\Migration;

/**
 * Hărțile oficiale Târgu Mureș (curtea Merkur) și Reghin (Catedrala Greco-Catolică),
 * digitizate din schițele organizatorului. Seeder idempotent: nu suprascrie un layout
 * MASTER creat de organizator și nu atinge evenimentele existente (au snapshot propriu).
 */
return new class extends Migration
{
    public function up(): void
    {
        (new VenueSeeder)->run();
    }

    public function down(): void
    {
        // Date de sistem: nu se șterg la rollback (layout-urile pot avea deja evenimente).
    }
};
