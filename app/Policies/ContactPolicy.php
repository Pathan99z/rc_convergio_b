<?php

namespace App\Policies;

use App\Models\Contact;
use App\Models\User;

class ContactPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Contact $contact): bool
    {
        // Allow users to view contacts they own or if they have specific permissions
        return $user->id === $contact->owner_id || $user->can('contacts.view');
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, Contact $contact): bool
    {
        // Allow users to update contacts they own or if they have specific permissions
        return $user->id === $contact->owner_id || $user->can('contacts.update');
    }

    public function delete(User $user, Contact $contact): bool
    {
        // Allow users to delete contacts they own or if they have specific permissions
        return $user->id === $contact->owner_id || $user->can('contacts.delete');
    }
}


