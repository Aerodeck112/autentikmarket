<?php

use Database\Seeders\NotificationTemplateSeeder;
use Illuminate\Database\Migrations\Migration;

/**
 * Șabloanele noi pentru notificările interne (admin și super admin): producător nou înscris,
 * rezervare temporară de stand, cerere de stand. Idempotent: textele editate din CRM rămân neatinse.
 */
return new class extends Migration
{
    public function up(): void
    {
        (new NotificationTemplateSeeder)->run();
    }

    public function down(): void
    {
        // Șabloanele rămân (pot fi dezactivate din CRM → Automatizări).
    }
};
