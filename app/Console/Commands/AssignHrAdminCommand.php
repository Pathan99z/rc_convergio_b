<?php

namespace App\Console\Commands;

use App\Models\User;
use Spatie\Permission\Models\Role;
use Illuminate\Console\Command;

class AssignHrAdminCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'hr-admin:assign {email : The email of the user to assign HR admin role}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Assign HR admin role to a user by email';

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

        // Get or create hr_admin role
        $hrAdminRole = Role::firstOrCreate(
            ['name' => 'hr_admin'],
            ['name' => 'hr_admin', 'guard_name' => 'web']
        );

        // Check if user already has the role
        if ($user->hasRole('hr_admin')) {
            $this->warn("User '{$email}' already has HR admin role!");
            $this->info("Current roles: " . $user->roles->pluck('name')->join(', '));
            return Command::SUCCESS;
        }

        // Assign the role (this ADDS the role, doesn't replace existing roles)
        $user->assignRole($hrAdminRole);

        $this->info("HR admin role assigned to: {$user->name} ({$email})");
        $this->info("Current roles: " . $user->roles->pluck('name')->join(', '));

        return Command::SUCCESS;
    }
}


