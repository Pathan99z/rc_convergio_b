<?php

namespace App\Http\Controllers\Api;

// Helper functions are loaded via AppServiceProvider
use App\Mail\TestMail;
use App\Models\EmailTemplate;
use App\Models\MailSetting;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;

class SettingsController extends Controller
{
    /**
     * Get email settings for current tenant.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function getEmailSettings(Request $request): JsonResponse
    {
        $user = $request->user();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Authentication required.',
            ], 401);
        }

        // Check permission: Admin or Super Admin
        if (!$user->hasRole('admin') && !$user->isSuperAdmin()) {
            return response()->json([
                'success' => false,
                'message' => 'Permission denied. Only Admin or Super Admin can view email settings.',
            ], 403);
        }

        $tenantId = $user->tenant_id ?? $user->id;

        // Get email settings
        $settings = getCompanyAllSetting($tenantId);

        // Mask password for security
        if (isset($settings['mail_password'])) {
            $settings['mail_password'] = '******';
        }

        return response()->json([
            'success' => true,
            'message' => 'Email settings retrieved successfully',
            'data' => [
                'email_setting' => $settings['email_setting'] ?? 'smtp',
                'mail_driver' => $settings['mail_driver'] ?? 'SMTP',
                'mail_host' => $settings['mail_host'] ?? '',
                'mail_port' => $settings['mail_port'] ?? '587',
                'mail_username' => $settings['mail_username'] ?? '',
                'mail_password' => '******',
                'mail_encryption' => $settings['mail_encryption'] ?? 'TLS',
                'mail_from_address' => $settings['mail_from_address'] ?? '',
                'mail_from_name' => $settings['mail_from_name'] ?? '',
            ],
        ]);
    }

    /**
     * Store email settings for current tenant.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function storeEmailSettings(Request $request): JsonResponse
    {
        $user = $request->user();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Authentication required.',
            ], 401);
        }

        // Check permission: Admin or Super Admin
        if (!$user->hasRole('admin') && !$user->isSuperAdmin()) {
            return response()->json([
                'success' => false,
                'message' => 'Permission denied. Only Admin or Super Admin can configure email settings.',
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'email_setting' => 'nullable|string|max:50',
            'mail_driver' => 'required|string|max:50',
            'mail_host' => 'required|string|max:255',
            'mail_port' => 'required|string|max:10',
            'mail_username' => 'required|string|max:255',
            'mail_password' => 'required|string|max:500',
            'mail_encryption' => 'required|string|max:10',
            'mail_from_address' => 'required|email|max:255',
            'mail_from_name' => 'required|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $tenantId = $user->tenant_id ?? $user->id;

            // Prepare settings data
            $settingsData = [
                'email_setting' => $request->input('email_setting', 'smtp'),
                'mail_driver' => $request->input('mail_driver'),
                'mail_host' => $request->input('mail_host'),
                'mail_port' => $request->input('mail_port'),
                'mail_username' => $request->input('mail_username'),
                'mail_password' => $request->input('mail_password'),
                'mail_encryption' => $request->input('mail_encryption'),
                'mail_from_address' => $request->input('mail_from_address'),
                'mail_from_name' => $request->input('mail_from_name'),
            ];

            // Encrypt password before saving
            $settingsData['mail_password'] = Crypt::encryptString($settingsData['mail_password']);

            // Save each setting
            foreach ($settingsData as $key => $value) {
                MailSetting::updateOrCreateForTenant($tenantId, $key, $value);
            }

            // Clear cache
            companySettingCacheForget($tenantId);

            Log::info('Email settings saved', [
                'tenant_id' => $tenantId,
                'user_id' => $user->id,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Email settings saved successfully',
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to save email settings', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to save email settings: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Send test email with provided credentials.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function sendTestMail(Request $request): JsonResponse
    {
        $user = $request->user();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Authentication required.',
            ], 401);
        }

        // Check permission: Admin or Super Admin
        if (!$user->hasRole('admin') && !$user->isSuperAdmin()) {
            return response()->json([
                'success' => false,
                'message' => 'Permission denied. Only Admin or Super Admin can send test emails.',
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'email' => 'required|email|max:255',
            'mail_driver' => 'required|string|max:50',
            'mail_host' => 'required|string|max:255',
            'mail_port' => 'required|string|max:10',
            'mail_username' => 'required|string|max:255',
            'mail_password' => 'required|string|max:500',
            'mail_encryption' => 'required|string|max:10',
            'mail_from_address' => 'required|email|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            // Configure mail with provided credentials
            config([
                'mail.mailers.smtp.host' => $request->input('mail_host'),
                'mail.mailers.smtp.port' => $request->input('mail_port'),
                'mail.mailers.smtp.encryption' => strtolower($request->input('mail_encryption')),
                'mail.mailers.smtp.username' => $request->input('mail_username'),
                'mail.mailers.smtp.password' => $request->input('mail_password'),
                'mail.from.address' => $request->input('mail_from_address'),
                'mail.from.name' => $request->input('mail_from_name', config('app.name')),
                'mail.default' => 'smtp',
            ]);

            // Send test email
            Mail::to($request->input('email'))->send(new TestMail());

            Log::info('Test email sent', [
                'user_id' => $user->id,
                'test_email' => $request->input('email'),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Test email sent successfully',
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to send test email', [
                'user_id' => $user->id,
                'test_email' => $request->input('email'),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to send test email: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get provider-specific fields for email configuration.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function getEmailFields(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'emailsetting' => 'required|string|max:50',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $provider = $request->input('emailsetting');
        $providerConfig = EmailTemplate::getProviderHostConfig($provider);

        $user = $request->user();
        $tenantId = $user ? ($user->tenant_id ?? $user->id) : null;
        $settings = $tenantId ? getCompanyAllSetting($tenantId) : [];

        $fields = [
            'mail_driver' => 'SMTP',
            'mail_host' => $providerConfig['host'] ?? ($settings['mail_host'] ?? ''),
            'mail_port' => $providerConfig['port'] ?? ($settings['mail_port'] ?? '587'),
            'mail_encryption' => $providerConfig['encryption'] ?? ($settings['mail_encryption'] ?? 'TLS'),
            'mail_username' => $settings['mail_username'] ?? '',
            'mail_from_address' => $settings['mail_from_address'] ?? '',
            'mail_from_name' => $settings['mail_from_name'] ?? '',
        ];

        // Determine which fields are readonly based on provider
        $readonlyFields = [];
        if ($provider !== 'custom' && $provider !== 'smtp') {
            if ($providerConfig !== null) {
                $readonlyFields = ['mail_host', 'mail_port', 'mail_encryption'];
            }
        }

        return response()->json([
            'success' => true,
            'data' => [
                'fields' => $fields,
                'readonly_fields' => $readonlyFields,
                'provider_config' => $providerConfig,
            ],
        ]);
    }

    /**
     * Get available email providers.
     *
     * @return JsonResponse
     */
    public function getEmailProviders(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => EmailTemplate::$emailSettings,
        ]);
    }
}

