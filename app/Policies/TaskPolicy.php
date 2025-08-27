<?php

namespace App\Policies;

use App\Models\Task;
use App\Models\User;

class TaskPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Task $task): bool
    {
        // Allow users to view tasks they own, are assigned to, or if they have specific permissions
        return $user->id === $task->owner_id || 
               $user->id === $task->assigned_to || 
               $user->can('tasks.view');
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, Task $task): bool
    {
        return true; // Allow all authenticated users to update tasks
    }

    public function delete(User $user, Task $task): bool
    {
        return true; // Allow all authenticated users to delete tasks
    }

    public function complete(User $user, Task $task): bool
    {
        return true; // Allow all authenticated users to complete tasks
    }
}
