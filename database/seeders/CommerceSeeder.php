<?php

namespace Database\Seeders;

use App\Models\Commerce\CommerceOrder;
use App\Models\Commerce\CommerceOrderItem;
use App\Models\Commerce\CommercePaymentLink;
use App\Models\Commerce\CommerceSetting;
use App\Models\Commerce\CommerceTransaction;
use App\Models\Quote;
use App\Models\Contact;
use App\Models\Deal;
use App\Models\User;
use App\Models\Team;
use Illuminate\Database\Seeder;

class CommerceSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create a test tenant user
        $tenant = User::factory()->create([
            'name' => 'Commerce Test Tenant',
            'email' => 'commerce@test.com',
            'tenant_id' => 1,
        ]);

        // Create a test team
        $team = Team::factory()->create([
            'name' => 'Commerce Team',
            'tenant_id' => $tenant->tenant_id,
            'created_by' => $tenant->id,
        ]);

        // Create test contacts
        $contacts = Contact::factory()->count(5)->create([
            'tenant_id' => $tenant->tenant_id,
        ]);

        // Create test deals
        $deals = Deal::factory()->count(3)->create([
            'tenant_id' => $tenant->tenant_id,
            'owner_id' => $tenant->id,
        ]);

        // Create test quotes
        $quotes = collect();
        for ($i = 0; $i < 5; $i++) {
            $quote = Quote::factory()->create([
                'tenant_id' => $tenant->tenant_id,
                'contact_id' => $contacts->random()->id,
                'deal_id' => $deals->random()->id,
                'owner_id' => $tenant->id,
                'total_amount' => fake()->randomFloat(2, 100, 1000),
                'currency' => fake()->randomElement(['USD', 'EUR', 'GBP']),
            ]);

            // Create quote items
            $itemCount = fake()->numberBetween(1, 4);
            for ($j = 0; $j < $itemCount; $j++) {
                $quote->items()->create([
                    'name' => fake()->words(3, true),
                    'description' => fake()->sentence(),
                    'quantity' => fake()->numberBetween(1, 5),
                    'unit_price' => fake()->randomFloat(2, 20, 200),
                    'discount' => fake()->randomFloat(2, 0, 50),
                    'tax_rate' => fake()->randomFloat(2, 0, 25),
                ]);
            }

            $quotes->push($quote);
        }

        // Create test orders
        $orders = collect();
        for ($i = 0; $i < 10; $i++) {
            $order = CommerceOrder::factory()->create([
                'tenant_id' => $tenant->tenant_id,
                'team_id' => $team->id,
                'owner_id' => $tenant->id,
                'created_by' => $tenant->id,
                'contact_id' => $contacts->random()->id,
                'deal_id' => $deals->random()->id,
                'quote_id' => fake()->boolean(30) ? $quotes->random()->id : null,
                'status' => fake()->randomElement(['pending', 'paid', 'failed', 'refunded', 'cancelled']),
                'total_amount' => fake()->randomFloat(2, 50, 2000),
                'currency' => fake()->randomElement(['USD', 'EUR', 'GBP']),
                'payment_method' => fake()->randomElement(['stripe', 'paypal', 'bank_transfer', null]),
                'payment_reference' => fake()->boolean(70) ? fake()->uuid() : null,
            ]);

            // Create order items
            $itemCount = fake()->numberBetween(1, 5);
            for ($j = 0; $j < $itemCount; $j++) {
                CommerceOrderItem::factory()->create([
                    'order_id' => $order->id,
                    'name' => fake()->words(3, true),
                    'description' => fake()->sentence(),
                    'quantity' => fake()->numberBetween(1, 3),
                    'unit_price' => fake()->randomFloat(2, 10, 500),
                    'discount' => fake()->randomFloat(2, 0, 100),
                    'tax_rate' => fake()->randomFloat(2, 0, 25),
                ]);
            }

            $orders->push($order);
        }

        // Create test payment links
        for ($i = 0; $i < 8; $i++) {
            $quote = $quotes->random();
            $order = $orders->random();

            CommercePaymentLink::factory()->create([
                'tenant_id' => $tenant->tenant_id,
                'team_id' => $team->id,
                'owner_id' => $tenant->id,
                'created_by' => $tenant->id,
                'quote_id' => fake()->boolean(60) ? $quote->id : null,
                'order_id' => fake()->boolean(40) ? $order->id : null,
                'status' => fake()->randomElement(['draft', 'active', 'completed', 'expired', 'cancelled']),
                'stripe_session_id' => fake()->boolean(80) ? 'cs_test_' . fake()->regexify('[A-Za-z0-9]{24}') : null,
                'public_url' => fake()->boolean(70) ? fake()->url() : null,
                'expires_at' => fake()->boolean(60) ? fake()->dateTimeBetween('now', '+30 days') : null,
            ]);
        }

        // Create test transactions
        for ($i = 0; $i < 15; $i++) {
            $order = $orders->random();
            
            CommerceTransaction::factory()->create([
                'tenant_id' => $tenant->tenant_id,
                'team_id' => $team->id,
                'order_id' => fake()->boolean(80) ? $order->id : null,
                'payment_provider' => fake()->randomElement(['stripe', 'paypal', 'bank_transfer']),
                'provider_event_id' => fake()->boolean(90) ? fake()->uuid() : null,
                'amount' => fake()->randomFloat(2, 10, 1000),
                'currency' => fake()->randomElement(['USD', 'EUR', 'GBP']),
                'status' => fake()->randomElement(['succeeded', 'failed', 'refunded', 'pending']),
                'event_type' => fake()->randomElement([
                    'checkout.session.completed',
                    'payment_intent.succeeded',
                    'payment_intent.payment_failed',
                    'charge.refunded',
                    'invoice.payment_succeeded',
                ]),
                'raw_payload' => [
                    'id' => fake()->uuid(),
                    'type' => fake()->randomElement(['checkout.session.completed', 'payment_intent.succeeded']),
                    'data' => [
                        'object' => [
                            'id' => fake()->uuid(),
                            'amount' => fake()->numberBetween(1000, 100000),
                            'currency' => strtolower(fake()->randomElement(['USD', 'EUR', 'GBP'])),
                        ],
                    ],
                ],
            ]);
        }

        // Create commerce settings
        CommerceSetting::factory()->create([
            'tenant_id' => $tenant->tenant_id,
            'stripe_public_key' => 'pk_test_' . fake()->regexify('[A-Za-z0-9]{24}'),
            'stripe_secret_key' => 'sk_test_' . fake()->regexify('[A-Za-z0-9]{24}'),
            'mode' => 'test',
            'webhook_secret' => 'whsec_' . fake()->regexify('[A-Za-z0-9]{24}'),
        ]);

        $this->command->info('Commerce test data seeded successfully!');
        $this->command->info("Created:");
        $this->command->info("- 1 tenant user (commerce@test.com)");
        $this->command->info("- 1 team");
        $this->command->info("- 5 contacts");
        $this->command->info("- 3 deals");
        $this->command->info("- 5 quotes with items");
        $this->command->info("- 10 orders with items");
        $this->command->info("- 8 payment links");
        $this->command->info("- 15 transactions");
        $this->command->info("- 1 commerce settings");
    }
}
