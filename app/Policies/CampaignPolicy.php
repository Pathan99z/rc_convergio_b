<?php

namespace App\Policies;

use App\Models\Campaign;
use App\Models\User;

class CampaignPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Campaign $campaign): bool
    {
        return true; // Allow all authenticated users to view campaigns
    }

    public function create(User $user): bool
    {
        return true; // Allow all authenticated users to create campaigns
    }

    public function update(User $user, Campaign $campaign): bool
    {
        // Only allow updates if campaign is in draft status
        if ($campaign->status !== 'draft') {
            return false;
        }
        
        return true; // Allow all authenticated users to update campaigns
    }

    public function delete(User $user, Campaign $campaign): bool
    {
        return true; // Allow all authenticated users to delete campaigns
    }

    public function send(User $user, Campaign $campaign): bool
    {
        // Allow sending if campaign is in draft status
        if ($campaign->status === 'draft') {
            return true;
        }
        
        // Allow scheduling if campaign is in sent status (for resending/scheduling)
        if ($campaign->status === 'sent') {
            return true;
        }
        
        // Allow sending if campaign is in scheduled status (for rescheduling)
        if ($campaign->status === 'scheduled') {
            return true;
        }
        
        return false; // Block sending for other statuses
    }
}
