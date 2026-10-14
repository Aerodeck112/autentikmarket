<?php

namespace Database\Factories;

use App\Enums\ProducerStatus;
use App\Models\Producer;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Producer>
 */
class ProducerFactory extends Factory
{
    protected $model = Producer::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory()->withRole('producer'),
            'brand_name' => fake()->unique()->company(),
            'legal_name' => fake()->company().' SRL',
            'representative_name' => fake()->name(),
            'city' => 'Târgu Mureș',
            'county' => 'Mureș',
            'email' => fake()->unique()->safeEmail(),
            'phone' => '07'.fake()->numerify('########'),
            'preferred_language' => 'ro',
        ];
    }

    public function approved(): static
    {
        return $this->afterCreating(fn (Producer $p) => $p->forceFill(['status' => ProducerStatus::Approved, 'approved_at' => now()])->save());
    }

    public function hungarian(): static
    {
        return $this->state(fn () => ['preferred_language' => 'hu']);
    }
}
