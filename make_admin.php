<?php

require_once 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Spatie\Permission\Models\Role;
use App\Models\User;

// Make user ID 1 an admin
$user = User::find(1);
$role = Role::where('name', 'admin')->first();

if ($user && $role) {
    $user->assignRole($role);
    echo "User {$user->name} (ID: {$user->id}) is now admin!\n";
    echo "Email: {$user->email}\n";
} else {
    echo "Error: User or admin role not found\n";
    if (!$user) echo "User ID 1 not found\n";
    if (!$role) echo "Admin role not found\n";
}
