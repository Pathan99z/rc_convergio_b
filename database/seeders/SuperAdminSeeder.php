<?php

namespace Database\Seeders;

use App\Models\User;
use Spatie\Permission\Models\Role;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class SuperAdminSeeder extends Seeder
{
    /**
     * Run the database seeds.
     * This seeder creates the super_admin role and optionally assigns it to a user.
     */
    public function run(): void
    {
        // Create super_admin role if it doesn't exist
        $superAdminRole = Role::firstOrCreate(
            ['name' => 'super_admin'],
            ['name' => 'super_admin', 'guard_name' => 'web']
        );

        $this->command->info('Super Admin role created/verified successfully!');
        
        // Optionally assign to a user by email (set in .env or pass as argument)
        $superAdminEmail = env('SUPER_ADMIN_EMAIL');
        
        if ($superAdminEmail) {
            $user = User::where('email', $superAdminEmail)->first();
            
            if ($user) {
                if (!$user->hasRole('super_admin')) {
                    $user->assignRole($superAdminRole);
                    $this->command->info("Super Admin role assigned to: {$user->email}");
                } else {
                    $this->command->info("User {$user->email} already has super_admin role");
                }
            } else {
                $this->command->warn("User with email {$superAdminEmail} not found!");
            }
        } else {
            $this->command->info('No SUPER_ADMIN_EMAIL set in .env. Use artisan command to assign role.');
        }
    }
}

