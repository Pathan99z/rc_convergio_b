<?php

namespace App\Services;

use App\Models\Contact;
use App\Models\Collateral;
use App\Models\CollateralSent;
use App\Models\Activity;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;
use Exception;

class CollateralMailer
{
    /**
     * Send collaterals via email to a contact.
     */
    public function sendCollaterals(Contact $contact, Collection $collaterals, ?string $message = null): bool
    {
        try {
            if (!$contact->email) {
                throw new Exception('Contact does not have a valid email address');
            }

            // Load product relationships
            $collaterals->load('product');

            // Configure email for tenant
            SetConfigEmail($contact->tenant_id);

            // Send email
            Mail::to($contact->email)
                ->send(new \App\Mail\CollateralMail($contact, $collaterals, $message));

            // Create tracking records
            $this->createSentRecords($contact, $collaterals, $message);

            // Log activity
            $this->logActivity($contact, $collaterals, $message);

            Log::info('Collateral email sent', [
                'contact_id' => $contact->id,
                'contact_email' => $contact->email,
                'collateral_count' => $collaterals->count(),
                'collateral_ids' => $collaterals->pluck('id')->toArray(),
            ]);

            return true;
        } catch (Exception $e) {
            Log::error('Failed to send collateral email', [
                'contact_id' => $contact->id,
                'contact_email' => $contact->email ?? 'N/A',
                'collateral_ids' => $collaterals->pluck('id')->toArray(),
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Create tracking records for sent collaterals.
     */
    private function createSentRecords(Contact $contact, Collection $collaterals, ?string $message): void
    {
        $user = auth()->user();
        $tenantId = $user->tenant_id ?? $user->id;
        $sentAt = now();

        foreach ($collaterals as $collateral) {
            CollateralSent::create([
                'contact_id' => $contact->id,
                'collateral_id' => $collateral->id,
                'sent_by' => $user->id,
                'message' => $message,
                'sent_at' => $sentAt,
                'tenant_id' => $tenantId,
            ]);
        }
    }

    /**
     * Log activity for sending collaterals.
     */
    private function logActivity(Contact $contact, Collection $collaterals, ?string $message): void
    {
        $user = auth()->user();
        $tenantId = $user->tenant_id ?? $user->id;
        
        $productNames = $collaterals->pluck('product.name')->unique()->implode(', ');
        $collateralCount = $collaterals->count();

        Activity::create([
            'type' => 'collateral_sent',
            'subject' => "Collateral sent to {$contact->first_name} {$contact->last_name}",
            'description' => "Sent {$collateralCount} collateral(s) for Product(s): {$productNames}",
            'tenant_id' => $tenantId,
            'owner_id' => $user->id,
            'related_type' => 'App\\Models\\Contact',
            'related_id' => $contact->id,
            'metadata' => [
                'collateral_ids' => $collaterals->pluck('id')->toArray(),
                'product_ids' => $collaterals->pluck('product_id')->unique()->toArray(),
                'message' => $message,
                'contact_email' => $contact->email,
            ],
        ]);
    }
}

