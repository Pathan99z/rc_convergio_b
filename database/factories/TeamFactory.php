<?php

namespace Database\Factories;

use App\Models\Team;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Team>
 */
class TeamFactory extends Factory
{
    protected $model = Team::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => User::factory(),
            'name' => $this->faker->words(2, true) . ' Team',
            'description' => $this->faker->sentence(),
            'created_by' => User::factory(),
        ];
    }

    /**
     * Create a team for a specific tenant.
     */
    public function forTenant(User $tenant): static
    {
        return $this->state(fn (array $attributes) => [
            'tenant_id' => $tenant->id,
            'created_by' => $tenant->id,
        ]);
    }
}
