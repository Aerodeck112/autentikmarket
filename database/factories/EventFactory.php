<?php

namespace Database\Factories;

use App\Models\Event;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Event>
 */
class EventFactory extends Factory
{
    protected $model = Event::class;

    public function definition(): array
    {
        $date = now()->addDays(20);

        return [
            'title_ro' => 'Autentik Market Târgu Mureș',
            'title_hu' => 'Autentik Market Marosvásárhely',
            'city' => 'Târgu Mureș',
            'venue_name' => 'Curtea Merkur',
            'address' => 'Str. Livezeni nr. 7',
            'event_date' => $date->toDateString(),
            'start_time' => '08:00',
            'end_time' => '15:00',
            'registration_start' => now()->subDay(),
            'registration_end' => $date->copy()->subDays(2),
        ];
    }
}
