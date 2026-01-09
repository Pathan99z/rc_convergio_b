<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Collateral;
use App\Policies\Concerns\ChecksTenantAndTeam;

class CollateralPolicy
{
    use ChecksTenantAndTeam;

    /**
     * Determine whether the user can view any collaterals.
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can view the collateral.
     */
    public function view(User $user, Collateral $collateral): bool
    {
        return $this->tenantAndTeamCheck($user, $collateral);
    }

    /**
     * Determine whether the user can create collaterals.
     */
    public function create(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can update the collateral.
     */
    public function update(User $user, Collateral $collateral): bool
    {
        return $this->tenantAndTeamCheck($user, $collateral);
    }

    /**
     * Determine whether the user can delete the collateral.
     */
    public function delete(User $user, Collateral $collateral): bool
    {
        return $this->tenantAndTeamCheck($user, $collateral);
    }

    /**
     * Determine whether the user can send collaterals.
     */
    public function send(User $user): bool
    {
        return true;
    }
}

