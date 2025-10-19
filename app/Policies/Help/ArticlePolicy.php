<?php

namespace App\Policies\Help;

use App\Models\Help\Article;
use App\Models\User;
use App\Policies\Concerns\ChecksTenantAndTeam;

class ArticlePolicy
{
    use ChecksTenantAndTeam;

    /**
     * Determine whether the user can view any articles.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasRole(['admin', 'service_manager', 'service_agent']);
    }

    /**
     * Determine whether the user can view the article.
     */
    public function view(User $user, Article $article): bool
    {
        if (!$this->tenantAndTeamCheck($user, $article)) {
            return false;
        }

        return $user->hasRole(['admin', 'service_manager', 'service_agent']);
    }

    /**
     * Determine whether the user can create articles.
     */
    public function create(User $user): bool
    {
        return $user->hasRole(['admin', 'service_manager']);
    }

    /**
     * Determine whether the user can update the article.
     */
    public function update(User $user, Article $article): bool
    {
        if (!$this->tenantAndTeamCheck($user, $article)) {
            return false;
        }

        return $user->hasRole(['admin', 'service_manager']);
    }

    /**
     * Determine whether the user can delete the article.
     */
    public function delete(User $user, Article $article): bool
    {
        if (!$this->tenantAndTeamCheck($user, $article)) {
            return false;
        }

        return $user->hasRole(['admin', 'service_manager']);
    }

    /**
     * Determine whether the user can restore the article.
     */
    public function restore(User $user, Article $article): bool
    {
        if (!$this->tenantAndTeamCheck($user, $article)) {
            return false;
        }

        return $user->hasRole(['admin', 'service_manager']);
    }

    /**
     * Determine whether the user can permanently delete the article.
     */
    public function forceDelete(User $user, Article $article): bool
    {
        if (!$this->tenantAndTeamCheck($user, $article)) {
            return false;
        }

        return $user->hasRole(['admin']);
    }

    /**
     * Determine whether the user can publish the article.
     */
    public function publish(User $user, Article $article): bool
    {
        if (!$this->tenantAndTeamCheck($user, $article)) {
            return false;
        }

        return $user->hasRole(['admin', 'service_manager']);
    }

    /**
     * Determine whether the user can view article statistics.
     */
    public function viewStats(User $user): bool
    {
        return $user->hasRole(['admin', 'service_manager']);
    }
}
