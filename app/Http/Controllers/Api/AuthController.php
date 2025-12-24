<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ForgotPasswordRequest;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Http\Requests\Auth\ResetPasswordRequest;
use App\Models\User;
use App\Constants\LinkedAccountConstants;
use App\Services\GenericLinkedAccountService;
use App\Services\LicenseService;
use App\Services\SsoService;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Auth\Events\Registered;
use Illuminate\Auth\Events\Verified;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;

class AuthController extends Controller
{
    protected LicenseService $licenseService;
    protected GenericLinkedAccountService $linkedAccountService;
    protected SsoService $ssoService;

    public function __construct(
        LicenseService $licenseService,
        GenericLinkedAccountService $linkedAccountService,
        SsoService $ssoService
    ) {
        $this->licenseService = $licenseService;
        $this->linkedAccountService = $linkedAccountService;
        $this->ssoService = $ssoService;
    }

    public function register(RegisterRequest $request): JsonResponse
    {
        $data = $request->validated();

        DB::beginTransaction();
        try {
            // Create user
            $user = User::create([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => $data['password'],
                'organization_name' => $data['organization_name'],
                'status' => 'active', // Automatically set status to active for public registrations
            ]);

            // Set tenant_id to user's own ID for public registrations
            $user->update(['tenant_id' => $user->id]);

            DB::commit();

            // Fire Registered event AFTER successful user creation
            // This triggers email verification notification asynchronously
            // Email will ONLY be sent after registration is successful
            event(new Registered($user));
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Registration failed', [
                'error' => $e->getMessage(),
                'email' => $data['email'] ?? null,
            ]);
            return $this->error('Registration failed. Please try again.', 500);
        }

        // Prepare response immediately (email sending is async and won't block)
        $autoLogin = true;
        $responseData = [
            'user' => $this->transformUser($user),
            'email_verification_required' => $user instanceof MustVerifyEmail,
        ];

        if ($autoLogin) {
            [$token, $expiresAt] = $this->createTokenWithExpiry($user, 'register');
            $responseData['access_token'] = $token;
            $responseData['expires_at'] = $expiresAt?->toIso8601String();
        }

        // Return immediately - email is sent asynchronously via queue
        // This ensures API responds fast and email only sends after successful registration
        return $this->success($responseData, 201);
    }

    public function login(LoginRequest $request): JsonResponse
    {
        $email = (string) str($request->validated('email'))->lower();
        $key = 'login:'.sha1($email.'|'.$request->ip());

        if (RateLimiter::tooManyAttempts($key, 5)) {
            $seconds = RateLimiter::availableIn($key);
            return $this->error('Too many attempts. Try again later.', 423, [
                'retry_after' => $seconds,
            ]);
        }

        $user = User::where('email', $email)->first();

        if (! $user || ! Hash::check($request->validated('password'), $user->password)) {
            RateLimiter::hit($key, 15 * 60);
            return $this->error('Invalid credentials.', 401);
        }

        // Check if user is active
        if ($user->status !== 'active') {
            return $this->error('Your account has been deactivated. Please contact an administrator.', 403, [
                'account_deactivated' => true,
                'user_id' => $user->id
            ]);
        }

        // Check if email is verified
        if (!$user->hasVerifiedEmail()) {
            return $this->error('Please verify your email address before logging in. Check your inbox for a verification link.', 403, [
                'email_verification_required' => true,
                'user_id' => $user->id
            ]);
        }

        RateLimiter::clear($key);

        // Validate license if enabled
        if ($this->licenseService->isLicenseCheckEnabled()) {
            $licenseValidation = $this->licenseService->validateLicense($user);
            
            if (!$licenseValidation['valid']) {
                return $this->error($this->getLicenseErrorMessage($licenseValidation['reason']), 403, [
                    'license_invalid' => true,
                    'license_reason' => $licenseValidation['reason'],
                    'license_info' => $licenseValidation['license'] ? [
                        'status' => $licenseValidation['license']->status,
                        'expires_at' => $licenseValidation['license']->expires_at->toIso8601String(),
                        'days_remaining' => $licenseValidation['license']->daysRemaining(),
                    ] : null,
                ]);
            }
        }

        [$plainTextToken, $expiresAt] = $this->createTokenWithExpiry($user, 'login');

        $responseData = [
            'access_token' => $plainTextToken,
            'expires_at' => $expiresAt?->toIso8601String(),
            'user' => $this->transformUser($user),
        ];

        // Include license information in response
        $licenseInfo = $this->licenseService->getLicenseInfo($user);
        if ($licenseInfo) {
            $responseData['license'] = $licenseInfo;
        }

        return $this->success($responseData);
    }

    public function verifyEmail(Request $request)
    {
        // Same validation - NO CHANGES
        $request->validate([
            'id' => ['required', 'integer'],
            'hash' => ['required', 'string'],
        ]);

        $user = User::findOrFail((int) $request->input('id'));

        // Same validation logic - NO CHANGES
        if (! URL::hasValidSignature($request)) {
            return $this->renderVerificationPage(false, 'Invalid or expired verification link.', $request);
        }

        if ($user->hasVerifiedEmail()) {
            return $this->renderVerificationPage(true, 'Your email is already verified. You can log in now!', $request);
        }

        if (! hash_equals((string) $request->route('hash', $request->input('hash')), sha1($user->getEmailForVerification()))) {
            return $this->renderVerificationPage(false, 'Invalid verification hash.', $request);
        }

        // Same verification logic - NO CHANGES
        if ($user->markEmailAsVerified()) {
            if ($user instanceof MustVerifyEmail) {
                event(new Verified($user));
            }
        }

        // Return professional HTML success page instead of JSON
        return $this->renderVerificationPage(true, 'Your email verification is complete! You can log in now.', $request);
    }
    
    /**
     * Render professional verification result page
     * This only changes the VIEW, not the functionality
     */
    private function renderVerificationPage(bool $success, string $message, Request $request)
    {
        $logoUrl = null;
        // Try RC_LOGO.png first (actual file), then rc-convergio-logo.png, then fallback
        $logoPath = public_path('images/RC_LOGO.png');
        if (file_exists($logoPath)) {
            $logoUrl = config('app.url') . '/images/RC_LOGO.png';
        } else {
            $logoPath = public_path('images/rc-convergio-logo.png');
            if (file_exists($logoPath)) {
                $logoUrl = config('app.url') . '/images/rc-convergio-logo.png';
            }
        }
        
        $frontendUrl = config('app.frontend_url', config('app.url'));
        
        return response()->view('emails.verify-email-result', [
            'success' => $success,
            'message' => $message,
            'logoUrl' => $logoUrl,
            'frontendUrl' => $frontendUrl,
        ], $success ? 200 : 400);
    }

    public function forgotPassword(ForgotPasswordRequest $request): JsonResponse
    {
        Password::sendResetLink($request->validated());

        return $this->success(['message' => 'If your email exists in our system, a password reset link has been sent.']);
    }

    public function resetPassword(ResetPasswordRequest $request): JsonResponse
    {
        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function (User $user, string $password): void {
                $user->forceFill([
                    'password' => Hash::make($password),
                    'remember_token' => Str::random(60),
                ])->save();

                event(new PasswordReset($user));
            }
        );

        if ($status == Password::PASSWORD_RESET) {
            return $this->success(['message' => 'Password has been reset.']);
        }

        return $this->error(__($status), 422);
    }

    /**
     * Resend email verification link
     */
    public function resendVerificationEmail(Request $request): JsonResponse
    {
        $request->validate([
            'email' => ['required', 'email', 'exists:users,email']
        ]);

        $user = User::where('email', $request->email)->first();

        if ($user->hasVerifiedEmail()) {
            return $this->error('Email is already verified.', 422);
        }

        // Send verification email
        if ($user instanceof MustVerifyEmail) {
            $user->sendEmailVerificationNotification();
        }

        return $this->success(['message' => 'Verification link sent!']);
    }

    /**
     * Logout user and invalidate token
     */
    public function logout(Request $request): JsonResponse
    {
        try {
            // Revoke the token that was used to authenticate the current request
            $request->user()->currentAccessToken()->delete();

            return $this->success([
                'message' => 'Successfully logged out'
            ]);
        } catch (\Exception $e) {
            return $this->error('Failed to logout', 500);
        }
    }

    /**
     * Get authenticated user details
     */
    public function me(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            
            return $this->success([
                'user' => $this->transformUser($user)
            ]);
        } catch (\Exception $e) {
            return $this->error('Failed to fetch user details', 500);
        }
    }

    /**
     * Generate SSO token and return redirect URL (industry standard JSON response)
     * Frontend will handle the redirect to external product
     */
    public function ssoRedirect(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            $productId = (int) $request->input('product_id', LinkedAccountConstants::PRODUCT_CONSOLE);

            // Generate SSO token
            $token = $this->ssoService->generateSsoToken($user, $productId);

            if (!$token) {
                Log::error('Failed to generate SSO token', [
                    'user_id' => $user->id,
                    'product_id' => $productId,
                ]);
                return $this->error('Failed to generate SSO token. Please try again.', 500);
            }

            // Get redirect URL
            $redirectUrl = $this->ssoService->getSsoRedirectUrl($productId, $token);

            if (!$redirectUrl) {
                Log::error('Invalid product ID for SSO redirect', [
                    'product_id' => $productId,
                ]);
                return $this->error('Invalid product. Please contact support.', 400);
            }

            // Return JSON with redirect URL (industry standard for API redirects)
            // Frontend will handle the redirect using window.open() or window.location.href
            return $this->success([
                'redirect_url' => $redirectUrl,
                'product_id' => $productId,
            ]);

        } catch (\Exception $e) {
            Log::error('SSO redirect failed', [
                'error' => $e->getMessage(),
                'user_id' => $request->user()?->id,
            ]);
            return $this->error('SSO redirect failed. Please try again.', 500);
        }
    }

    /**
     * Verify and mark JTI as used (for Console SSO verification)
     * This endpoint is called by Console to verify JTI before login
     * Industry standard: API-based verification (no direct DB access)
     */
    public function verifyJti(Request $request): JsonResponse
    {
        $request->validate([
            'jti' => ['required', 'string', 'max:255'],
        ]);

        try {
            $jti = $request->input('jti');
            
            // Find token in database
            $ssoToken = \App\Models\SsoToken::where('jti', $jti)->first();
            
            if (!$ssoToken) {
                Log::warning('JTI verification failed: Token not found', [
                    'jti' => $jti,
                    'ip' => $request->ip(),
                ]);
                return $this->error('Token not found', 404);
            }
            
            // Check if expired
            if ($ssoToken->isExpired()) {
                Log::warning('JTI verification failed: Token expired', [
                    'jti' => $jti,
                    'expires_at' => $ssoToken->expires_at,
                ]);
                return $this->error('Token expired', 400);
            }
            
            // Check if already used (replay attack prevention)
            if ($ssoToken->used) {
                Log::warning('JTI verification failed: Token already used (replay attack)', [
                    'jti' => $jti,
                    'ip' => $request->ip(),
                ]);
                return $this->error('Token already used', 400);
            }
            
            // Mark as used (atomic operation to prevent race conditions)
            $ssoToken->markAsUsed();
            
            Log::info('JTI verified and marked as used', [
                'jti' => $jti,
                'product_id' => $ssoToken->product_id,
                'ip' => $request->ip(),
            ]);
            
            return $this->success([
                'valid' => true,
                'jti' => $jti,
                'expires_at' => $ssoToken->expires_at->toIso8601String(),
            ]);
            
        } catch (\Exception $e) {
            Log::error('JTI verification failed', [
                'jti' => $request->input('jti'),
                'error' => $e->getMessage(),
                'ip' => $request->ip(),
            ]);
            
            return $this->error('Verification failed', 500);
        }
    }

    private function transformUser(User $user): array
    {
        // Avoid relation load errors if Spatie tables are not present yet
        try {
            $user->loadMissing(['roles', 'permissions']);
        } catch (\Throwable $e) {
            // ignore
        }

        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'email_verified_at' => optional($user->email_verified_at)?->toIso8601String(),
            'organization_name' => $user->organization_name ?? null,
            'roles' => method_exists($user, 'getRoleNames') ? $user->getRoleNames()->toArray() : [],
            'permissions' => method_exists($user, 'getAllPermissions') ? $user->getAllPermissions()->pluck('name')->toArray() : [],
        ];
    }

    /**
     * @return array{0:string,1:\Carbon\CarbonImmutable|null}
     */
    private function createTokenWithExpiry(User $user, string $name = 'api'): array
    {
        $minutes = (int) (config('sanctum.expiration') ?? 60);
        $expiresAt = now()->addMinutes($minutes)->toImmutable();
        $token = $user->createToken($name, ['*'], $expiresAt);
        return [$token->plainTextToken, $token->accessToken->expires_at];
    }

    /**
     * Get appropriate error message for license validation failure.
     */
    private function getLicenseErrorMessage(string $reason): string
    {
        switch ($reason) {
            case 'No license found':
                return 'Your account does not have a valid license. Please contact support.';
            case 'License is inactive':
                return 'Your license has been deactivated. Please contact support.';
            case 'License has expired':
                return 'Your license has expired. Please renew your subscription to continue using the service.';
            default:
                return 'License validation failed. Please contact support.';
        }
    }

    private function success(array $data = [], int $status = 200): JsonResponse
    {
        return response()->json(['success' => true, 'data' => $data], $status);
    }

    private function error(string $message, int $status = 400, array $meta = []): JsonResponse
    {
        return response()->json(['success' => false, 'error' => $message, 'meta' => $meta], $status);
    }
}


