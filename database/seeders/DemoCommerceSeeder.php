<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Commerce\SubscriptionPlan;
use App\Models\Commerce\Subscription;
use App\Models\Commerce\SubscriptionInvoice;
use App\Models\Commerce\CommerceTransaction;
use App\Models\User;
use Carbon\Carbon;

class DemoCommerceSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->command->info('Creating demo commerce data...');

        // Create demo subscription plans
        $this->createDemoPlans();

        // Create demo subscriptions
        $this->createDemoSubscriptions();

        // Create demo invoices
        $this->createDemoInvoices();

        $this->command->info('Demo commerce data created successfully!');
    }

    private function createDemoPlans()
    {
        $plans = [
            [
                'name' => 'Starter Plan',
                'slug' => 'starter-plan',
                'interval' => 'monthly',
                'amount_cents' => 1999, // $19.99
                'currency' => 'usd',
                'trial_days' => 7,
                'metadata' => ['description' => 'Perfect for small businesses getting started'],
            ],
            [
                'name' => 'Professional Plan',
                'slug' => 'professional-plan',
                'interval' => 'monthly',
                'amount_cents' => 4999, // $49.99
                'currency' => 'usd',
                'trial_days' => 14,
                'metadata' => ['description' => 'Advanced features for growing businesses'],
            ],
            [
                'name' => 'Enterprise Plan',
                'slug' => 'enterprise-plan',
                'interval' => 'yearly',
                'amount_cents' => 99999, // $999.99
                'currency' => 'usd',
                'trial_days' => 30,
                'metadata' => ['description' => 'Full-featured solution for large organizations'],
            ],
        ];

        foreach ($plans as $planData) {
            SubscriptionPlan::firstOrCreate(
                [
                    'tenant_id' => 1,
                    'slug' => $planData['slug'],
                ],
                array_merge($planData, [
                    'tenant_id' => 1,
                    'active' => true,
                ])
            );
        }
    }

    private function createDemoSubscriptions()
    {
        $plans = SubscriptionPlan::all();
        $demoCustomers = [
            ['name' => 'John Smith', 'email' => 'john.smith@example.com'],
            ['name' => 'Sarah Johnson', 'email' => 'sarah.johnson@example.com'],
            ['name' => 'Mike Wilson', 'email' => 'mike.wilson@example.com'],
            ['name' => 'Emily Davis', 'email' => 'emily.davis@example.com'],
            ['name' => 'David Brown', 'email' => 'david.brown@example.com'],
        ];

        foreach ($demoCustomers as $index => $customer) {
            $plan = $plans->random();
            $user = User::firstOrCreate(
                ['email' => $customer['email']],
                [
                    'name' => $customer['name'],
                    'tenant_id' => 1,
                    'password' => bcrypt('demo_password'),
                    'email_verified_at' => now(),
                ]
            );

            $now = now();
            $statuses = ['active', 'trialing', 'past_due', 'cancelled'];
            $status = $statuses[array_rand($statuses)];

            $trialEndsAt = $plan->trial_days > 0 ? $now->copy()->addDays($plan->trial_days) : null;
            $currentPeriodStart = $trialEndsAt ? $trialEndsAt : $now->copy()->subDays(rand(1, 30));
            $currentPeriodEnd = $currentPeriodStart->copy()->addMonths($plan->interval === 'yearly' ? 12 : 1);

            $subscription = Subscription::create([
                'tenant_id' => 1,
                'user_id' => $user->id,
                'customer_id' => 'demo_customer_' . ($index + 1),
                'stripe_customer_id' => 'cus_demo_' . ($index + 1),
                'stripe_subscription_id' => 'sub_demo_' . ($index + 1),
                'plan_id' => $plan->id,
                'status' => $status,
                'current_period_start' => $currentPeriodStart,
                'current_period_end' => $currentPeriodEnd,
                'cancel_at_period_end' => $status === 'cancelled',
                'trial_ends_at' => $trialEndsAt,
                'metadata' => [
                    'demo_mode' => true,
                    'created_via' => 'demo_seeder',
                    'customer_name' => $customer['name'],
                ],
            ]);

            // Create demo invoices for this subscription
            $this->createDemoInvoicesForSubscription($subscription);
        }
    }

    private function createDemoInvoicesForSubscription($subscription)
    {
        $monthsBack = rand(1, 6);
        
        for ($i = 0; $i < $monthsBack; $i++) {
            $invoiceDate = now()->subMonths($i);
            
            SubscriptionInvoice::create([
                'tenant_id' => $subscription->tenant_id,
                'subscription_id' => $subscription->id,
                'stripe_invoice_id' => 'in_demo_' . $subscription->id . '_' . $i,
                'amount_cents' => $subscription->plan->amount_cents,
                'currency' => $subscription->plan->currency,
                'status' => 'paid',
                'paid_at' => $invoiceDate,
                'raw_payload' => [
                    'demo_mode' => true,
                    'amount_paid' => $subscription->plan->amount_cents,
                    'currency' => $subscription->plan->currency,
                ],
            ]);

            // Create transaction record
            CommerceTransaction::create([
                'tenant_id' => $subscription->tenant_id,
                'subscription_id' => $subscription->id,
                'amount' => $subscription->plan->amount_cents / 100,
                'currency' => $subscription->plan->currency,
                'status' => 'completed',
                'payment_provider' => 'demo',
                'provider' => 'demo',
                'provider_event_id' => 'evt_demo_' . $subscription->id . '_' . $i,
                'event_type' => 'invoice.payment_succeeded',
                'transaction_type' => 'subscription_payment',
                'metadata' => json_encode([
                    'demo_mode' => true,
                    'subscription_id' => $subscription->id,
                ]),
            ]);
        }
    }

    private function createDemoInvoices()
    {
        // This method is called by createDemoSubscriptions
        // Additional standalone invoices can be created here if needed
    }
}
