<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CampaignRecipient;
use App\Services\CampaignIntentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class CampaignIntentWebhookController extends Controller
{
    protected $campaignIntentService;

    public function __construct(CampaignIntentService $campaignIntentService)
    {
        $this->campaignIntentService = $campaignIntentService;
    }

    /**
     * Handle campaign events and convert to intent events.
     * This is a NEW endpoint that doesn't interfere with existing webhook functionality.
     */
    public function handleIntentEvents(Request $request): JsonResponse
    {
        try {
            // Verify webhook signature (implement based on your email provider)
            if (!$this->verifySignature($request)) {
                return response()->json(['error' => 'Invalid signature'], 401);
            }

            // Ensure idempotency
            $eventId = $request->input('event_id') ?? $request->input('message_id');
            if ($eventId && $this->isEventProcessed($eventId)) {
                return response()->json(['message' => 'Event already processed']);
            }

            $eventType = $request->input('event_type') ?? $request->input('type');
            $messageId = $request->input('message_id');
            $email = $request->input('email');
            $timestamp = $request->input('timestamp') ?? now();

            // Find the recipient by message_id or email
            $recipient = CampaignRecipient::where('message_id', $messageId)
                ->orWhere('email', $email)
                ->first();

            if (!$recipient) {
                Log::warning('Campaign intent webhook: Recipient not found', [
                    'message_id' => $messageId,
                    'email' => $email,
                    'event_type' => $eventType,
                ]);
                return response()->json(['error' => 'Recipient not found'], 404);
            }

            // Convert campaign event to intent event
            $intentEvent = $this->processIntentEvent($recipient, $eventType, $request->all());

            // Mark event as processed
            if ($eventId) {
                $this->markEventProcessed($eventId);
            }

            if ($intentEvent) {
                return response()->json([
                    'message' => 'Campaign intent event processed successfully',
                    'intent_event_id' => $intentEvent->id,
                    'score' => $intentEvent->intent_score
                ]);
            } else {
                return response()->json([
                    'message' => 'Campaign event processed but no intent event created'
                ]);
            }

        } catch (\Exception $e) {
            Log::error('Failed to process campaign intent webhook', [
                'error' => $e->getMessage(),
                'request_data' => $request->all()
            ]);

            return response()->json([
                'error' => 'Failed to process campaign intent event'
            ], 500);
        }
    }

    /**
     * Process campaign event and convert to intent event.
     */
    private function processIntentEvent(CampaignRecipient $recipient, string $eventType, array $webhookData): ?\App\Models\IntentEvent
    {
        switch ($eventType) {
            case 'opened':
            case 'open':
                return $this->campaignIntentService->convertEmailOpenToIntent($recipient, $webhookData);

            case 'clicked':
            case 'click':
                return $this->campaignIntentService->convertEmailClickToIntent($recipient, $webhookData);

            case 'landing_view':
            case 'landing_page_view':
                $landingUrl = $webhookData['url'] ?? $webhookData['landing_url'] ?? null;
                if ($landingUrl) {
                    return $this->campaignIntentService->convertCampaignLandingToIntent($recipient, $landingUrl, $webhookData);
                }
                break;

            default:
                Log::info('Campaign intent webhook: Event type not mapped to intent', [
                    'event_type' => $eventType,
                    'recipient_id' => $recipient->id,
                ]);
        }

        return null;
    }

    /**
     * Get campaign intent statistics.
     */
    public function getCampaignIntentStats(Request $request): JsonResponse
    {
        try {
            $tenantId = auth()->user()->tenant_id;
            $campaignId = $request->get('campaign_id');

            $stats = $this->campaignIntentService->getCampaignIntentStats($tenantId, $campaignId);

            return response()->json([
                'data' => $stats,
                'message' => 'Campaign intent statistics retrieved successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to retrieve campaign intent statistics',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Verify webhook signature (implement based on your email provider).
     */
    private function verifySignature(Request $request): bool
    {
        // Implement signature verification based on your email provider
        // Example for SendGrid:
        // $signature = $request->header('X-Twilio-Email-Event-Webhook-Signature');
        // $timestamp = $request->header('X-Twilio-Email-Event-Webhook-Timestamp');
        // $payload = $timestamp . $request->getContent();
        // $expectedSignature = hash_hmac('sha256', $payload, config('services.sendgrid.webhook_secret'));
        
        // For now, return true (implement proper verification)
        return true;
    }

    /**
     * Check if event was already processed.
     */
    private function isEventProcessed(string $eventId): bool
    {
        // Check if event was already processed (implement with Redis or database)
        return false;
    }

    /**
     * Mark event as processed.
     */
    private function markEventProcessed(string $eventId): void
    {
        // Mark event as processed (implement with Redis or database)
    }
}
