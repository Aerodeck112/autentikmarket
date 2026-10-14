<?php

use Database\Seeders\SystemDataSeeder;
use Illuminate\Database\Migrations\Migration;

/**
 * Datele de sistem (roluri, permisiuni, template-uri, categorii) sunt inserate de migrații.
 * Astfel, atât instalarea inițială, cât și update-urile prin ZIP (care rulează doar `migrate`)
 * aduc automat datele de sistem noi. Seeder-ul este idempotent.
 */
return new class extends Migration
{
    public function up(): void
    {
        (new SystemDataSeeder)->run();
    }

    public function down(): void
    {
        // Datele de sistem nu se șterg la rollback (siguranța datelor).
    }
};
