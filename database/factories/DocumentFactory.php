<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Document>
 */
class DocumentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $filename = $this->faker->word() . '_' . time() . '.pdf';
        
        return [
            'tenant_id' => \App\Models\User::factory(),
            'team_id' => null,
            'owner_id' => \App\Models\User::factory(),
            'related_type' => null,
            'related_id' => null,
            'title' => $this->faker->sentence(3),
            'file_path' => 'tenant_1/documents/' . $filename,
            'file_type' => 'application/pdf',
            'file_size' => $this->faker->numberBetween(1000, 10000000),
            'visibility' => $this->faker->randomElement(['private', 'team', 'tenant']),
            'view_count' => $this->faker->numberBetween(0, 100),
            'last_viewed_at' => $this->faker->optional()->dateTimeBetween('-30 days', 'now'),
            'metadata' => [
                'original_name' => $this->faker->word() . '.pdf',
                'upload_source' => 'web',
            ],
            'created_by' => \App\Models\User::factory(),
        ];
    }
}
