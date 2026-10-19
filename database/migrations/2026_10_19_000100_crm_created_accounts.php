<?php

use App\Models\Permission;
use App\Models\Role;
use Database\Seeders\NotificationTemplateSeeder;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Cumpărători și producători adăugați de organizator din CRM:
 *  - e-mailul devine opțional (cumpărători doar cu telefon, înscriși la târg);
 *  - șabloanele de invitație ACCOUNT_INVITE / PRODUCER_INVITE (RO/HU);
 *  - organizatorul poate adăuga cumpărători (permisiunea visitors.manage).
 * Aditiv: nicio dată existentă nu se modifică.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('email')->nullable()->change();
        });

        (new NotificationTemplateSeeder)->run();

        // Doar adăugăm permisiunea; restul drepturilor rolului, modificate eventual din CRM, rămân neatinse.
        $role = Role::where('name', 'organizer')->first();
        $permission = Permission::where('name', 'visitors.manage')->first();
        if ($role && $permission) {
            $role->permissions()->syncWithoutDetaching([$permission->id]);
        }
    }

    public function down(): void
    {
        // E-mailul rămâne opțional: conturile create fără e-mail nu pot primi o valoare inventată.
    }
};
