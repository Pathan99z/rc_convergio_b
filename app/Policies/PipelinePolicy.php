<?php

namespace App\Policies;

use App\Models\Pipeline;
use App\Models\User;

class PipelinePolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Pipeline $pipeline): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return true; // Allow all authenticated users to create pipelines
    }

    public function update(User $user, Pipeline $pipeline): bool
    {
        return true; // Allow all authenticated users to update pipelines
    }

    public function delete(User $user, Pipeline $pipeline): bool
    {
        return true; // Allow all authenticated users to delete pipelines
    }
}
