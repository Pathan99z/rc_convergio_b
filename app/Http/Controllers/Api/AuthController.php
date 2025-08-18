<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ForgotPasswordRequest;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Http\Requests\Auth\ResetPasswordRequest;
use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Auth\Events\Registered;
use Illuminate\Auth\Events\Verified;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;

class AuthController extends Controller
{
    public function register(RegisterRequest $request): JsonResponse
    {
        $data = $request->validated();

        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => $data['password'],
            'organization_name' => $data['organization_name'],
        ]);

        event(new Registered($user));

        if ($user instanceof MustVerifyEmail) {
            $user->sendEmailVerificationNotification();
        }

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

        RateLimiter::clear($key);

        [$plainTextToken, $expiresAt] = $this->createTokenWithExpiry($user, 'login');

        return $this->success([
            'access_token' => $plainTextToken,
            'expires_at' => $expiresAt?->toIso8601String(),
            'user' => $this->transformUser($user),
        ]);
    }

    public function verifyEmail(Request $request): JsonResponse
    {
        $request->validate([
            'id' => ['required', 'integer'],
            'hash' => ['required', 'string'],
        ]);

        $user = User::findOrFail((int) $request->input('id'));

        if (! URL::hasValidSignature($request)) {
            return $this->error('Invalid or expired verification link.', 403);
        }

        if ($user->hasVerifiedEmail()) {
            return $this->success(['message' => 'Email already verified.']);
        }

        if (! hash_equals((string) $request->route('hash', $request->input('hash')), sha1($user->getEmailForVerification()))) {
            return $this->error('Invalid verification hash.', 403);
        }

        if ($user->markEmailAsVerified()) {
            if ($user instanceof MustVerifyEmail) {
                event(new Verified($user));
            }
        }

        return $this->success(['message' => 'Email verified successfully.']);
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

    private function success(array $data = [], int $status = 200): JsonResponse
    {
        return response()->json(['success' => true, 'data' => $data], $status);
    }

    private function error(string $message, int $status = 400, array $meta = []): JsonResponse
    {
        return response()->json(['success' => false, 'error' => $message, 'meta' => $meta], $status);
    }
}


