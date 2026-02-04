<?php

namespace App\Policies\Hr;

use App\Models\Hr\DocumentType;
use App\Models\User;
use App\Policies\Concerns\ChecksTenantAndTeam;

class DocumentTypePolicy
{
    use ChecksTenantAndTeam;

    /**
     * Determine if the user can view any document types.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasRole('hr_admin') ||
               $user->hasRole('system_admin') ||
               $user->hasRole('admin');
    }

    /**
     * Determine if the user can view the document type.
     */
    public function view(User $user, DocumentType $documentType): bool
    {
        if (!$this->tenantAndTeamCheck($user, $documentType)) {
            return false;
        }

        return $this->viewAny($user);
    }

    /**
     * Determine if the user can create document types.
     */
    public function create(User $user): bool
    {
        return $user->hasRole('hr_admin') ||
               $user->hasRole('system_admin') ||
               $user->hasRole('admin');
    }

    /**
     * Determine if the user can update the document type.
     */
    public function update(User $user, DocumentType $documentType): bool
    {
        if (!$this->tenantAndTeamCheck($user, $documentType)) {
            return false;
        }

        return $this->create($user);
    }

    /**
     * Determine if the user can delete the document type.
     */
    public function delete(User $user, DocumentType $documentType): bool
    {
        if (!$this->tenantAndTeamCheck($user, $documentType)) {
            return false;
        }

        return $this->create($user);
    }
}
