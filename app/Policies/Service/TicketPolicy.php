<?php

namespace App\Policies\Service;

use App\Models\Service\Ticket;
use App\Models\User;
use App\Policies\Concerns\ChecksTenantAndTeam;
use Illuminate\Auth\Access\Response;

class TicketPolicy
{
    use ChecksTenantAndTeam;

    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return true; // HasTenantScope automatically filters by tenant
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Ticket $ticket): bool
    {
        // Basic tenant check
        if (!$this->tenantAndTeamCheck($user, $ticket)) {
            return false;
        }

        // Allow if user is admin
        if ($user->hasRole('admin')) {
            return true;
        }

        // Allow if user is assigned to the ticket
        if ($ticket->assignee_id === $user->id) {
            return true;
        }

        // Allow if user is in the same team as the ticket
        if ($ticket->team_id && $ticket->team_id === $user->team_id) {
            return true;
        }

        // Allow if user has service role
        if ($user->hasRole('service_agent') || $user->hasRole('service_manager')) {
            return true;
        }

        return false;
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        // Allow if user has service role or is admin
        return $user->hasRole('admin') || 
               $user->hasRole('service_agent') || 
               $user->hasRole('service_manager');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Ticket $ticket): bool
    {
        // Basic tenant check
        if (!$this->tenantAndTeamCheck($user, $ticket)) {
            return false;
        }

        // Allow if user is admin
        if ($user->hasRole('admin')) {
            return true;
        }

        // Allow if user is assigned to the ticket
        if ($ticket->assignee_id === $user->id) {
            return true;
        }

        // Allow if user is service manager
        if ($user->hasRole('service_manager')) {
            return true;
        }

        return false;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Ticket $ticket): bool
    {
        // Basic tenant check
        if (!$this->tenantAndTeamCheck($user, $ticket)) {
            return false;
        }

        // Only admin and service manager can delete tickets
        return $user->hasRole('admin') || $user->hasRole('service_manager');
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, Ticket $ticket): bool
    {
        // Basic tenant check
        if (!$this->tenantAndTeamCheck($user, $ticket)) {
            return false;
        }

        // Only admin and service manager can restore tickets
        return $user->hasRole('admin') || $user->hasRole('service_manager');
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, Ticket $ticket): bool
    {
        // Basic tenant check
        if (!$this->tenantAndTeamCheck($user, $ticket)) {
            return false;
        }

        // Only admin can permanently delete tickets
        return $user->hasRole('admin');
    }

    /**
     * Determine whether the user can assign the ticket.
     */
    public function assign(User $user, Ticket $ticket): bool
    {
        // Basic tenant check
        if (!$this->tenantAndTeamCheck($user, $ticket)) {
            return false;
        }

        // Allow if user is admin or service manager
        return $user->hasRole('admin') || $user->hasRole('service_manager');
    }

    /**
     * Determine whether the user can close the ticket.
     */
    public function close(User $user, Ticket $ticket): bool
    {
        // Basic tenant check
        if (!$this->tenantAndTeamCheck($user, $ticket)) {
            return false;
        }

        // Allow if user is admin
        if ($user->hasRole('admin')) {
            return true;
        }

        // Allow if user is assigned to the ticket
        if ($ticket->assignee_id === $user->id) {
            return true;
        }

        // Allow if user is service manager
        if ($user->hasRole('service_manager')) {
            return true;
        }

        return false;
    }

    /**
     * Determine whether the user can reply to the ticket.
     */
    public function reply(User $user, Ticket $ticket): bool
    {
        // Basic tenant check
        if (!$this->tenantAndTeamCheck($user, $ticket)) {
            return false;
        }

        // Allow if user is admin
        if ($user->hasRole('admin')) {
            return true;
        }

        // Allow if user is assigned to the ticket
        if ($ticket->assignee_id === $user->id) {
            return true;
        }

        // Allow if user is in the same team as the ticket
        if ($ticket->team_id && $ticket->team_id === $user->team_id) {
            return true;
        }

        // Allow if user has service role
        if ($user->hasRole('service_agent') || $user->hasRole('service_manager')) {
            return true;
        }

        return false;
    }

    /**
     * Determine whether the user can view internal messages.
     */
    public function viewInternal(User $user, Ticket $ticket): bool
    {
        // Basic tenant check
        if (!$this->tenantAndTeamCheck($user, $ticket)) {
            return false;
        }

        // Only service agents and above can view internal messages
        return $user->hasRole('admin') || 
               $user->hasRole('service_agent') || 
               $user->hasRole('service_manager');
    }

    /**
     * Determine whether the user can create public tickets (for customers).
     */
    public function publicCreate(User $user = null): bool
    {
        // Allow public ticket creation (no authentication required)
        return true;
    }
}
