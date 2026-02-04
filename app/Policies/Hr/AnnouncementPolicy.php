<?php

namespace App\Policies\Hr;

use App\Models\Hr\Announcement;
use App\Models\User;

class AnnouncementPolicy
{
    /**
     * Determine if the user can view any announcements.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasRole('hr_admin') || 
               $user->hasRole('system_admin') || 
               $user->hasRole('admin');
    }

    /**
     * Determine if the user can view the announcement.
     */
    public function view(User $user, Announcement $announcement): bool
    {
        // HR Admin can view all
        if ($user->hasRole('hr_admin') || 
            $user->hasRole('system_admin') || 
            $user->hasRole('admin')) {
            return true;
        }

        // Employee can view if they have access (checked in controller)
        return true;
    }

    /**
     * Determine if the user can create announcements.
     */
    public function create(User $user): bool
    {
        return $user->hasRole('hr_admin') || 
               $user->hasRole('system_admin') || 
               $user->hasRole('admin');
    }

    /**
     * Determine if the user can update the announcement.
     */
    public function update(User $user, Announcement $announcement): bool
    {
        return $user->hasRole('hr_admin') || 
               $user->hasRole('system_admin') || 
               $user->hasRole('admin');
    }

    /**
     * Determine if the user can delete the announcement.
     */
    public function delete(User $user, Announcement $announcement): bool
    {
        return $user->hasRole('hr_admin') || 
               $user->hasRole('system_admin') || 
               $user->hasRole('admin');
    }

    /**
     * Determine if the user can publish the announcement.
     */
    public function publish(User $user, Announcement $announcement): bool
    {
        return $user->hasRole('hr_admin') || 
               $user->hasRole('system_admin') || 
               $user->hasRole('admin');
    }
}

