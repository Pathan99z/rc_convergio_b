<?php

namespace App\Policies;

use App\Models\Activity;
use App\Models\User;

class ActivityPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Activity $activity): bool
    {
        // Allow users to view activities they own or if they have specific permissions
        return $user->id === $activity->owner_id || $user->can('activities.view');
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, Activity $activity): bool
    {
        return true; // Allow all authenticated users to update activities
    }

    public function delete(User $user, Activity $activity): bool
    {
        return true; // Allow all authenticated users to delete activities
    }

    public function deleteAny(User $user): bool
    {
        return true; // Allow bulk delete operations
    }

    public function updateAny(User $user): bool
    {
        return true; // Allow bulk update operations
    }

    public function createAny(User $user): bool
    {
        return true; // Allow bulk create operations
    }
}
