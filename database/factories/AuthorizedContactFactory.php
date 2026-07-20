<?php

namespace Database\Factories;

use App\Models\AuthorizedContact;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class AuthorizedContactFactory extends Factory
{
    protected $model = AuthorizedContact::class;

    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'phone_e164' => '+51' . fake()->numerify('9########'), // Peru mobile format
            'role' => fake()->randomElement(['admin', 'operator', 'viewer']),
            'active' => true,
            'allowed_from' => fake()->optional()->dateTimeBetween('-30 days', 'now'),
            'user_id' => null,
            'metadata' => [],
        ];
    }

    public function admin(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => 'admin',
        ]);
    }

    public function operator(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => 'operator',
        ]);
    }

    public function viewer(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => 'viewer',
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'active' => false,
        ]);
    }
}