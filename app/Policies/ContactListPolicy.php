<?php

namespace App\Policies;

use App\Models\ContactList;
use App\Models\User;

class ContactListPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return true; // Allow all authenticated users to view lists
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, ContactList $list): bool
    {
        // Allow if user is the creator or tenant owner
        return $user->id === $list->created_by || $user->id === $list->tenant_id;
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return true; // Allow all authenticated users to create lists
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, ContactList $list): bool
    {
        // Allow if user is the creator or tenant owner
        return $user->id === $list->created_by || $user->id === $list->tenant_id;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, ContactList $list): bool
    {
        // Allow if user is the creator or tenant owner
        return $user->id === $list->created_by || $user->id === $list->tenant_id;
    }
}
