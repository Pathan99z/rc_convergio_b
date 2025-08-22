<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Company;
use App\Models\User;

class CompanySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Get the first user as owner
        $user = User::first();
        
        if (!$user) {
            $this->command->error('No users found. Please run UserSeeder first.');
            return;
        }

        $companies = [
            [
                'name' => 'Tech Solutions Inc.',
                'domain' => 'techsolutions.com',
                'website' => 'https://techsolutions.com',
                'industry' => 'Technology',
                'size' => 150,
                'type' => 'Private',
                'address' => [
                    'street' => '123 Tech Street',
                    'city' => 'San Francisco',
                    'state' => 'CA',
                    'postal_code' => '94105',
                    'country' => 'USA'
                ],
                'annual_revenue' => 5000000,
                'timezone' => 'America/Los_Angeles',
                'description' => 'Leading technology solutions provider',
                'linkedin_page' => 'https://linkedin.com/company/techsolutions',
                'phone' => '+1-555-0123',
                'email' => 'contact@techsolutions.com',
                'status' => 'active'
            ],
            [
                'name' => 'Healthcare Plus',
                'domain' => 'healthcareplus.com',
                'website' => 'https://healthcareplus.com',
                'industry' => 'Healthcare',
                'size' => 350,
                'type' => 'Public',
                'address' => [
                    'street' => '456 Health Avenue',
                    'city' => 'Boston',
                    'state' => 'MA',
                    'postal_code' => '02108',
                    'country' => 'USA'
                ],
                'annual_revenue' => 15000000,
                'timezone' => 'America/New_York',
                'description' => 'Comprehensive healthcare services',
                'linkedin_page' => 'https://linkedin.com/company/healthcareplus',
                'phone' => '+1-555-0456',
                'email' => 'info@healthcareplus.com',
                'status' => 'active'
            ],
            [
                'name' => 'Global Finance Corp',
                'domain' => 'globalfinance.com',
                'website' => 'https://globalfinance.com',
                'industry' => 'Finance',
                'size' => 1000,
                'type' => 'Public',
                'address' => [
                    'street' => '789 Finance Boulevard',
                    'city' => 'New York',
                    'state' => 'NY',
                    'postal_code' => '10001',
                    'country' => 'USA'
                ],
                'annual_revenue' => 50000000,
                'timezone' => 'America/New_York',
                'description' => 'International financial services',
                'linkedin_page' => 'https://linkedin.com/company/globalfinance',
                'phone' => '+1-555-0789',
                'email' => 'contact@globalfinance.com',
                'status' => 'active'
            ],
            [
                'name' => 'EduTech Solutions',
                'domain' => 'edutechsolutions.com',
                'website' => 'https://edutechsolutions.com',
                'industry' => 'Education',
                'size' => 25,
                'type' => 'Private',
                'address' => [
                    'street' => '321 Education Drive',
                    'city' => 'Austin',
                    'state' => 'TX',
                    'postal_code' => '73301',
                    'country' => 'USA'
                ],
                'annual_revenue' => 2000000,
                'timezone' => 'America/Chicago',
                'description' => 'Innovative educational technology',
                'linkedin_page' => 'https://linkedin.com/company/edutechsolutions',
                'phone' => '+1-555-0321',
                'email' => 'hello@edutechsolutions.com',
                'status' => 'active'
            ],
            [
                'name' => 'Manufacturing Pro',
                'domain' => 'manufacturingpro.com',
                'website' => 'https://manufacturingpro.com',
                'industry' => 'Manufacturing',
                'size' => 300,
                'type' => 'Private',
                'address' => [
                    'street' => '654 Industrial Way',
                    'city' => 'Detroit',
                    'state' => 'MI',
                    'postal_code' => '48201',
                    'country' => 'USA'
                ],
                'annual_revenue' => 25000000,
                'timezone' => 'America/Detroit',
                'description' => 'Advanced manufacturing solutions',
                'linkedin_page' => 'https://linkedin.com/company/manufacturingpro',
                'phone' => '+1-555-0654',
                'email' => 'info@manufacturingpro.com',
                'status' => 'active'
            ]
        ];

        foreach ($companies as $companyData) {
            Company::create([
                'tenant_id' => 1,
                'owner_id' => $user->id,
                ...$companyData
            ]);
        }

        $this->command->info('Companies seeded successfully!');
    }
}
