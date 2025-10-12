<?php

namespace Database\Seeders;

use App\Models\AssignmentRule;
use App\Models\AssignmentDefault;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Log;

class AssignmentSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->command->info('Seeding assignment system...');

        // Get all tenants (users with tenant_id = 0 or their own ID)
        $tenants = User::where(function ($query) {
            $query->where('tenant_id', 0)
                  ->orWhereColumn('tenant_id', 'id');
        })->get();

        foreach ($tenants as $tenant) {
            $tenantId = $tenant->tenant_id === 0 ? $tenant->id : $tenant->tenant_id;
            
            $this->command->info("Creating assignment defaults for tenant: {$tenantId}");

            // Create default assignment settings for each tenant
            AssignmentDefault::firstOrCreate(
                ['tenant_id' => $tenantId],
                [
                    'default_user_id' => $tenant->id,
                    'round_robin_enabled' => false,
                    'enable_automatic_assignment' => true,
                ]
            );

            // Create default assignment rules for each tenant
            $this->createDefaultRules($tenantId, $tenant->id);
        }

        $this->command->info('Assignment system seeded successfully!');
    }

    /**
     * Create default assignment rules for a tenant.
     */
    private function createDefaultRules(int $tenantId, int $createdBy): void
    {
        // Rule 1: Assign US leads to specific user (if exists)
        $usUser = User::where('tenant_id', $tenantId)
                     ->where('email', 'like', '%@%')
                     ->first();

        if ($usUser) {
            AssignmentRule::firstOrCreate(
                [
                    'tenant_id' => $tenantId,
                    'name' => 'US Leads Assignment'
                ],
                [
                    'description' => 'Automatically assign leads from USA to the primary user',
                    'priority' => 10,
                    'criteria' => [
                        'all' => [
                            [
                                'field' => 'record_type',
                                'op' => 'eq',
                                'value' => 'contact'
                            ],
                            [
                                'field' => 'lifecycle_stage',
                                'op' => 'eq',
                                'value' => 'Lead'
                            ],
                            [
                                'field' => 'company_country',
                                'op' => 'eq',
                                'value' => 'USA'
                            ]
                        ]
                    ],
                    'action' => [
                        'type' => 'assign_user',
                        'user_id' => $usUser->id
                    ],
                    'active' => true,
                    'created_by' => $createdBy,
                ]
            );
        }

        // Rule 2: Assign high-value deals to primary user
        AssignmentRule::firstOrCreate(
            [
                'tenant_id' => $tenantId,
                'name' => 'High Value Deals Assignment'
            ],
            [
                'description' => 'Assign deals with value greater than $10,000 to the primary user',
                'priority' => 20,
                'criteria' => [
                    'all' => [
                        [
                            'field' => 'record_type',
                            'op' => 'eq',
                            'value' => 'deal'
                        ],
                        [
                            'field' => 'value',
                            'op' => 'gt',
                            'value' => 10000
                        ]
                    ]
                ],
                'action' => [
                    'type' => 'assign_user',
                    'user_id' => $createdBy
                ],
                'active' => true,
                'created_by' => $createdBy,
            ]
        );

        // Rule 3: Assign leads from specific sources
        AssignmentRule::firstOrCreate(
            [
                'tenant_id' => $tenantId,
                'name' => 'Website Leads Assignment'
            ],
            [
                'description' => 'Assign leads from website to primary user',
                'priority' => 30,
                'criteria' => [
                    'all' => [
                        [
                            'field' => 'record_type',
                            'op' => 'eq',
                            'value' => 'contact'
                        ],
                        [
                            'field' => 'source',
                            'op' => 'eq',
                            'value' => 'Website'
                        ]
                    ]
                ],
                'action' => [
                    'type' => 'assign_user',
                    'user_id' => $createdBy
                ],
                'active' => true,
                'created_by' => $createdBy,
            ]
        );

        // Rule 4: Round-robin assignment for remaining contacts
        AssignmentRule::firstOrCreate(
            [
                'tenant_id' => $tenantId,
                'name' => 'Default Round Robin Assignment'
            ],
            [
                'description' => 'Round-robin assignment for all remaining contacts',
                'priority' => 100,
                'criteria' => [
                    'all' => [
                        [
                            'field' => 'record_type',
                            'op' => 'eq',
                            'value' => 'contact'
                        ],
                        [
                            'field' => 'lifecycle_stage',
                            'op' => 'eq',
                            'value' => 'Lead'
                        ]
                    ]
                ],
                'action' => [
                    'type' => 'assign_team_round_robin',
                    'team_id' => 1 // Default team
                ],
                'active' => true,
                'created_by' => $createdBy,
            ]
        );

        Log::info('Default assignment rules created', [
            'tenant_id' => $tenantId,
            'created_by' => $createdBy
        ]);
    }
}
