<?php

require_once 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Spatie\Permission\Models\Role;
use App\Models\User;

// Get email from command line argument, or use default for this request
$email = isset($argv[1]) ? $argv[1] : 'preranjathan2nd@gmail.com';

// Find user by email
$user = User::where('email', $email)->first();
$role = Role::where('name', 'admin')->first();

if ($user && $role) {
    $user->assignRole($role);
    echo "User {$user->name} (ID: {$user->id}) is now admin!\n";
    echo "Email: {$user->email}\n";
} else {
    echo "Error: User or admin role not found\n";
    if (!$user) echo "User with email {$email} not found\n";
    if (!$role) echo "Admin role not found\n";
}
