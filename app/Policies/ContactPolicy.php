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
        return (int) ($user->tenant_id ?? 0) === (int) $contact->tenant_id || $user->can('contacts.view');
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, Contact $contact): bool
    {
        return (int) ($user->tenant_id ?? 0) === (int) $contact->tenant_id || $user->can('contacts.update');
    }

    public function delete(User $user, Contact $contact): bool
    {
        return (int) ($user->tenant_id ?? 0) === (int) $contact->tenant_id || $user->can('contacts.delete');
    }
}


