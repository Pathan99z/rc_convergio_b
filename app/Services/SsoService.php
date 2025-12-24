<?php

namespace App\Services;

use App\Constants\LinkedAccountConstants;
use App\Models\SsoToken;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;

class SsoService
{
    /**
     * Generate JWT token for SSO login
     */
    public function generateSsoToken(User $user, int $productId): ?string
    {
        try {
            // Get linked account
            $linkedAccount = DB::table('linked_user_accounts')
                ->where('source_user_id', $user->id)
                ->where('product_id', $productId)
                ->where('status', LinkedAccountConstants::STATUS_ACTIVE)
                ->first();

            if (!$linkedAccount) {
                Log::warning('No linked account found for SSO', [
                    'user_id' => $user->id,
                    'product_id' => $productId,
                ]);
                return null;
            }

            // Generate unique JTI (JWT ID)
            $jti = Str::uuid()->toString() . '-' . time();

            // Token expiry (60 seconds)
            $issuedAt = now()->timestamp;
            $expiresAt = $issuedAt + 60;

            // Token payload (base fields)
            $payload = [
                'user_id' => $user->id,
                'mapping_id' => $linkedAccount->id,
                'product_id' => $productId,
                'jti' => $jti,
                'iat' => $issuedAt,
                'exp' => $expiresAt,
            ];

            // Add product-specific user ID field
            // For Console (product_id = 1), use 'console_user_id' as expected by Console
            // For future products, use 'target_user_id' as generic field name
            if ($productId === LinkedAccountConstants::PRODUCT_CONSOLE) {
                $payload['console_user_id'] = $linkedAccount->target_user_id;
            } else {
                $payload['target_user_id'] = $linkedAccount->target_user_id;
            }

            // Load private key
            $privateKeyPath = storage_path('app/keys/sso-private.pem');
            
            if (!file_exists($privateKeyPath)) {
                Log::error('SSO private key not found', [
                    'path' => $privateKeyPath,
                ]);
                return null;
            }

            $privateKey = file_get_contents($privateKeyPath);

            // Generate JWT token (RS256)
            $token = JWT::encode($payload, $privateKey, 'RS256');

            // Store JTI in database
            SsoToken::create([
                'jti' => $jti,
                'expires_at' => now()->addSeconds(60),
                'used' => false,
                'source_user_id' => $user->id,
                'product_id' => $productId,
            ]);

            Log::info('SSO token generated', [
                'user_id' => $user->id,
                'product_id' => $productId,
                'jti' => $jti,
            ]);

            return $token;

        } catch (\Exception $e) {
            Log::error('Failed to generate SSO token', [
                'user_id' => $user->id,
                'product_id' => $productId,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Verify JWT token (for Console to verify)
     * This method can be used for testing or API verification
     */
    public function verifyToken(string $token): ?array
    {
        try {
            // Load public key
            $publicKeyPath = storage_path('app/keys/sso-public.pem');
            
            if (!file_exists($publicKeyPath)) {
                Log::error('SSO public key not found', [
                    'path' => $publicKeyPath,
                ]);
                return null;
            }

            $publicKey = file_get_contents($publicKeyPath);

            // Decode and verify token
            $decoded = JWT::decode($token, new Key($publicKey, 'RS256'));

            // Convert to array
            $payload = (array) $decoded;

            // Check if token is expired
            if (isset($payload['exp']) && $payload['exp'] < time()) {
                Log::warning('SSO token expired', [
                    'jti' => $payload['jti'] ?? null,
                ]);
                return null;
            }

            // Check if JTI has been used
            if (isset($payload['jti'])) {
                $ssoToken = SsoToken::where('jti', $payload['jti'])->first();
                
                if ($ssoToken && $ssoToken->used) {
                    Log::warning('SSO token already used (replay attack)', [
                        'jti' => $payload['jti'],
                    ]);
                    return null;
                }

                // Mark as used
                if ($ssoToken) {
                    $ssoToken->markAsUsed();
                }
            }

            return $payload;

        } catch (\Exception $e) {
            Log::error('Failed to verify SSO token', [
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Get SSO redirect URL for a product
     */
    public function getSsoRedirectUrl(int $productId, string $token): ?string
    {
        return match($productId) {
            LinkedAccountConstants::PRODUCT_CONSOLE => env('CONSOLE_SSO_URL') . '?token=' . urlencode($token),
            // Add more products as needed
            default => null,
        };
    }

    /**
     * Clean up expired tokens (can be run via cron)
     */
    public function cleanupExpiredTokens(): int
    {
        $deleted = SsoToken::where('expires_at', '<', now())
            ->where('used', true)
            ->delete();

        Log::info('Cleaned up expired SSO tokens', [
            'deleted_count' => $deleted,
        ]);

        return $deleted;
    }
}

