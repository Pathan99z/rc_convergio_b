<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Str;
use Illuminate\Http\JsonResponse;

class FeatureRestrictionService
{
    /**
     * Restricted email domains (free/preview users)
     */
    private const RESTRICTED_DOMAINS = [
        'yahoo.com',
        'hotmail.com',
        'outlook.com',
        'aol.com',
        'icloud.com',
        'protonmail.com',
        'mail.com',
        'live.com',
        'msn.com'
    ];

    /**
     * Feature restrictions configuration
     */
    private const FEATURE_RESTRICTIONS = [
        'user_management' => [
            'roles' => ['admin'],
            'domains' => ['restricted' => false, 'business' => true],
            'message' => 'User management is only available for administrators with business accounts.'
        ],
        'campaign_sending' => [
            'roles' => ['admin', 'manager'],
            'domains' => ['restricted' => false, 'business' => true],
            'message' => 'Campaign sending is not available on your current plan. Upgrade to a business account.'
        ],
        'advanced_reports' => [
            'roles' => ['admin', 'manager'],
            'domains' => ['restricted' => false, 'business' => true],
            'message' => 'Advanced reports are not available on your current plan. Upgrade to a business account.'
        ],
        'team_invites' => [
            'roles' => ['admin'],
            'domains' => ['restricted' => false, 'business' => true],
            'message' => 'Team invitations are only available for administrators with business accounts.'
        ],
        'bulk_operations' => [
            'roles' => ['admin', 'manager'],
            'domains' => ['restricted' => false, 'business' => true],
            'message' => 'Bulk operations are not available on your current plan. Upgrade to a business account.'
        ],
        'api_integrations' => [
            'roles' => ['admin'],
            'domains' => ['restricted' => false, 'business' => true],
            'message' => 'API integrations are only available for administrators with business accounts.'
        ],
        'data_export' => [
            'roles' => ['admin', 'manager'],
            'domains' => ['restricted' => false, 'business' => true],
            'message' => 'Data export is not available on your current plan. Upgrade to a business account.'
        ]
    ];

    /**
     * Check if user can access a specific feature
     */
    public function canAccessFeature(User $user, string $feature): bool
    {
        if (!isset(self::FEATURE_RESTRICTIONS[$feature])) {
            return true; // Feature not restricted
        }

        $restriction = self::FEATURE_RESTRICTIONS[$feature];
        
        // Check role-based restriction
        if (!$this->hasRequiredRole($user, $restriction['roles'])) {
            return false;
        }

        // Check domain-based restriction
        if (!$this->hasValidDomain($user, $restriction['domains'])) {
            return false;
        }

        return true;
    }

    /**
     * Get restriction message for a feature
     */
    public function getRestrictionMessage(string $feature): string
    {
        return self::FEATURE_RESTRICTIONS[$feature]['message'] ?? 'This feature is not available on your current plan.';
    }

    /**
     * Check if user has required role
     */
    private function hasRequiredRole(User $user, array $requiredRoles): bool
    {
        foreach ($requiredRoles as $role) {
            if ($user->hasRole($role)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Check if user has valid domain for feature
     */
    private function hasValidDomain(User $user, array $domainRules): bool
    {
        $userDomain = $this->getUserDomain($user);
        
        // If restricted domains are blocked for this feature
        if ($domainRules['restricted'] === false && $this->isRestrictedDomain($userDomain)) {
            return false;
        }

        // If business domains are required for this feature
        if ($domainRules['business'] === true && $this->isRestrictedDomain($userDomain)) {
            return false;
        }

        return true;
    }

    /**
     * Get user's email domain
     */
    public function getUserDomain(User $user): string
    {
        return Str::lower(Str::after($user->email, '@'));
    }

    /**
     * Check if domain is restricted
     */
    public function isRestrictedDomain(string $domain): bool
    {
        return in_array($domain, self::RESTRICTED_DOMAINS);
    }

    /**
     * Check if domain is business domain
     */
    public function isBusinessDomain(string $domain): bool
    {
        return !$this->isRestrictedDomain($domain);
    }

    /**
     * Get user's plan type based on domain
     */
    public function getUserPlanType(User $user): string
    {
        $domain = $this->getUserDomain($user);
        
        if ($this->isRestrictedDomain($domain)) {
            return 'free';
        }
        
        return 'business';
    }

    /**
     * Get user's feature access summary
     */
    public function getUserFeatureAccess(User $user): array
    {
        $access = [];
        
        foreach (array_keys(self::FEATURE_RESTRICTIONS) as $feature) {
            $access[$feature] = $this->canAccessFeature($user, $feature);
        }
        
        return $access;
    }

    /**
     * Get restriction details for a feature
     */
    public function getFeatureRestrictionDetails(string $feature): ?array
    {
        return self::FEATURE_RESTRICTIONS[$feature] ?? null;
    }

    /**
     * Check if user can create users
     */
    public function canCreateUsers(User $user): bool
    {
        return $this->canAccessFeature($user, 'user_management');
    }

    /**
     * Check if user can send campaigns
     */
    public function canSendCampaigns(User $user): bool
    {
        return $this->canAccessFeature($user, 'campaign_sending');
    }

    /**
     * Check if user can access advanced reports
     */
    public function canAccessAdvancedReports(User $user): bool
    {
        return $this->canAccessFeature($user, 'advanced_reports');
    }

    /**
     * Check if user can invite team members
     */
    public function canInviteTeamMembers(User $user): bool
    {
        return $this->canAccessFeature($user, 'team_invites');
    }

    /**
     * Check if user can perform bulk operations
     */
    public function canPerformBulkOperations(User $user): bool
    {
        return $this->canAccessFeature($user, 'bulk_operations');
    }

    /**
     * Check if user can access API integrations
     */
    public function canAccessApiIntegrations(User $user): bool
    {
        return $this->canAccessFeature($user, 'api_integrations');
    }

    /**
     * Check if user can export data
     */
    public function canExportData(User $user): bool
    {
        return $this->canAccessFeature($user, 'data_export');
    }
}
