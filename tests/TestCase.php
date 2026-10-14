<?php

namespace Tests;

use App\Enums\EventStatus;
use App\Models\Event;
use App\Models\Producer;
use App\Models\User;
use App\Models\Venue;
use App\Services\Events\EventLifecycleService;
use Database\Seeders\VenueSeeder;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function admin(string $role = 'super_admin'): User
    {
        return User::factory()->withRole($role)->create();
    }

    protected function visitor(string $lang = 'ro'): User
    {
        return User::factory()->withRole('visitor')->create(['preferred_language' => $lang]);
    }

    protected function approvedProducer(string $lang = 'ro'): Producer
    {
        return Producer::factory()->approved()->create(['preferred_language' => $lang]);
    }

    /** Eveniment cu înscrieri deschise și hartă (snapshot din layout-ul demo). */
    protected function openEvent(array $attrs = []): Event
    {
        $this->seed(VenueSeeder::class);
        $venue = Venue::where('code', 'TM')->firstOrFail();
        $lifecycle = app(EventLifecycleService::class);
        $event = $lifecycle->create(array_merge(Event::factory()->raw(), ['venue_id' => $venue->id], $attrs), $this->admin());
        $lifecycle->changeStatus($event, EventStatus::RegistrationsOpen);

        return $event->fresh(['layout']);
    }
}
