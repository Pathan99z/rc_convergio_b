<?php

namespace App\Helper;

use App\Models\MailSetting;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;

if (!function_exists('SetConfigEmail')) {
    /**
     * Configure email settings for a tenant before sending emails.
     *
     * @param int|null $tenantId Tenant ID (if null, uses current user's tenant)
     * @return bool
     */
    function SetConfigEmail(?int $tenantId = null): bool
    {
        try {
            // Get tenant_id
            if (empty($tenantId)) {
                if (Auth::check()) {
                    $user = Auth::user();
                    $tenantId = $user->tenant_id ?? $user->id;
                } else {
                    // Fallback: get first super admin or default tenant
                    $superAdmin = User::where('type', 'super admin')->first();
                    if ($superAdmin !== null) {
                        $tenantId = $superAdmin->tenant_id ?? $superAdmin->id;
                    } else {
                        $tenantId = 1;
                    }
                }
            }

            // Get tenant email settings
            $emailSettings = getCompanyAllSetting($tenantId);

            // Check if tenant has configured email settings
            $hasTenantConfig = !empty($emailSettings['mail_host']) &&
                !empty($emailSettings['mail_username']) &&
                !empty($emailSettings['mail_password']);

            if (!$hasTenantConfig) {
                // Fallback to .env with warning
                Log::warning('Tenant email configuration not found, using .env fallback', [
                    'tenant_id' => $tenantId,
                ]);

                // Use .env settings (Laravel will use config/mail.php which reads from .env)
                return true;
            }

            // Decrypt password if encrypted
            $password = $emailSettings['mail_password'] ?? '';
            try {
                $password = Crypt::decryptString($password);
            } catch (\Exception $e) {
                // Password might not be encrypted (backward compatibility)
                // Use as-is
            }

            // Configure Laravel mail config
            config([
                'mail.mailers.smtp.host' => $emailSettings['mail_host'] ?? env('MAIL_HOST', 'smtp.gmail.com'),
                'mail.mailers.smtp.port' => $emailSettings['mail_port'] ?? env('MAIL_PORT', '587'),
                'mail.mailers.smtp.encryption' => strtolower(
                    $emailSettings['mail_encryption'] ?? env('MAIL_ENCRYPTION', 'tls')
                ),
                'mail.mailers.smtp.username' => $emailSettings['mail_username'] ?? env('MAIL_USERNAME'),
                'mail.mailers.smtp.password' => $password,
                'mail.from.address' => $emailSettings['mail_from_address'] ??
                    env('MAIL_FROM_ADDRESS', 'noreply@example.com'),
                'mail.from.name' => $emailSettings['mail_from_name'] ??
                    env('MAIL_FROM_NAME', config('app.name')),
            ]);

            // Set default mailer to smtp
            config(['mail.default' => 'smtp']);

            return true;
        } catch (\Exception $e) {
            Log::error('SetConfigEmail failed', [
                'tenant_id' => $tenantId ?? null,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            // Fallback to .env on error
            return true;
        }
    }
}

if (!function_exists('getCompanyAllSetting')) {
    /**
     * Get all mail settings for a tenant (company).
     *
     * @param int|null $tenantId
     * @return array<string, string>
     */
    function getCompanyAllSetting(?int $tenantId = null): array
    {
        if (empty($tenantId)) {
            if (Auth::check()) {
                $user = Auth::user();
                $tenantId = $user->tenant_id ?? $user->id;
            } else {
                return [];
            }
        }

        $cacheKey = 'company_mail_settings_' . $tenantId;

        return Cache::rememberForever($cacheKey, function () use ($tenantId) {
            $settings = MailSetting::where('tenant_id', $tenantId)
                ->pluck('setting_value', 'setting_key')
                ->toArray();

            return $settings;
        });
    }
}

if (!function_exists('companySettingCacheForget')) {
    /**
     * Clear company mail settings cache.
     *
     * @param int|null $tenantId
     * @return void
     */
    function companySettingCacheForget(?int $tenantId = null): void
    {
        try {
            if (empty($tenantId)) {
                if (Auth::check()) {
                    $user = Auth::user();
                    $tenantId = $user->tenant_id ?? $user->id;
                } else {
                    return;
                }
            }

            $cacheKey = 'company_mail_settings_' . $tenantId;
            Cache::forget($cacheKey);
        } catch (\Exception $e) {
            Log::error('companySettingCacheForget failed', [
                'tenant_id' => $tenantId ?? null,
                'error' => $e->getMessage(),
            ]);
        }
    }
}

