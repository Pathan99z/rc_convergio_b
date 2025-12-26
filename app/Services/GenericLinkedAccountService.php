<?php

namespace App\Services;

use App\Constants\LinkedAccountConstants;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class GenericLinkedAccountService
{
    /**
     * Sync user to external product with password sync
     */
    public function syncUserToProduct(
        User $convergioUser,
        int $productId,
        int $integrationType = LinkedAccountConstants::TYPE_BOTH
    ): ?int {
        try {
            // Check if link already exists
            $existingLink = DB::table('linked_user_accounts')
                ->where('source_user_id', $convergioUser->id)
                ->where('product_id', $productId)
                ->first();

            if ($existingLink && $existingLink->status === LinkedAccountConstants::STATUS_ACTIVE) {
                // Update existing link
                return $this->updateExistingLink($convergioUser, $productId, $integrationType, $existingLink);
            }

            // Route to product-specific sync
            return match($productId) {
                LinkedAccountConstants::PRODUCT_CONSOLE => $this->syncToConsole($convergioUser, $integrationType),
                LinkedAccountConstants::PRODUCT_FUTURE_PRODUCT_1 => $this->syncToFutureProduct1($convergioUser, $integrationType),
                // Add more products as needed
                default => throw new \Exception("Unknown product ID: {$productId}"),
            };

        } catch (\Exception $e) {
            Log::error('Failed to sync user to product', [
                'user_id' => $convergioUser->id,
                'product_id' => $productId,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Sync user to Console
     */
    private function syncToConsole(User $convergioUser, int $integrationType): ?int
    {
        try {
            $isAdmin = $convergioUser->hasRole('admin') || $convergioUser->hasRole('super_admin');
            
            if (!$isAdmin) {
                Log::info('Skipping Console sync for non-admin user', [
                    'user_id' => $convergioUser->id,
                    'email' => $convergioUser->email,
                ]);
                return null;
            }

            $companyCd = $convergioUser->organization_name ?? '';
            
            if (!empty($companyCd)) {
                $this->ensureConsoleCompanyExists($companyCd, $convergioUser);
            }

            $existingConsoleUser = DB::connection('console')
                ->table('users')
                ->where('email', $convergioUser->email)
                ->first();

            return $existingConsoleUser
                ? $this->updateExistingConsoleUser($convergioUser, $existingConsoleUser, $integrationType)
                : $this->createNewConsoleUser($convergioUser, $companyCd, $integrationType);

        } catch (\Exception $e) {
            Log::error('Failed to sync user to Console', [
                'user_id' => $convergioUser->id,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Update existing Console user
     *
     * @param User $convergioUser
     * @param object $existingConsoleUser
     * @param int $integrationType
     * @return int
     */
    private function updateExistingConsoleUser(
        User $convergioUser,
        object $existingConsoleUser,
        int $integrationType
    ): int {
        if (!empty($existingConsoleUser->company_cd)) {
            $this->ensureConsoleCompanyExists($existingConsoleUser->company_cd, $convergioUser);
        }
        
        DB::connection('console')
            ->table('users')
            ->where('user_id', $existingConsoleUser->user_id)
            ->update([
                'user_group_cd' => '002',
                'user_level' => '2',
                'flagactive' => $convergioUser->status === 'active' ? 'Y' : 'N',
                'last_updated_date' => now()->format('Y-m-d'),
                'last_updated_time' => now()->format('H:i:s'),
            ]);
        
        $this->createOrUpdateLink(
            $convergioUser->id,
            $existingConsoleUser->user_id,
            LinkedAccountConstants::PRODUCT_CONSOLE,
            $integrationType,
            $existingConsoleUser->user_uid ?? '',
            $existingConsoleUser->username ?? ''
        );
        
        return $existingConsoleUser->user_id;
    }

    /**
     * Create new Console user
     *
     * @param User $convergioUser
     * @param string $companyCd
     * @param int $integrationType
     * @return int
     */
    private function createNewConsoleUser(User $convergioUser, string $companyCd, int $integrationType): int
    {
        $username = $this->generateConsoleUsername($convergioUser->email);
        $randomPassword = md5(Str::random(32));

        $consoleUserData = [
            'fullname' => $convergioUser->name,
            'username' => $username,
            'password' => $randomPassword,
            'email' => $convergioUser->email,
            'company_cd' => $companyCd,
            'user_group_cd' => '002',
            'user_level' => '2',
            'user_uid' => (string) $convergioUser->id,
            'user_created_by' => (string) $convergioUser->id,
            'user_created_date' => $convergioUser->created_at->format('Y-m-d'),
            'flagactive' => $convergioUser->status === 'active' ? 'Y' : 'N',
            'user_terms_flag' => 0,
            'last_updated_date' => now()->format('Y-m-d'),
            'last_updated_time' => now()->format('H:i:s'),
        ];

        DB::connection('console')->table('users')->insert($consoleUserData);
        
        $consoleUserId = DB::connection('console')->getPdo()->lastInsertId();

        $this->createOrUpdateLink(
            $convergioUser->id,
            $consoleUserId,
            LinkedAccountConstants::PRODUCT_CONSOLE,
            $integrationType,
            (string) $convergioUser->id,
            $username
        );

        Log::info('Admin user synced to Console', [
            'convergio_user_id' => $convergioUser->id,
            'console_user_id' => $consoleUserId,
            'integration_type' => $integrationType,
        ]);

        return $consoleUserId;
    }

    /**
     * Ensure company exists in Console's company_mst table
     * This is REQUIRED for SSO login to work (Console uses INNER JOIN with company_mst)
     */
    private function ensureConsoleCompanyExists(string $companyCd, User $convergioUser): void
    {
        try {
            $existingCompany = DB::connection('console')
                ->table('company_mst')
                ->where('cmp_uid_code', $companyCd)
                ->first();
            
            if (!$existingCompany) {
                // Create company if it doesn't exist
                DB::connection('console')->table('company_mst')->insert([
                    'cmp_uid_code' => $companyCd,
                    'cmp_name' => $convergioUser->organization_name ?? $companyCd,
                    'cmp_flagactive' => 'Y', // Must be active for SSO to work
                    'cmp_owner_name' => $convergioUser->name ?? '',
                    'cmp_brand_email' => $convergioUser->email ?? '',
                    // Other fields can remain NULL/default as they're not required for SSO
                ]);
                
                Log::info('Company created in Console company_mst', [
                    'cmp_uid_code' => $companyCd,
                    'convergio_user_id' => $convergioUser->id,
                ]);
            } else if (isset($existingCompany->cmp_flagactive) && $existingCompany->cmp_flagactive !== 'Y') {
                // Update company to active if it exists but is inactive
                DB::connection('console')
                    ->table('company_mst')
                    ->where('cmp_uid_code', $companyCd)
                    ->update(['cmp_flagactive' => 'Y']);
                
                Log::info('Company activated in Console company_mst', [
                    'cmp_uid_code' => $companyCd,
                    'convergio_user_id' => $convergioUser->id,
                ]);
            }
        } catch (\Exception $e) {
            // Log error but don't fail user sync - company creation is best effort
            Log::warning('Failed to ensure company exists in Console company_mst', [
                'cmp_uid_code' => $companyCd,
                'convergio_user_id' => $convergioUser->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Update existing link
     */
    private function updateExistingLink($convergioUser, $productId, $integrationType, $existingLink): int
    {
        DB::table('linked_user_accounts')
            ->where('id', $existingLink->id)
            ->update([
                'integration_type' => $integrationType,
                'status' => LinkedAccountConstants::STATUS_ACTIVE,
                'last_sync_at' => now(),
                'updated_at' => now(),
            ]);

        return $existingLink->target_user_id;
    }

    /**
     * Create or update link record
     */
    private function createOrUpdateLink(
        int $sourceUserId,
        int $targetUserId,
        int $productId,
        int $integrationType,
        string $targetUserUid,
        string $targetUsername
    ): void {
        DB::table('linked_user_accounts')->updateOrInsert(
            [
                'source_user_id' => $sourceUserId,
                'product_id' => $productId,
            ],
            [
                'target_user_id' => $targetUserId,
                'target_user_uid' => $targetUserUid,
                'target_username' => $targetUsername,
                'integration_type' => $integrationType,
                'status' => LinkedAccountConstants::STATUS_ACTIVE,
                'synced_at' => now(),
                'last_sync_at' => now(),
                'updated_at' => now(),
            ]
        );
    }

    /**
     * Generate unique username for Console
     */
    private function generateConsoleUsername(string $email): string
    {
        $baseUsername = explode('@', $email)[0];
        $username = $baseUsername;
        $counter = 1;

        while (DB::connection('console')
            ->table('users')
            ->where('username', $username)
            ->exists()) {
            $username = $baseUsername . $counter;
            $counter++;
        }

        return $username;
    }

    /**
     * Get target user ID for a product
     */
    public function getTargetUserId(int $sourceUserId, int $productId): ?int
    {
        $link = DB::table('linked_user_accounts')
            ->where('source_user_id', $sourceUserId)
            ->where('product_id', $productId)
            ->where('status', LinkedAccountConstants::STATUS_ACTIVE)
            ->first();

        return $link ? $link->target_user_id : null;
    }

    /**
     * Placeholder for future product sync
     */
    private function syncToFutureProduct1(User $convergioUser, int $integrationType): ?int
    {
        // Placeholder for future product integration
        Log::info('Future product sync not implemented yet', [
            'user_id' => $convergioUser->id,
        ]);
        return null;
    }
}

