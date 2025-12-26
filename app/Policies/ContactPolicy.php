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
        return $this->canAccessContact($user, $contact);
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, Contact $contact): bool
    {
        return $this->canAccessContact($user, $contact);
    }

    public function delete(User $user, Contact $contact): bool
    {
        return $this->canAccessContact($user, $contact);
    }

    /**
     * Check if user can access a contact.
     * Super admin can access any contact, otherwise user must be in the same tenant.
     *
     * @param User $user
     * @param Contact $contact
     * @return bool
     */
    private function canAccessContact(User $user, Contact $contact): bool
    {
        // Super admin can access any contact
        if ($user->isSuperAdmin()) {
            return true;
        }
        
        // Allow users to access contacts within their tenant (including contacts assigned by assignment rules)
        return $user->tenant_id === $contact->tenant_id;
    }
}


