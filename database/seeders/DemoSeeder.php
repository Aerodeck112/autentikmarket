<?php

namespace Database\Seeders;

use App\Enums\EventStatus;
use App\Enums\Language;
use App\Enums\ProducerStatus;
use App\Models\Event;
use App\Models\Producer;
use App\Models\ProducerCategory;
use App\Models\User;
use App\Models\Venue;
use App\Services\Events\EventLifecycleService;
use Illuminate\Database\Seeder;

/**
 * Date DEMO pentru development / prezentare. NU rulați în producție.
 * Producătorii demo sunt fictivi și marcați ca atare.
 */
class DemoSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([SystemDataSeeder::class, VenueSeeder::class]);

        $admin = User::firstOrCreate(['email' => 'admin@example.test'], [
            'first_name' => 'Admin', 'last_name' => 'Demo', 'password' => 'Parola123', 'preferred_language' => Language::Ro,
            'email_verified_at' => now(),
        ]);
        $admin->assignRole('super_admin');

        $staff = User::firstOrCreate(['email' => 'staff@example.test'], [
            'first_name' => 'Staff', 'last_name' => 'Demo', 'password' => 'Parola123', 'preferred_language' => Language::Hu,
            'email_verified_at' => now(),
        ]);
        $staff->assignRole('staff');

        $cats = ProducerCategory::pluck('id')->all();
        $demo = [
            ['Stupina Demo', 'Miere', 'ro'], ['Demo Sajtműhely', 'Sajt', 'hu'], ['Grădina Demo', 'Legume', 'ro'],
            ['Demo Pékség', 'Pékáru', 'hu'], ['Atelier Demo Ceramică', 'Ceramică', 'ro'], ['Demo Szappan', 'Szappan', 'hu'],
        ];
        foreach ($demo as $i => [$brand, $_, $lang]) {
            $u = User::firstOrCreate(['email' => "producer{$i}@example.test"], [
                'first_name' => 'Producător', 'last_name' => 'Demo '.($i + 1), 'password' => 'Parola123',
                'preferred_language' => $lang, 'email_verified_at' => now(),
            ]);
            $u->assignRole('producer');
            if (! $u->producer) {
                $p = new Producer([
                    'user_id' => $u->id, 'brand_name' => $brand.' [DEMO]', 'producer_category_id' => $cats[$i % count($cats)],
                    'city' => $i % 2 ? 'Marosvásárhely' : 'Târgu Mureș', 'county' => 'Mureș', 'preferred_language' => $lang,
                    'representative_name' => $u->name, 'email' => $u->email,
                    'description_ro' => 'Producător demonstrativ — date fictive.', 'description_hu' => 'Bemutató termelő — fiktív adatok.',
                ]);
                $p->save();
                $p->forceFill(['status' => ProducerStatus::Approved, 'approved_at' => now()])->save();
            }
        }

        foreach (range(1, 20) as $i) {
            $u = User::firstOrCreate(['email' => "visitor{$i}@example.test"], [
                'first_name' => $i % 2 ? 'Vizitator' : 'Látogató', 'last_name' => 'Demo '.$i, 'password' => 'Parola123',
                'preferred_language' => $i % 2 ? 'ro' : 'hu', 'city' => $i % 2 ? 'Târgu Mureș' : 'Marosvásárhely',
                'email_verified_at' => now(),
            ]);
            $u->assignRole('visitor');
        }

        $venue = Venue::where('code', 'TM')->first();
        if ($venue && ! Event::exists()) {
            $lifecycle = app(EventLifecycleService::class);
            $date = now()->next('Saturday')->addWeeks(3);
            $event = $lifecycle->create([
                'title_ro' => 'Autentik Market Târgu Mureș', 'title_hu' => 'Autentik Market Marosvásárhely',
                'venue_id' => $venue->id, 'city' => $venue->city, 'venue_name' => 'Curtea Merkur', 'address' => $venue->address,
                'event_date' => $date->toDateString(), 'start_time' => '08:00', 'end_time' => '15:00',
                'description_ro' => 'Târgul lunar de producători și artizani.', 'description_hu' => 'Havi termelői és kézműves vásár.',
                'registration_start' => now()->subDay(), 'registration_end' => $date->copy()->subDays(3),
            ], $admin);
            $lifecycle->changeStatus($event, EventStatus::RegistrationsOpen, $admin);
        }
    }
}
