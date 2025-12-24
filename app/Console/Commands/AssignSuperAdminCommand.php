<?php

namespace App\Console\Commands;

use App\Models\User;
use Spatie\Permission\Models\Role;
use Illuminate\Console\Command;

class AssignSuperAdminCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'super-admin:assign {email : The email of the user to assign super admin role}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Assign super admin role to a user by email';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $email = $this->argument('email');

        // Find the user
        $user = User::where('email', $email)->first();

        if (!$user) {
            $this->error("User with email '{$email}' not found!");
            return Command::FAILURE;
        }

        // Get or create super_admin role
        $superAdminRole = Role::firstOrCreate(
            ['name' => 'super_admin'],
            ['name' => 'super_admin', 'guard_name' => 'web']
        );

        // Check if user already has the role
        if ($user->hasRole('super_admin')) {
            $this->warn("User '{$email}' already has super admin role!");
            return Command::SUCCESS;
        }

        // Assign the role
        $user->assignRole($superAdminRole);

        $this->info("Super admin role assigned to: {$user->name} ({$email})");

        return Command::SUCCESS;
    }
}

