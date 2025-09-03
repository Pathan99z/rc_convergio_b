<?php

namespace Database\Seeders;

use Spatie\Permission\Models\Role;
use Illuminate\Database\Seeder;

class RoleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $roles = [
            'admin' => 'Full system access and user management',
            'sales_rep' => 'Manage contacts, deals, and activities',
            'manager' => 'Team management and reporting access',
        ];

        foreach ($roles as $name => $description) {
            Role::firstOrCreate(
                ['name' => $name],
                ['name' => $name, 'guard_name' => 'web']
            );
        }
    }
}
