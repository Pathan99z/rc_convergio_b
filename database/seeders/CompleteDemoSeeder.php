<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\Commerce\SubscriptionPlan;
use App\Models\Commerce\Subscription;
use App\Models\Commerce\SubscriptionInvoice;
use App\Models\Commerce\CommerceTransaction;
use App\Models\TenantBranding;
use Illuminate\Support\Facades\Hash;

class CompleteDemoSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->command->info('Creating complete demo data for client presentation...');

        // Create demo tenant branding
        $this->createDemoBranding();

        // Create demo subscription plans
        $this->createDemoPlans();

        // Create demo subscriptions with various statuses
        $this->createDemoSubscriptions();

        // Create demo invoices
        $this->createDemoInvoices();

        $this->command->info('Complete demo data created successfully!');
    }

    private function createDemoBranding()
    {
        $this->command->info('Creating demo tenant branding...');

        TenantBranding::updateOrCreate(
            ['tenant_id' => 1],
            [
                'company_name' => 'RC Convergio Solutions',
                'company_email' => 'billing@rcconvergio.com',
                'company_phone' => '+1 (555) 123-4567',
                'company_website' => 'https://rcconvergio.com',
                'company_address' => '123 Business Street, Suite 100, New York, NY 10001',
                'primary_color' => '#3b82f6',
                'secondary_color' => '#1f2937',
                'invoice_footer' => 'Thank you for your business! For support, contact us at support@rcconvergio.com',
                'email_signature' => 'Best regards,<br>The RC Convergio Team',
                'active' => true,
            ]
        );
    }

    private function createDemoPlans()
    {
        $this->command->info('Creating demo subscription plans...');

        $plans = [
            [
                'name' => 'Starter Plan',
                'slug' => 'starter-plan',
                'interval' => 'monthly',
                'amount_cents' => 1999,
                'currency' => 'usd',
                'trial_days' => 14,
                'metadata' => [
                    'description' => 'Perfect for small businesses getting started',
                    'features' => ['Up to 5 users', 'Basic analytics', 'Email support']
                ]
            ],
            [
                'name' => 'Professional Plan',
                'slug' => 'professional-plan',
                'interval' => 'monthly',
                'amount_cents' => 4999,
                'currency' => 'usd',
                'trial_days' => 14,
                'metadata' => [
                    'description' => 'Advanced features for growing businesses',
                    'features' => ['Up to 25 users', 'Advanced analytics', 'Priority support', 'API access']
                ]
            ],
            [
                'name' => 'Enterprise Plan',
                'slug' => 'enterprise-plan',
                'interval' => 'yearly',
                'amount_cents' => 99999,
                'currency' => 'usd',
                'trial_days' => 30,
                'metadata' => [
                    'description' => 'Full-featured solution for large organizations',
                    'features' => ['Unlimited users', 'Custom analytics', '24/7 support', 'Custom integrations']
                ]
            ]
        ];

        foreach ($plans as $planData) {
            SubscriptionPlan::firstOrCreate(
                [
                    'tenant_id' => 1,
                    'slug' => $planData['slug']
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
        $this->command->info('Creating demo subscriptions...');

        $plans = SubscriptionPlan::where('tenant_id', 1)->get();
        
        if ($plans->isEmpty()) {
            $this->command->warn('No plans found. Creating plans first...');
            $this->createDemoPlans();
            $plans = SubscriptionPlan::where('tenant_id', 1)->get();
        }

        $subscriptions = [
            [
                'customer_id' => 'demo_customer_john_doe',
                'status' => 'active',
                'current_period_start' => now()->subDays(10),
                'current_period_end' => now()->addDays(20),
                'trial_ends_at' => now()->subDays(5),
                'metadata' => [
                    'demo' => true,
                    'created_by' => 'demo_seeder',
                    'customer_email' => 'john.doe@example.com',
                    'customer_name' => 'John Doe'
                ]
            ],
            [
                'customer_id' => 'demo_customer_jane_smith',
                'status' => 'trialing',
                'current_period_start' => now()->subDays(5),
                'current_period_end' => now()->addDays(25),
                'trial_ends_at' => now()->addDays(9),
                'metadata' => [
                    'demo' => true,
                    'created_by' => 'demo_seeder',
                    'customer_email' => 'jane.smith@example.com',
                    'customer_name' => 'Jane Smith'
                ]
            ],
            [
                'customer_id' => 'demo_customer_bob_wilson',
                'status' => 'past_due',
                'current_period_start' => now()->subDays(15),
                'current_period_end' => now()->subDays(5),
                'trial_ends_at' => now()->subDays(20),
                'metadata' => [
                    'demo' => true,
                    'created_by' => 'demo_seeder',
                    'customer_email' => 'bob.wilson@example.com',
                    'customer_name' => 'Bob Wilson'
                ]
            ],
            [
                'customer_id' => 'demo_customer_alice_brown',
                'status' => 'canceled',
                'current_period_start' => now()->subDays(30),
                'current_period_end' => now()->subDays(20),
                'trial_ends_at' => now()->subDays(35),
                'cancel_at' => now()->subDays(20),
                'metadata' => [
                    'demo' => true,
                    'created_by' => 'demo_seeder',
                    'customer_email' => 'alice.brown@example.com',
                    'customer_name' => 'Alice Brown'
                ]
            ]
        ];

        foreach ($subscriptions as $index => $subscriptionData) {
            $plan = $plans->get($index % $plans->count());
            
            Subscription::create(array_merge($subscriptionData, [
                'tenant_id' => 1,
                'plan_id' => $plan->id,
                'stripe_customer_id' => 'demo_customer_' . time() . '_' . $index,
                'stripe_subscription_id' => 'demo_sub_' . time() . '_' . $index,
            ]));
        }
    }

    private function createDemoInvoices()
    {
        $this->command->info('Creating demo invoices...');

        $subscriptions = Subscription::where('tenant_id', 1)->get();

        foreach ($subscriptions as $subscription) {
            // Create multiple invoices for each subscription
            $invoiceCount = rand(1, 3);
            
            for ($i = 0; $i < $invoiceCount; $i++) {
                $invoiceDate = $subscription->current_period_start->copy()->subMonths($i);
                $isPaid = $subscription->status !== 'past_due' && $subscription->status !== 'canceled';
                
                $invoice = SubscriptionInvoice::create([
                    'tenant_id' => 1,
                    'subscription_id' => $subscription->id,
                    'stripe_invoice_id' => 'demo_inv_' . time() . '_' . $subscription->id . '_' . $i,
                    'amount_cents' => $subscription->plan->amount_cents,
                    'currency' => $subscription->plan->currency,
                    'status' => $isPaid ? 'paid' : 'open',
                    'paid_at' => $isPaid ? $invoiceDate->copy()->addDays(rand(1, 5)) : null,
                    'raw_payload' => [
                        'demo' => true,
                        'period_start' => $invoiceDate->toISOString(),
                        'period_end' => $invoiceDate->copy()->addMonth()->toISOString(),
                        'due_date' => $invoiceDate->copy()->addDays(30)->toISOString(),
                    ]
                ]);

                // Create transaction record
                if ($isPaid) {
                    CommerceTransaction::create([
                        'tenant_id' => 1,
                        'amount' => $subscription->plan->amount_cents / 100,
                        'currency' => $subscription->plan->currency,
                        'status' => 'completed',
                        'payment_provider' => 'demo',
                        'provider_event_id' => 'demo_txn_' . time() . '_' . $subscription->id . '_' . $i,
                        'event_type' => 'subscription_payment',
                        'raw_payload' => [
                            'demo' => true,
                            'invoice_id' => $invoice->id,
                            'subscription_id' => $subscription->id,
                        ]
                    ]);
                }
            }
        }
    }
}
