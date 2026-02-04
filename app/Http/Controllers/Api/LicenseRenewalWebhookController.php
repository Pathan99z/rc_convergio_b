<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\License;
use App\Models\Plan;
use App\Models\User;
use App\Services\Commerce\PayFastService;
use App\Services\LicenseService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class LicenseRenewalWebhookController extends Controller
{
    protected LicenseService $licenseService;

    public function __construct(LicenseService $licenseService)
    {
        $this->licenseService = $licenseService;
    }

    /**
     * Handle PayFast ITN (Instant Transaction Notification) for license renewal.
     * 
     * @param Request $request
     * @return \Illuminate\Http\Response
     */
    public function handle(Request $request)
    {
        $data = $request->all();
        
        Log::channel('commerce')->info('License renewal PayFast ITN received', [
            'data' => $data,
        ]);

        // Extract payment ID
        $paymentId = $data['m_payment_id'] ?? null;
        
        if (!$paymentId) {
            Log::channel('commerce')->error('License renewal PayFast ITN missing payment ID');
            return response('Missing payment ID', 400);
        }

        // Verify this is a license renewal payment
        if (strpos($paymentId, 'license_renewal_') !== 0) {
            Log::channel('commerce')->warning('PayFast ITN not a license renewal payment', [
                'payment_id' => $paymentId,
            ]);
            return response('Not a license renewal payment', 400);
        }

        // Use company PayFast (tenant_id = null) for verification
        $payfastService = new PayFastService(null);

        // Verify ITN signature
        if (!$payfastService->verifyITN($data)) {
            Log::channel('commerce')->error('Invalid PayFast ITN signature for license renewal', [
                'payment_id' => $paymentId,
            ]);
            return response('Invalid signature', 400);
        }

        $paymentStatus = $data['payment_status'] ?? '';
        $pfPaymentId = $data['pf_payment_id'] ?? $paymentId;
        $emailAddress = $data['email_address'] ?? '';

        if (empty($emailAddress)) {
            Log::channel('commerce')->error('License renewal PayFast ITN missing email address');
            return response('Missing email address', 400);
        }

        // Extract plan_id from payment_id: "license_renewal_{plan_id}_{timestamp}"
        $paymentIdParts = explode('_', $paymentId);
        if (count($paymentIdParts) < 3) {
            Log::channel('commerce')->error('Invalid license renewal payment ID format', [
                'payment_id' => $paymentId,
            ]);
            return response('Invalid payment ID format', 400);
        }

        $planId = (int) $paymentIdParts[2];

        try {
            DB::beginTransaction();

            // Get plan
            $plan = Plan::find($planId);
            if (!$plan) {
                Log::channel('commerce')->error('Plan not found for license renewal', [
                    'plan_id' => $planId,
                    'payment_id' => $paymentId,
                ]);
                DB::rollBack();
                return response('Plan not found', 404);
            }

            // Find tenant by email
            $tenant = User::where('email', $emailAddress)->first();
            if (!$tenant) {
                Log::channel('commerce')->error('Tenant not found for license renewal', [
                    'email' => $emailAddress,
                    'payment_id' => $paymentId,
                ]);
                DB::rollBack();
                return response('Tenant not found', 404);
            }

            // Check for duplicate events (using pf_payment_id)
            $existingTransaction = DB::table('commerce_transactions')
                ->where('provider_event_id', $pfPaymentId)
                ->where('payment_provider', 'payfast')
                ->first();

            if ($existingTransaction) {
                Log::channel('commerce')->info('Duplicate license renewal PayFast ITN event ignored', [
                    'pf_payment_id' => $pfPaymentId,
                    'payment_status' => $paymentStatus,
                ]);
                DB::rollBack();
                return response('OK', 200); // Return OK for duplicate
            }

            // Process payment based on status
            if ($paymentStatus === 'COMPLETE') {
                // Find or create license
                $license = $tenant->activeLicense();

                if ($license) {
                    // Extend existing license
                    $newExpiry = now()->addDays($plan->duration_days);
                    $license->update([
                        'expires_at' => $newExpiry,
                        'is_active' => true,
                        'plan_id' => $plan->id, // Update plan if changed
                    ]);

                    Log::channel('commerce')->info('License extended via renewal payment', [
                        'tenant_id' => $tenant->id,
                        'license_id' => $license->id,
                        'plan_id' => $plan->id,
                        'new_expiry' => $newExpiry,
                        'payment_id' => $paymentId,
                    ]);
                } else {
                    // Create new license
                    $license = $this->licenseService->createLicenseForTenant($tenant, $plan);

                    if (!$license) {
                        Log::channel('commerce')->error('Failed to create license for renewal payment', [
                            'tenant_id' => $tenant->id,
                            'plan_id' => $plan->id,
                            'payment_id' => $paymentId,
                        ]);
                        DB::rollBack();
                        return response('Failed to create license', 500);
                    }

                    Log::channel('commerce')->info('License created via renewal payment', [
                        'tenant_id' => $tenant->id,
                        'license_id' => $license->id,
                        'plan_id' => $plan->id,
                        'expires_at' => $license->expires_at,
                        'payment_id' => $paymentId,
                    ]);
                }

                // Create transaction record (optional, for tracking)
                try {
                    DB::table('commerce_transactions')->insert([
                        'payment_provider' => 'payfast',
                        'provider_event_id' => $pfPaymentId,
                        'amount' => (float) ($data['amount_gross'] ?? 0),
                        'currency' => strtoupper($data['currency'] ?? 'ZAR'),
                        'status' => 'succeeded',
                        'event_type' => 'license_renewal.complete',
                        'raw_payload' => json_encode($data),
                        'tenant_id' => $tenant->id,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                } catch (\Exception $e) {
                    // Log but don't fail the webhook if transaction record fails
                    Log::channel('commerce')->warning('Failed to create transaction record for license renewal', [
                        'error' => $e->getMessage(),
                        'payment_id' => $paymentId,
                    ]);
                }

                DB::commit();

                return response('OK', 200);
            } elseif ($paymentStatus === 'FAILED' || $paymentStatus === 'CANCELLED') {
                // Log failed payment
                Log::channel('commerce')->warning('License renewal payment failed', [
                    'tenant_id' => $tenant->id,
                    'plan_id' => $plan->id,
                    'payment_status' => $paymentStatus,
                    'payment_id' => $paymentId,
                ]);

                // Create transaction record for failed payment
                try {
                    DB::table('commerce_transactions')->insert([
                        'payment_provider' => 'payfast',
                        'provider_event_id' => $pfPaymentId,
                        'amount' => (float) ($data['amount_gross'] ?? 0),
                        'currency' => strtoupper($data['currency'] ?? 'ZAR'),
                        'status' => 'failed',
                        'event_type' => 'license_renewal.' . strtolower($paymentStatus),
                        'raw_payload' => json_encode($data),
                        'tenant_id' => $tenant->id,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                } catch (\Exception $e) {
                    Log::channel('commerce')->warning('Failed to create transaction record for failed license renewal', [
                        'error' => $e->getMessage(),
                        'payment_id' => $paymentId,
                    ]);
                }

                DB::commit();
                return response('OK', 200);
            } else {
                // Other statuses (PENDING, etc.)
                Log::channel('commerce')->info('License renewal payment in pending state', [
                    'tenant_id' => $tenant->id,
                    'plan_id' => $plan->id,
                    'payment_status' => $paymentStatus,
                    'payment_id' => $paymentId,
                ]);

                DB::commit();
                return response('OK', 200);
            }

        } catch (\Exception $e) {
            DB::rollBack();
            Log::channel('commerce')->error('License renewal webhook processing failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'payment_id' => $paymentId,
                'data' => $data,
            ]);

            return response('Internal server error', 500);
        }
    }
}


