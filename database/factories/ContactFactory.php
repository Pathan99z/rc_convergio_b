<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Contact>
 */
class ContactFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'first_name' => fake()->firstName(),
            'last_name' => fake()->lastName(),
            'email' => fake()->unique()->safeEmail(),
            'phone' => fake()->optional(0.7)->phoneNumber(),
            'company_name' => fake()->optional(0.8)->company(),
            'company_country' => fake()->optional(0.6)->country(),
            'lifecycle_stage' => fake()->randomElement(['Lead', 'Prospect', 'Customer', 'Champion']),
            'source' => fake()->randomElement(['Website', 'Referral', 'Cold Call', 'Email', 'Social Media']),
            'owner_id' => \App\Models\User::factory(),
            'tenant_id' => \App\Models\User::factory(),
        ];
    }

    public function lead(): static
    {
        return $this->state(fn (array $attributes) => [
            'lifecycle_stage' => 'Lead',
        ]);
    }

    public function prospect(): static
    {
        return $this->state(fn (array $attributes) => [
            'lifecycle_stage' => 'Prospect',
        ]);
    }

    public function customer(): static
    {
        return $this->state(fn (array $attributes) => [
            'lifecycle_stage' => 'Customer',
        ]);
    }

    public function fromUsa(): static
    {
        return $this->state(fn (array $attributes) => [
            'company_country' => 'USA',
        ]);
    }

    public function fromWebsite(): static
    {
        return $this->state(fn (array $attributes) => [
            'source' => 'Website',
        ]);
    }
}
