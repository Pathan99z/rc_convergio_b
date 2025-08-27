<?php

namespace App\Policies;

use App\Models\Stage;
use App\Models\User;

class StagePolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Stage $stage): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return true; // Allow all authenticated users to create stages
    }

    public function update(User $user, Stage $stage): bool
    {
        return true; // Allow all authenticated users to update stages
    }

    public function delete(User $user, Stage $stage): bool
    {
        return true; // Allow all authenticated users to delete stages
    }
}
