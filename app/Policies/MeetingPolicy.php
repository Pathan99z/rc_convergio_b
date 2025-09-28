<?php

namespace App\Policies;

use App\Models\Meeting;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class MeetingPolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return true; // All authenticated users can view meetings
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Meeting $meeting): bool
    {
        return $meeting->tenant_id === $user->tenant_id;
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return true; // All authenticated users can create meetings
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Meeting $meeting): bool
    {
        return $meeting->tenant_id === $user->tenant_id;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Meeting $meeting): bool
    {
        return $meeting->tenant_id === $user->tenant_id;
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, Meeting $meeting): bool
    {
        return $meeting->tenant_id === $user->tenant_id;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, Meeting $meeting): bool
    {
        return $meeting->tenant_id === $user->tenant_id;
    }

    /**
     * Determine whether the user can update the meeting status.
     */
    public function updateStatus(User $user, Meeting $meeting): bool
    {
        return $meeting->tenant_id === $user->tenant_id;
    }

    /**
     * Determine whether the user can sync meetings.
     */
    public function sync(User $user): bool
    {
        return true; // All authenticated users can sync meetings
    }
}
