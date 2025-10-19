<?php

namespace Database\Factories;

use App\Models\Commerce\CommercePaymentLink;
use App\Models\Quote;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Commerce\CommercePaymentLink>
 */
class CommercePaymentLinkFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = CommercePaymentLink::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'quote_id' => Quote::factory(),
            'stripe_session_id' => 'cs_test_' . Str::random(24),
            'url' => $this->faker->url(),
            'status' => $this->faker->randomElement(['draft', 'active', 'completed', 'expired']),
            'expires_at' => $this->faker->optional()->dateTimeBetween('now', '+30 days'),
            'tenant_id' => 1,
            'team_id' => null,
        ];
    }

    /**
     * Indicate that the payment link is draft.
     */
    public function draft(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'draft',
        ]);
    }

    /**
     * Indicate that the payment link is active.
     */
    public function active(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'active',
            'expires_at' => $this->faker->dateTimeBetween('now', '+30 days'),
        ]);
    }

    /**
     * Indicate that the payment link is completed.
     */
    public function completed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'completed',
        ]);
    }

    /**
     * Indicate that the payment link is expired.
     */
    public function expired(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'expired',
            'expires_at' => $this->faker->dateTimeBetween('-30 days', '-1 day'),
        ]);
    }

    /**
     * Indicate that the payment link is a test link.
     */
    public function test(): static
    {
        return $this->state(fn (array $attributes) => [
            'stripe_session_id' => 'test_session_' . Str::random(24),
            'url' => 'https://example.com/test-payment/' . Str::random(10),
        ]);
    }

    /**
     * Indicate that the payment link is a real Stripe link.
     */
    public function real(): static
    {
        return $this->state(fn (array $attributes) => [
            'stripe_session_id' => 'cs_test_' . Str::random(24),
            'url' => 'https://checkout.stripe.com/c/pay/' . Str::random(20),
        ]);
    }
}
