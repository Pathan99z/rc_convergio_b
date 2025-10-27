<?php

namespace Database\Factories\Commerce;

use App\Models\Commerce\CommerceTransaction;
use App\Models\Commerce\CommerceOrder;
use App\Models\Commerce\CommercePaymentLink;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Commerce\CommerceTransaction>
 */
class CommerceTransactionFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = CommerceTransaction::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'order_id' => null,
            'payment_link_id' => null,
            'payment_provider' => $this->faker->randomElement(['stripe', 'paypal', 'bank_transfer']),
            'provider_event_id' => $this->faker->optional()->uuid(),
            'amount' => $this->faker->randomFloat(2, 10, 1000),
            'currency' => $this->faker->randomElement(['USD', 'EUR', 'GBP', 'CAD', 'AUD']),
            'status' => $this->faker->randomElement(['succeeded', 'failed', 'refunded', 'pending']),
            'event_type' => $this->faker->randomElement([
                'checkout.session.completed',
                'payment_intent.succeeded',
                'payment_intent.payment_failed',
                'charge.refunded',
                'invoice.payment_succeeded',
            ]),
            'raw_payload' => [
                'id' => $this->faker->uuid(),
                'type' => $this->faker->randomElement(['checkout.session.completed', 'payment_intent.succeeded']),
                'data' => [
                    'object' => [
                        'id' => $this->faker->uuid(),
                        'amount' => $this->faker->numberBetween(1000, 100000),
                        'currency' => strtolower($this->faker->randomElement(['USD', 'EUR', 'GBP'])),
                    ],
                ],
            ],
            'tenant_id' => 1,
            'team_id' => null,
        ];
    }

    /**
     * Indicate that the transaction is successful.
     */
    public function succeeded(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'succeeded',
            'event_type' => $this->faker->randomElement(['checkout.session.completed', 'payment_intent.succeeded']),
        ]);
    }

    /**
     * Indicate that the transaction failed.
     */
    public function failed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'failed',
            'event_type' => 'payment_intent.payment_failed',
        ]);
    }

    /**
     * Indicate that the transaction is refunded.
     */
    public function refunded(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'refunded',
            'event_type' => 'charge.refunded',
        ]);
    }

    /**
     * Indicate that the transaction is pending.
     */
    public function pending(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'pending',
        ]);
    }

    /**
     * Indicate that the transaction is for Stripe.
     */
    public function stripe(): static
    {
        return $this->state(fn (array $attributes) => [
            'payment_provider' => 'stripe',
            'provider_event_id' => 'evt_' . Str::random(24),
        ]);
    }

    /**
     * Indicate that the transaction is for PayPal.
     */
    public function paypal(): static
    {
        return $this->state(fn (array $attributes) => [
            'payment_provider' => 'paypal',
            'provider_event_id' => 'PAY-' . Str::random(20),
        ]);
    }

    /**
     * Indicate that the transaction has an order.
     */
    public function withOrder(): static
    {
        return $this->state(fn (array $attributes) => [
            'order_id' => CommerceOrder::factory(),
        ]);
    }

    /**
     * Indicate that the transaction has a payment link.
     */
    public function withPaymentLink(): static
    {
        return $this->state(fn (array $attributes) => [
            'payment_link_id' => CommercePaymentLink::factory(),
        ]);
    }
}
