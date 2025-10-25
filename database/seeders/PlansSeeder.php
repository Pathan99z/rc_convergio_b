<?php

namespace Database\Seeders;

use App\Models\Plan;
use Illuminate\Database\Seeder;

class PlansSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $plans = [
            [
                'name' => 'Free',
                'description' => 'Free trial plan with basic features',
                'duration_days' => 14,
                'price' => 0.00,
                'features' => [
                    'Basic CRM functionality',
                    'Up to 100 contacts',
                    'Email support',
                    'Basic reporting',
                ],
                'is_active' => true,
                'sort_order' => 1,
            ],
            [
                'name' => 'Pro',
                'description' => 'Professional plan with advanced features',
                'duration_days' => 30,
                'price' => 29.99,
                'features' => [
                    'Advanced CRM functionality',
                    'Up to 1,000 contacts',
                    'Priority email support',
                    'Advanced reporting and analytics',
                    'Custom integrations',
                    'Team collaboration tools',
                ],
                'is_active' => true,
                'sort_order' => 2,
            ],
            [
                'name' => 'Enterprise',
                'description' => 'Enterprise plan with unlimited features',
                'duration_days' => 365,
                'price' => 99.99,
                'features' => [
                    'Full CRM functionality',
                    'Unlimited contacts',
                    '24/7 phone and email support',
                    'Advanced reporting and analytics',
                    'Custom integrations',
                    'Team collaboration tools',
                    'API access',
                    'Custom branding',
                    'Advanced security features',
                ],
                'is_active' => true,
                'sort_order' => 3,
            ],
        ];

        foreach ($plans as $planData) {
            Plan::updateOrCreate(
                ['name' => $planData['name']],
                $planData
            );
        }
    }
}