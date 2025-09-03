<?php

require_once 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Spatie\Permission\Models\Role;
use App\Models\User;

// Make amrutha@example.com an admin
$user = User::where('email', 'amrutha@example.com')->first();
$role = Role::where('name', 'admin')->first();

if ($user && $role) {
    $user->assignRole($role);
    echo "✅ User {$user->name} (ID: {$user->id}) is now admin!\n";
    echo "📧 Email: {$user->email}\n";
    echo "🔑 You can now login and test all admin features!\n";
} else {
    echo "❌ Error: User or admin role not found\n";
    if (!$user) echo "User with email amrutha@example.com not found\n";
    if (!$role) echo "Admin role not found\n";
}
