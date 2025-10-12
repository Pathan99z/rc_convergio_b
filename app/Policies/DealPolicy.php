<?php

namespace App\Policies;

use App\Models\Deal;
use App\Models\User;
use App\Policies\Concerns\ChecksTenantAndTeam;
use Illuminate\Auth\Access\HandlesAuthorization;

class DealPolicy
{
    use HandlesAuthorization, ChecksTenantAndTeam;
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Deal $deal): bool
    {
        // Allow users to view deals they own or if they have specific permissions
        if ($user->id === $deal->owner_id || $user->can('deals.view')) {
            return true;
        }

        // Additional team fallback check if team access is enabled
        return $this->tenantAndTeamCheck($user, $deal);
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, Deal $deal): bool
    {
        // Allow users to update deals they own or if they have specific permissions
        if ($user->id === $deal->owner_id || $user->can('deals.update')) {
            return true;
        }

        // Additional team fallback check if team access is enabled
        return $this->tenantAndTeamCheck($user, $deal);
    }

    public function delete(User $user, Deal $deal): bool
    {
        return true; // Allow all authenticated users to delete deals
    }

    public function move(User $user, Deal $deal): bool
    {
        return true; // Allow all authenticated users to move deals
    }
}
