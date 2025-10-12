<?php

namespace App\Policies;

use App\Models\Event;
use App\Models\User;
use App\Policies\Concerns\ChecksTenantAndTeam;
use Illuminate\Auth\Access\HandlesAuthorization;

class EventPolicy
{
    use HandlesAuthorization, ChecksTenantAndTeam;

    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return true; // Users can view events in their tenant
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Event $event): bool
    {
        return $this->tenantAndTeamCheck($user, $event);
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return true; // Users can create events in their tenant
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Event $event): bool
    {
        return $this->tenantAndTeamCheck($user, $event);
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Event $event): bool
    {
        return $this->tenantAndTeamCheck($user, $event);
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, Event $event): bool
    {
        return $user->tenant_id === $event->tenant_id;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, Event $event): bool
    {
        return $user->tenant_id === $event->tenant_id;
    }

    /**
     * Determine whether the user can add attendees to the event.
     */
    public function addAttendee(User $user, Event $event): bool
    {
        return $user->tenant_id === $event->tenant_id;
    }

    /**
     * Determine whether the user can view attendees.
     */
    public function viewAttendees(User $user, Event $event): bool
    {
        return $user->tenant_id === $event->tenant_id;
    }

    /**
     * Determine whether the user can mark attendance.
     */
    public function markAttendance(User $user, Event $event): bool
    {
        return $user->tenant_id === $event->tenant_id;
    }
}








