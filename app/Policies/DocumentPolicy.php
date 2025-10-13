<?php

namespace App\Policies;

use App\Models\Document;
use App\Models\User;
use App\Policies\Concerns\ChecksTenantAndTeam;
use Illuminate\Auth\Access\HandlesAuthorization;

class DocumentPolicy
{
    use HandlesAuthorization, ChecksTenantAndTeam;

    /**
     * Determine whether the user can view any documents.
     */
    public function viewAny(User $user): bool
    {
        return $user->tenant_id != null;
    }

    /**
     * Determine whether the user can view the document.
     */
    public function view(User $user, Document $document): bool
    {
        // Basic tenant validation
        if ($user->tenant_id !== $document->tenant_id) {
            return false;
        }

        // Admin override - admins can view all documents in their tenant
        if ($user->hasRole('admin')) {
            return true;
        }

        // Check visibility rules
        switch ($document->visibility) {
            case 'private':
                // Only owner can view private documents
                return $document->owner_id === $user->id;
            
            case 'team':
                // Team members can view team documents
                if (!$user->team_id || $document->team_id !== $user->team_id) {
                    return false;
                }
                return true;
            
            case 'tenant':
                // All tenant users can view tenant documents
                return true;
            
            default:
                return false;
        }
    }

    /**
     * Determine whether the user can create documents.
     */
    public function create(User $user): bool
    {
        return $user->tenant_id != null;
    }

    /**
     * Determine whether the user can update the document.
     */
    public function update(User $user, Document $document): bool
    {
        // Basic tenant validation
        if ($user->tenant_id !== $document->tenant_id) {
            return false;
        }

        // Admin override
        if ($user->hasRole('admin')) {
            return true;
        }

        // Owner can always update their documents
        if ($document->owner_id === $user->id) {
            return true;
        }

        // Team members can update team documents if visibility is 'team'
        if ($document->visibility === 'team' && 
            $user->team_id && 
            $document->team_id === $user->team_id) {
            return true;
        }

        return false;
    }

    /**
     * Determine whether the user can delete the document.
     */
    public function delete(User $user, Document $document): bool
    {
        // Basic tenant validation
        if ($user->tenant_id !== $document->tenant_id) {
            return false;
        }

        // Admin override
        if ($user->hasRole('admin')) {
            return true;
        }

        // Owner can always delete their documents
        if ($document->owner_id === $user->id) {
            return true;
        }

        // Team members can delete team documents if visibility is 'team'
        if ($document->visibility === 'team' && 
            $user->team_id && 
            $document->team_id === $user->team_id) {
            return true;
        }

        return false;
    }

    /**
     * Determine whether the user can restore the document.
     */
    public function restore(User $user, Document $document): bool
    {
        return $this->update($user, $document);
    }

    /**
     * Determine whether the user can permanently delete the document.
     */
    public function forceDelete(User $user, Document $document): bool
    {
        // Only admins can force delete
        return $user->hasRole('admin') && $user->tenant_id === $document->tenant_id;
    }

    /**
     * Determine whether the user can download the document.
     */
    public function download(User $user, Document $document): bool
    {
        return $this->view($user, $document);
    }
}
