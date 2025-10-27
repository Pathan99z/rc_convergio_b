<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\QuoteItem>
 */
class QuoteItemFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $quantity = fake()->numberBetween(1, 10);
        $unitPrice = fake()->randomFloat(2, 10, 1000);
        $discount = fake()->randomFloat(2, 0, $unitPrice * 0.2); // Max 20% discount
        $taxRate = fake()->randomFloat(2, 0, 25); // 0-25% tax rate
        
        $subtotal = $quantity * $unitPrice;
        $discountedAmount = $subtotal - $discount;
        $taxAmount = $discountedAmount * ($taxRate / 100);
        $total = $discountedAmount + $taxAmount;

        return [
            'quote_id' => \App\Models\Quote::factory(),
            'name' => fake()->words(3, true),
            'description' => fake()->optional(0.7)->sentence(),
            'quantity' => $quantity,
            'unit_price' => $unitPrice,
            'discount' => $discount,
            'tax_rate' => $taxRate,
            'total' => $total,
            'sort_order' => fake()->numberBetween(0, 100),
        ];
    }
}
