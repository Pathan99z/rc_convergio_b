<?php

namespace App\Services;

use App\Models\License;
use App\Models\Plan;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class LicenseService
{
    /**
     * Create a license for a tenant.
     */
    public function createLicenseForTenant(User $tenant, ?Plan $plan = null): ?License
    {
        try {
            // Use default plan if none provided
            if (!$plan) {
                $plan = Plan::getDefault();
            }

            if (!$plan) {
                Log::error('No default plan found for license creation', [
                    'tenant_id' => $tenant->id,
                ]);
                return null;
            }

            // Deactivate any existing active license for this tenant
            $this->deactivateExistingLicense($tenant);

            // Create new license
            $license = License::create([
                'tenant_id' => $tenant->id,
                'plan_id' => $plan->id,
                'expires_at' => now()->addDays($plan->duration_days),
                'is_active' => true,
                'activated_at' => now(),
            ]);

            Log::info('License created for tenant', [
                'tenant_id' => $tenant->id,
                'license_id' => $license->id,
                'plan_name' => $plan->name,
                'expires_at' => $license->expires_at,
            ]);

            return $license;

        } catch (\Exception $e) {
            Log::error('Failed to create license for tenant', [
                'tenant_id' => $tenant->id,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Validate a tenant's license.
     */
    public function validateLicense(User $tenant): array
    {
        $license = $tenant->activeLicense();

        if (!$license) {
            return [
                'valid' => false,
                'reason' => 'No license found',
                'license' => null,
            ];
        }

        if (!$license->is_active) {
            return [
                'valid' => false,
                'reason' => 'License is inactive',
                'license' => $license,
            ];
        }

        if ($license->isExpired()) {
            return [
                'valid' => false,
                'reason' => 'License has expired',
                'license' => $license,
            ];
        }

        return [
            'valid' => true,
            'reason' => null,
            'license' => $license,
        ];
    }

    /**
     * Upgrade a tenant's license to a new plan.
     */
    public function upgradeLicense(User $tenant, Plan $newPlan): ?License
    {
        try {
            DB::beginTransaction();

            // Deactivate existing license
            $this->deactivateExistingLicense($tenant);

            // Create new license with new plan
            $license = $this->createLicenseForTenant($tenant, $newPlan);

            if ($license) {
                DB::commit();
                Log::info('License upgraded for tenant', [
                    'tenant_id' => $tenant->id,
                    'new_plan' => $newPlan->name,
                    'license_id' => $license->id,
                ]);
            } else {
                DB::rollBack();
            }

            return $license;

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to upgrade license for tenant', [
                'tenant_id' => $tenant->id,
                'new_plan' => $newPlan->name,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Extend a tenant's license by a given number of days.
     */
    public function extendLicense(User $tenant, int $days): bool
    {
        try {
            $license = $tenant->activeLicense();

            if (!$license) {
                Log::warning('No active license found to extend', [
                    'tenant_id' => $tenant->id,
                ]);
                return false;
            }

            $result = $license->extend($days);

            if ($result) {
                Log::info('License extended for tenant', [
                    'tenant_id' => $tenant->id,
                    'days_added' => $days,
                    'new_expiry' => $license->expires_at,
                ]);
            }

            return $result;

        } catch (\Exception $e) {
            Log::error('Failed to extend license for tenant', [
                'tenant_id' => $tenant->id,
                'days' => $days,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Deactivate existing active license for a tenant.
     */
    private function deactivateExistingLicense(User $tenant): void
    {
        $existingLicense = $tenant->activeLicense();
        if ($existingLicense) {
            $existingLicense->deactivate();
            Log::info('Deactivated existing license for tenant', [
                'tenant_id' => $tenant->id,
                'license_id' => $existingLicense->id,
            ]);
        }
    }

    /**
     * Get license information for a tenant.
     */
    public function getLicenseInfo(User $tenant): ?array
    {
        $license = $tenant->activeLicense();

        if (!$license) {
            return null;
        }

        return [
            'id' => $license->id,
            'plan_name' => $license->plan->name,
            'plan_description' => $license->plan->description,
            'features' => $license->plan->features,
            'expires_at' => $license->expires_at->toIso8601String(),
            'days_remaining' => $license->daysRemaining(),
            'status' => $license->status,
            'is_valid' => $license->isValid(),
        ];
    }

    /**
     * Check if license validation is enabled.
     */
    public function isLicenseCheckEnabled(): bool
    {
        return config('app.license_check_enabled', true);
    }

    /**
     * Create licenses for existing tenants that don't have one.
     */
    public function syncLicensesForExistingTenants(): int
    {
        $defaultPlan = Plan::getDefault();
        
        if (!$defaultPlan) {
            Log::error('No default plan found for license sync');
            return 0;
        }

        // Find tenants without active licenses
        $tenantsWithoutLicenses = User::whereDoesntHave('license')
            ->where('status', 'active')
            ->get();

        $created = 0;

        foreach ($tenantsWithoutLicenses as $tenant) {
            $license = $this->createLicenseForTenant($tenant, $defaultPlan);
            if ($license) {
                $created++;
            }
        }

        Log::info('License sync completed', [
            'tenants_processed' => $tenantsWithoutLicenses->count(),
            'licenses_created' => $created,
        ]);

        return $created;
    }
}
