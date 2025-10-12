<?php

namespace App\Policies;

use App\Models\Pipeline;
use App\Models\User;
use App\Policies\Concerns\ChecksTenantAndTeam;
use Illuminate\Auth\Access\HandlesAuthorization;

class PipelinePolicy
{
    use HandlesAuthorization, ChecksTenantAndTeam;
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Pipeline $pipeline): bool
    {
        return $this->tenantAndTeamCheck($user, $pipeline);
    }

    public function create(User $user): bool
    {
        return true; // Allow all authenticated users to create pipelines
    }

    public function update(User $user, Pipeline $pipeline): bool
    {
        return $this->tenantAndTeamCheck($user, $pipeline);
    }

    public function delete(User $user, Pipeline $pipeline): bool
    {
        return $this->tenantAndTeamCheck($user, $pipeline);
    }
}
