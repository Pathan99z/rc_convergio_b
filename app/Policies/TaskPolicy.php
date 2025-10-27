<?php

namespace App\Policies;

use App\Models\Task;
use App\Models\User;
use App\Policies\Concerns\ChecksTenantAndTeam;
use Illuminate\Auth\Access\HandlesAuthorization;

class TaskPolicy
{
    use HandlesAuthorization, ChecksTenantAndTeam;
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Task $task): bool
    {
        // Allow users to view tasks they own, are assigned to, or if they have specific permissions
        if ($user->id === $task->owner_id || 
            $user->id === $task->assigned_to || 
            $user->can('tasks.view')) {
            return true;
        }

        // Additional team fallback check if team access is enabled
        return $this->tenantAndTeamCheck($user, $task);
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, Task $task): bool
    {
        // Allow users to update tasks they own, are assigned to, or if they have specific permissions
        if ($user->id === $task->owner_id || 
            $user->id === $task->assigned_to || 
            $user->can('tasks.update')) {
            return true;
        }

        // Additional team fallback check if team access is enabled
        return $this->tenantAndTeamCheck($user, $task);
    }

    public function delete(User $user, Task $task): bool
    {
        return true; // Allow all authenticated users to delete tasks
    }

    public function complete(User $user, Task $task): bool
    {
        return true; // Allow all authenticated users to complete tasks
    }

    public function updateAny(User $user): bool
    {
        return true; // Allow bulk update operations
    }

    public function deleteAny(User $user): bool
    {
        return true; // Allow bulk delete operations
    }

    public function createAny(User $user): bool
    {
        return true; // Allow bulk create operations
    }
}
