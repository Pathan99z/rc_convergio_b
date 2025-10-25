<?php

namespace App\Listeners;

use App\Services\LicenseService;
use Illuminate\Auth\Events\Registered;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;

class CreateLicenseOnRegistration implements ShouldQueue
{
    use InteractsWithQueue;

    protected LicenseService $licenseService;

    /**
     * Create the event listener.
     */
    public function __construct(LicenseService $licenseService)
    {
        $this->licenseService = $licenseService;
    }

    /**
     * Handle the event.
     */
    public function handle(Registered $event): void
    {
        try {
            $user = $event->user;

            // Only create license for users who are tenants (have tenant_id set to their own ID)
            if ($user->tenant_id === $user->id) {
                $license = $this->licenseService->createLicenseForTenant($user);

                if ($license) {
                    Log::info('License automatically created for new tenant registration', [
                        'user_id' => $user->id,
                        'tenant_id' => $user->tenant_id,
                        'license_id' => $license->id,
                        'plan_name' => $license->plan->name,
                    ]);
                } else {
                    Log::error('Failed to create license for new tenant registration', [
                        'user_id' => $user->id,
                        'tenant_id' => $user->tenant_id,
                    ]);
                }
            } else {
                Log::info('Skipping license creation for non-tenant user registration', [
                    'user_id' => $user->id,
                    'tenant_id' => $user->tenant_id,
                ]);
            }

        } catch (\Exception $e) {
            Log::error('Exception occurred while creating license on registration', [
                'user_id' => $event->user->id ?? null,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(Registered $event, $exception): void
    {
        Log::error('Failed to process license creation for registration', [
            'user_id' => $event->user->id ?? null,
            'error' => $exception->getMessage(),
        ]);
    }
}