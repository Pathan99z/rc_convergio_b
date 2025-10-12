<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Deal>
 */
class DealFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->words(3, true),
            'value' => fake()->randomFloat(2, 1000, 100000),
            'stage' => fake()->randomElement(['Prospecting', 'Qualification', 'Proposal', 'Negotiation', 'Closed Won', 'Closed Lost']),
            'source' => fake()->randomElement(['Website', 'Referral', 'Cold Call', 'Email', 'Social Media']),
            'probability' => fake()->numberBetween(0, 100),
            'close_date' => fake()->dateTimeBetween('now', '+90 days'),
            'owner_id' => \App\Models\User::factory(),
            'tenant_id' => \App\Models\User::factory(),
        ];
    }

    public function highValue(): static
    {
        return $this->state(fn (array $attributes) => [
            'value' => fake()->randomFloat(2, 10000, 100000),
        ]);
    }

    public function lowValue(): static
    {
        return $this->state(fn (array $attributes) => [
            'value' => fake()->randomFloat(2, 1000, 5000),
        ]);
    }

    public function prospecting(): static
    {
        return $this->state(fn (array $attributes) => [
            'stage' => 'Prospecting',
        ]);
    }

    public function closedWon(): static
    {
        return $this->state(fn (array $attributes) => [
            'stage' => 'Closed Won',
        ]);
    }

    public function fromWebsite(): static
    {
        return $this->state(fn (array $attributes) => [
            'source' => 'Website',
        ]);
    }
}
