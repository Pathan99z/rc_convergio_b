<?php

namespace Database\Factories\Commerce;

use App\Models\Commerce\CommerceSetting;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Commerce\CommerceSetting>
 */
class CommerceSettingFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = CommerceSetting::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => 1,
            'stripe_public_key' => 'pk_test_' . Str::random(24),
            'stripe_secret_key' => 'sk_test_' . Str::random(24),
            'mode' => 'test',
            'webhook_secret' => 'whsec_' . Str::random(24),
        ];
    }

    /**
     * Indicate that the settings are for test mode.
     */
    public function test(): static
    {
        return $this->state(fn (array $attributes) => [
            'mode' => 'test',
            'stripe_public_key' => 'pk_test_' . Str::random(24),
            'stripe_secret_key' => 'sk_test_' . Str::random(24),
        ]);
    }

    /**
     * Indicate that the settings are for live mode.
     */
    public function live(): static
    {
        return $this->state(fn (array $attributes) => [
            'mode' => 'live',
            'stripe_public_key' => 'pk_live_' . Str::random(24),
            'stripe_secret_key' => 'sk_live_' . Str::random(24),
        ]);
    }

    /**
     * Indicate that the settings have no keys configured.
     */
    public function noKeys(): static
    {
        return $this->state(fn (array $attributes) => [
            'stripe_public_key' => null,
            'stripe_secret_key' => null,
            'webhook_secret' => null,
        ]);
    }
}
