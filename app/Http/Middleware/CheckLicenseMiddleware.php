<?php

namespace App\Http\Middleware;

use App\Services\LicenseService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class CheckLicenseMiddleware
{
    protected LicenseService $licenseService;

    public function __construct(LicenseService $licenseService)
    {
        $this->licenseService = $licenseService;
    }

    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure(\Illuminate\Http\Request): (\Illuminate\Http\Response|\Illuminate\Http\JsonResponse)  $next
     * @return \Illuminate\Http\Response|\Illuminate\Http\JsonResponse
     */
    public function handle(Request $request, Closure $next)
    {
        // Skip license check if disabled in environment
        if (!$this->licenseService->isLicenseCheckEnabled()) {
            return $next($request);
        }

        // Skip license check for unauthenticated users
        if (!Auth::check()) {
            return $next($request);
        }

        $user = Auth::user();

        // Validate license
        $validation = $this->licenseService->validateLicense($user);

        if (!$validation['valid']) {
            return $this->handleInvalidLicense($validation['reason'], $validation['license']);
        }

        return $next($request);
    }

    /**
     * Handle invalid license response.
     */
    private function handleInvalidLicense(string $reason, $license = null): JsonResponse
    {
        $response = [
            'success' => false,
            'message' => $this->getLicenseErrorMessage($reason),
            'error_code' => 'LICENSE_INVALID',
        ];

        if ($license) {
            $response['license_info'] = [
                'status' => $license->status,
                'expires_at' => $license->expires_at->toIso8601String(),
                'days_remaining' => $license->daysRemaining(),
            ];
        }

        return response()->json($response, 403);
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
}

