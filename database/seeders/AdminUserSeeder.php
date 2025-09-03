<?php

namespace Database\Seeders;

use App\Models\User;
use Spatie\Permission\Models\Role;
use Illuminate\Database\Seeder;

class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        // Make specific users admin by email
        $adminEmails = [
            'jon@example.com',
            'admin@company.com',
            // Add more emails here
        ];

        $adminRole = Role::where('name', 'admin')->first();

        if (!$adminRole) {
            $this->command->error('Admin role not found! Run RoleSeeder first.');
            return;
        }

        foreach ($adminEmails as $email) {
            $user = User::where('email', $email)->first();
            
            if ($user) {
                $user->assignRole($adminRole);
                $this->command->info("User {$user->name} ({$email}) is now admin!");
            } else {
                $this->command->warn("User with email {$email} not found!");
            }
        }
    }
}
