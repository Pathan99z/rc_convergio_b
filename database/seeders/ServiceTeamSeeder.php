<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\Team;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;

class ServiceTeamSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::transaction(function () {
            // Create Service Team
            $serviceTeam = Team::create([
                'name' => 'Service Team',
                'description' => 'Default service team for ticket assignment',
                'tenant_id' => 1, // Assuming tenant_id 1 exists
                'created_by' => 1, // Assuming user ID 1 exists
            ]);

            // Create Service Manager
            $serviceManager = User::create([
                'name' => 'Service Manager',
                'email' => 'service.manager@example.com',
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
                'tenant_id' => 1,
                'team_id' => $serviceTeam->id,
            ]);

            // Create Service Agent 1
            $serviceAgent1 = User::create([
                'name' => 'Service Agent 1',
                'email' => 'service.agent1@example.com',
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
                'tenant_id' => 1,
                'team_id' => $serviceTeam->id,
            ]);

            // Create Service Agent 2
            $serviceAgent2 = User::create([
                'name' => 'Service Agent 2',
                'email' => 'service.agent2@example.com',
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
                'tenant_id' => 1,
                'team_id' => $serviceTeam->id,
            ]);

            // Assign roles (assuming Spatie Permission is set up)
            if (class_exists(\Spatie\Permission\Models\Role::class)) {
                $serviceManagerRole = \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'service_manager']);
                $serviceAgentRole = \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'service_agent']);

                $serviceManager->assignRole($serviceManagerRole);
                $serviceAgent1->assignRole($serviceAgentRole);
                $serviceAgent2->assignRole($serviceAgentRole);
            }

            $this->command->info('Service Team created successfully!');
            $this->command->info('Team ID: ' . $serviceTeam->id);
            $this->command->info('Service Manager: ' . $serviceManager->email);
            $this->command->info('Service Agent 1: ' . $serviceAgent1->email);
            $this->command->info('Service Agent 2: ' . $serviceAgent2->email);
        });
    }
}
