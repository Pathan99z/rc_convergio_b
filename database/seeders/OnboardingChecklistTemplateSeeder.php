<?php

namespace Database\Seeders;

use App\Constants\HrConstants;
use App\Models\Hr\OnboardingChecklistTemplate;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class OnboardingChecklistTemplateSeeder extends Seeder
{
    /**
     * Run the database seeds.
     * Creates default onboarding checklist templates for all tenants.
     */
    public function run(): void
    {
        // Get all tenant users (users who are tenants themselves)
        $tenants = User::whereNull('tenant_id')
            ->orWhereColumn('id', 'tenant_id')
            ->get();

        foreach ($tenants as $tenant) {
            $tenantId = $tenant->id;

            // Check if templates already exist for this tenant
            $existingCount = OnboardingChecklistTemplate::where('tenant_id', $tenantId)->count();
            if ($existingCount > 0) {
                $this->command->info("Templates already exist for tenant {$tenantId}. Skipping...");
                continue;
            }

            $defaultTemplates = [
                [
                    'name' => 'Documents Upload',
                    'category' => HrConstants::CHECKLIST_CATEGORY_HR,
                    'description' => 'Upload required documents: Employment Contract, ID Document, Tax Forms, Emergency Contact Form',
                    'is_required' => true,
                    'order' => 1,
                ],
                [
                    'name' => 'Bank Details',
                    'category' => HrConstants::CHECKLIST_CATEGORY_FINANCE,
                    'description' => 'Complete bank account details for salary payment',
                    'is_required' => true,
                    'order' => 2,
                ],
                [
                    'name' => 'ID Verification',
                    'category' => HrConstants::CHECKLIST_CATEGORY_HR,
                    'description' => 'Verify identity document (Passport/National ID)',
                    'is_required' => true,
                    'order' => 3,
                ],
                [
                    'name' => 'IT Setup',
                    'category' => HrConstants::CHECKLIST_CATEGORY_IT,
                    'description' => 'Complete IT setup: Email account, System access, Equipment assignment',
                    'is_required' => true,
                    'order' => 4,
                ],
                [
                    'name' => 'Policy Acknowledgment',
                    'category' => HrConstants::CHECKLIST_CATEGORY_HR,
                    'description' => 'Read and acknowledge company policies and procedures',
                    'is_required' => true,
                    'order' => 5,
                ],
            ];

            foreach ($defaultTemplates as $templateData) {
                OnboardingChecklistTemplate::create([
                    'tenant_id' => $tenantId,
                    'created_by' => $tenantId, // Use tenant ID as creator
                    'name' => $templateData['name'],
                    'category' => $templateData['category'],
                    'description' => $templateData['description'],
                    'is_required' => $templateData['is_required'],
                    'order' => $templateData['order'],
                    'is_active' => true,
                ]);
            }

            $this->command->info("Created default templates for tenant {$tenantId}");
        }

        $this->command->info('Onboarding checklist templates seeded successfully!');
    }
}
