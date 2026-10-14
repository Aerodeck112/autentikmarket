<?php

namespace Database\Seeders;

use App\Models\LoyaltyRule;
use Illuminate\Database\Seeder;

class LoyaltyRuleSeeder extends Seeder
{
    public function run(): void
    {
        // Doar dacă nu există reguli (adminul le poate modifica/șterge liber din CRM).
        if (LoyaltyRule::exists()) {
            return;
        }
        LoyaltyRule::create([
            'threshold' => 5,
            'title_ro' => 'Cadou Autentik — 5 vizite',
            'title_hu' => 'Autentik ajándék — 5 látogatás',
            'description_ro' => 'Mulțumim că revii! Ridică-ți cadoul de la standul de informații.',
            'description_hu' => 'Köszönjük, hogy visszatérsz! Ajándékodat az információs standnál veheted át.',
            'active' => true,
        ]);
        LoyaltyRule::create([
            'threshold' => 10,
            'title_ro' => 'Cadou special — 10 vizite',
            'title_hu' => 'Különleges ajándék — 10 látogatás',
            'description_ro' => 'Ești parte din comunitatea Autentik. Te așteaptă un cadou special de la producătorii noștri.',
            'description_hu' => 'Az Autentik közösség része vagy. Különleges ajándék vár termelőinktől.',
            'active' => true,
        ]);
    }
}
