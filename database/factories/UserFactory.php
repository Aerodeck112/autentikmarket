<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    protected $model = User::class;

    protected static ?string $password = null;

    public function definition(): array
    {
        return [
            'first_name' => fake()->firstName(),
            'last_name' => fake()->lastName(),
            'email' => fake()->unique()->safeEmail(),
            'phone' => '07'.fake()->numerify('########'),
            'city' => fake()->randomElement(['Târgu Mureș', 'Reghin', 'Sighișoara', 'Sovata']),
            'preferred_language' => 'ro',
            'email_verified_at' => now(),
            'password' => static::$password ??= 'password1',
        ];
    }

    public function hungarian(): static
    {
        return $this->state(fn () => ['preferred_language' => 'hu']);
    }

    public function unverified(): static
    {
        return $this->state(fn () => ['email_verified_at' => null]);
    }

    public function withRole(string $role): static
    {
        return $this->afterCreating(fn (User $u) => $u->assignRole($role));
    }
}
