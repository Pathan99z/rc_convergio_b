<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CampaignRecipient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class CampaignWebhookController extends Controller
{
    public function handleEvents(Request $request): JsonResponse
    {
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
            Log::warning('Campaign webhook: Recipient not found', [
                'message_id' => $messageId,
                'email' => $email,
                'event_type' => $eventType,
            ]);
            return response()->json(['error' => 'Recipient not found'], 404);
        }

        // Update recipient status based on event type
        $this->processEvent($recipient, $eventType, $timestamp, $request->all());

        // Mark event as processed
        if ($eventId) {
            $this->markEventProcessed($eventId);
        }

        return response()->json(['message' => 'Event processed successfully']);
    }

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

    private function isEventProcessed(string $eventId): bool
    {
        // Check if event was already processed (implement with Redis or database)
        return false;
    }

    private function markEventProcessed(string $eventId): void
    {
        // Mark event as processed (implement with Redis or database)
    }

    private function processEvent(CampaignRecipient $recipient, string $eventType, $timestamp, array $data): void
    {
        switch ($eventType) {
            case 'delivered':
            case 'delivery':
                $recipient->update([
                    'status' => 'delivered',
                    'delivered_at' => $timestamp,
                    'metadata' => array_merge($recipient->metadata ?? [], $data),
                ]);
                break;

            case 'opened':
            case 'open':
                $recipient->update([
                    'status' => 'opened',
                    'opened_at' => $timestamp,
                    'metadata' => array_merge($recipient->metadata ?? [], $data),
                ]);
                break;

            case 'clicked':
            case 'click':
                $recipient->update([
                    'status' => 'clicked',
                    'clicked_at' => $timestamp,
                    'metadata' => array_merge($recipient->metadata ?? [], $data),
                ]);
                break;

            case 'bounced':
            case 'bounce':
                $recipient->update([
                    'status' => 'bounced',
                    'bounced_at' => $timestamp,
                    'bounce_reason' => $data['bounce_reason'] ?? $data['reason'] ?? null,
                    'metadata' => array_merge($recipient->metadata ?? [], $data),
                ]);
                break;

            case 'failed':
            case 'failure':
                $recipient->update([
                    'status' => 'failed',
                    'metadata' => array_merge($recipient->metadata ?? [], $data),
                ]);
                break;

            default:
                Log::info('Campaign webhook: Unknown event type', [
                    'event_type' => $eventType,
                    'recipient_id' => $recipient->id,
                ]);
        }

        // Update campaign metrics
        $this->updateCampaignMetrics($recipient->campaign);
    }

    private function updateCampaignMetrics($campaign): void
    {
        $recipients = $campaign->recipients();

        $campaign->update([
            'sent_count' => $recipients->whereIn('status', ['sent', 'delivered', 'opened', 'clicked'])->count(),
            'delivered_count' => $recipients->whereIn('status', ['delivered', 'opened', 'clicked'])->count(),
            'opened_count' => $recipients->whereIn('status', ['opened', 'clicked'])->count(),
            'clicked_count' => $recipients->where('status', 'clicked')->count(),
            'bounced_count' => $recipients->where('status', 'bounced')->count(),
        ]);
    }
}
