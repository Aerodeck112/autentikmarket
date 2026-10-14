<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\ProducerCategory;
use App\Models\Role;
use App\Support\Rbac;
use Illuminate\Database\Seeder;

/**
 * Date de sistem ESENȚIALE (idempotent — nu suprascrie modificările făcute din CRM).
 * Rulat automat de migrația 2026_10_14_002000_seed_system_data.
 */
class SystemDataSeeder extends Seeder
{
    public function run(): void
    {
        $this->rbac();
        $this->categories();
        $this->call([
            NotificationTemplateSeeder::class,
            TaskTemplateSeeder::class,
            LoyaltyRuleSeeder::class,
            DocumentTemplateSeeder::class,
        ]);
    }

    protected function rbac(): void
    {
        foreach (Rbac::PERMISSIONS as $name => [$group, $ro, $hu]) {
            Permission::updateOrCreate(['name' => $name], ['group' => $group, 'label_ro' => $ro, 'label_hu' => $hu]);
        }
        foreach (config('autentik.roles') as $name => [$ro, $hu]) {
            Role::updateOrCreate(['name' => $name], ['label_ro' => $ro, 'label_hu' => $hu, 'is_system' => true]);
        }
        $perms = Permission::pluck('id', 'name');
        foreach (Rbac::rolePermissions() as $role => $names) {
            $r = Role::where('name', $role)->first();
            // syncWithoutDetaching: permisiunile adăugate manual din CRM se păstrează.
            $r?->permissions()->syncWithoutDetaching(collect($names)->map(fn ($n) => $perms[$n])->filter()->all());
        }
    }

    protected function categories(): void
    {
        $cats = [
            ['miere', 'Miere & produse apicole', 'Méz és méhészeti termékek', '🍯'],
            ['lactate', 'Lactate & brânzeturi', 'Tejtermékek és sajtok', '🧀'],
            ['legume-fructe', 'Legume & fructe', 'Zöldség és gyümölcs', '🥕'],
            ['panificatie', 'Panificație & patiserie', 'Pékáru és sütemények', '🥖'],
            ['conserve', 'Conserve, dulcețuri & zacuscă', 'Befőttek, lekvárok és zakuszka', '🫙'],
            ['sucuri', 'Sucuri & siropuri naturale', 'Természetes levek és szörpök', '🧃'],
            ['carne', 'Carne & preparate', 'Hús és hústermékek', '🥓'],
            ['ceaiuri', 'Ceaiuri & plante', 'Teák és gyógynövények', '🌿'],
            ['cosmetice', 'Cosmetice naturale', 'Természetes kozmetikumok', '🧴'],
            ['curatenie', 'Produse de curățenie naturale', 'Természetes tisztítószerek', '🧼'],
            ['artizanat', 'Artizanat & meșteșuguri', 'Kézművesség', '🧺'],
            ['textile-ceramica', 'Textile & ceramică', 'Textil és kerámia', '🏺'],
        ];
        foreach ($cats as $i => [$slug, $ro, $hu, $icon]) {
            ProducerCategory::firstOrCreate(['slug' => $slug], ['name_ro' => $ro, 'name_hu' => $hu, 'icon' => $icon, 'sort_order' => $i + 1]);
        }
    }
}
