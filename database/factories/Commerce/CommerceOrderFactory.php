<?php

namespace Database\Factories\Commerce;

use App\Models\Commerce\CommerceOrder;
use App\Models\Deal;
use App\Models\Quote;
use App\Models\Contact;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Commerce\CommerceOrder>
 */
class CommerceOrderFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = CommerceOrder::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'order_number' => 'ORD-' . date('Y') . '-' . strtoupper($this->faker->unique()->lexify('??????')),
            'deal_id' => null,
            'quote_id' => null,
            'contact_id' => null,
            'customer_snapshot' => null,
            'subtotal' => $this->faker->randomFloat(2, 50, 500),
            'tax' => $this->faker->randomFloat(2, 0, 50),
            'discount' => $this->faker->randomFloat(2, 0, 100),
            'total_amount' => $this->faker->randomFloat(2, 100, 1000),
            'currency' => $this->faker->randomElement(['USD', 'EUR', 'GBP', 'CAD', 'AUD']),
            'status' => $this->faker->randomElement(['pending', 'paid', 'failed', 'refunded']),
            'payment_method' => $this->faker->optional()->randomElement(['stripe', 'paypal', 'bank_transfer']),
            'payment_reference' => $this->faker->optional()->uuid(),
            'tenant_id' => 1,
            'team_id' => null,
            'owner_id' => null,
            'created_by' => null,
        ];
    }

    /**
     * Indicate that the order is pending.
     */
    public function pending(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'pending',
        ]);
    }

    /**
     * Indicate that the order is paid.
     */
    public function paid(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'paid',
            'payment_method' => $this->faker->randomElement(['stripe', 'paypal', 'bank_transfer']),
            'payment_reference' => $this->faker->uuid(),
        ]);
    }

    /**
     * Indicate that the order is failed.
     */
    public function failed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'failed',
        ]);
    }

    /**
     * Indicate that the order is refunded.
     */
    public function refunded(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'refunded',
        ]);
    }

    /**
     * Indicate that the order is cancelled.
     */
    public function cancelled(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'cancelled',
        ]);
    }

    /**
     * Indicate that the order has a deal.
     */
    public function withDeal(): static
    {
        return $this->state(fn (array $attributes) => [
            'deal_id' => Deal::factory(),
        ]);
    }

    /**
     * Indicate that the order has a quote.
     */
    public function withQuote(): static
    {
        return $this->state(fn (array $attributes) => [
            'quote_id' => Quote::factory(),
        ]);
    }

    /**
     * Indicate that the order has a contact.
     */
    public function withContact(): static
    {
        return $this->state(fn (array $attributes) => [
            'contact_id' => Contact::factory(),
        ]);
    }
}
