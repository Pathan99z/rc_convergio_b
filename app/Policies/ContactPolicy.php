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
        // Super admin can view any contact
        if ($user->isSuperAdmin()) {
            return true;
        }
        
        // Allow users to view contacts within their tenant (including contacts assigned by assignment rules)
        return $user->tenant_id === $contact->tenant_id;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, Contact $contact): bool
    {
        // Super admin can update any contact
        if ($user->isSuperAdmin()) {
            return true;
        }
        
        // Allow users to update contacts within their tenant (including contacts assigned by assignment rules)
        return $user->tenant_id === $contact->tenant_id;
    }

    public function delete(User $user, Contact $contact): bool
    {
        // Super admin can delete any contact
        if ($user->isSuperAdmin()) {
            return true;
        }
        
        // Allow users to delete contacts within their tenant (including contacts assigned by assignment rules)
        return $user->tenant_id === $contact->tenant_id;
    }
}


