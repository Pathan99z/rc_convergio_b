<?php

namespace App\Policies;

use App\Models\Stage;
use App\Models\User;
use App\Policies\Concerns\ChecksTenantAndTeam;
use Illuminate\Auth\Access\HandlesAuthorization;

class StagePolicy
{
    use HandlesAuthorization, ChecksTenantAndTeam;
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Stage $stage): bool
    {
        return $this->tenantAndTeamCheck($user, $stage);
    }

    public function create(User $user): bool
    {
        return true; // Allow all authenticated users to create stages
    }

    public function update(User $user, Stage $stage): bool
    {
        return $this->tenantAndTeamCheck($user, $stage);
    }

    public function delete(User $user, Stage $stage): bool
    {
        return $this->tenantAndTeamCheck($user, $stage);
    }
}
