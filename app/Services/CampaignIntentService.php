<?php

namespace App\Services;

use App\Models\IntentEvent;
use App\Models\CampaignRecipient;
use App\Models\Contact;
use App\Models\Company;
use App\Services\ScoringEngineService;
use App\Services\UrlNormalizerService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class CampaignIntentService
{
    protected $scoringEngine;
    protected $urlNormalizer;

    public function __construct()
    {
        $this->scoringEngine = new ScoringEngineService();
        $this->urlNormalizer = new UrlNormalizerService();
    }

    /**
     * Check if buyer intent processing is disabled for development.
     */
    private function isBuyerIntentDisabled(): bool
    {
        return config('app.dev_mode.disable_buyer_intent_processing', false) && 
               config('app.env') === 'local';
    }

    /**
     * Convert email open event to intent event.
     */
    public function convertEmailOpenToIntent(CampaignRecipient $recipient, array $webhookData = []): ?IntentEvent
    {
        try {
            $eventData = $this->buildEventData('email_open', $recipient, $webhookData);
            $eventData['page_url'] = $this->getEmailPageUrl($recipient, 'open');
            
            return $this->createIntentEvent($recipient, $eventData, 10); // Base score 10

        } catch (\Exception $e) {
            Log::error('Failed to convert email open to intent', [
                'recipient_id' => $recipient->id,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * Convert email click event to intent event.
     */
    public function convertEmailClickToIntent(CampaignRecipient $recipient, array $webhookData = []): ?IntentEvent
    {
        try {
            $eventData = $this->buildEventData('email_click', $recipient, $webhookData);
            $eventData['page_url'] = $this->getEmailPageUrl($recipient, 'click', $webhookData['url'] ?? null);
            
            return $this->createIntentEvent($recipient, $eventData, 25); // Base score 25

        } catch (\Exception $e) {
            Log::error('Failed to convert email click to intent', [
                'recipient_id' => $recipient->id,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * Convert campaign landing view to intent event.
     */
    public function convertCampaignLandingToIntent(CampaignRecipient $recipient, string $landingUrl, array $webhookData = []): ?IntentEvent
    {
        try {
            $eventData = $this->buildEventData('campaign_view', $recipient, $webhookData);
            $eventData['page_url'] = $landingUrl;
            
            return $this->createIntentEvent($recipient, $eventData, 15); // Base score 15

        } catch (\Exception $e) {
            Log::error('Failed to convert campaign landing to intent', [
                'recipient_id' => $recipient->id,
                'landing_url' => $landingUrl,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * Build event data for intent event.
     */
    private function buildEventData(string $action, CampaignRecipient $recipient, array $webhookData = []): array
    {
        $contact = $recipient->contact;
        $campaign = $recipient->campaign;

        return [
            'action' => $action,
            'duration_seconds' => null,
            'metadata' => [
                'campaign_id' => $campaign->id,
                'campaign_name' => $campaign->name,
                'campaign_type' => $campaign->type,
                'recipient_id' => $recipient->id,
                'message_id' => $recipient->message_id,
                'webhook_data' => $webhookData,
                'source' => 'campaign_webhook',
            ],
            'session_id' => $this->generateSessionId($recipient),
            'rc_vid' => $this->generateRcVid($recipient),
            'referrer' => $this->getReferrer($recipient, $webhookData),
            'contact_id' => $contact?->id,
            'company_id' => $contact?->company_id,
        ];
    }

    /**
     * Create intent event from campaign data.
     */
    private function createIntentEvent(CampaignRecipient $recipient, array $eventData, int $baseScore): ?IntentEvent
    {
        try {
            $tenantId = $recipient->tenant_id;
            $normalizedPageUrl = $this->urlNormalizer->normalize($eventData['page_url']);
            
            // Generate idempotency key
            $idempotencyKey = $this->generateIdempotencyKey($recipient, $eventData['action']);

            // Check for existing event to prevent duplicates
            $existingEvent = IntentEvent::where('tenant_id', $tenantId)
                ->whereRaw('JSON_EXTRACT(event_data, "$.idempotency_key") = ?', [$idempotencyKey])
                ->first();

            if ($existingEvent) {
                Log::info('Campaign intent event already exists', [
                    'idempotency_key' => $idempotencyKey,
                    'existing_event_id' => $existingEvent->id
                ]);
                return $existingEvent;
            }

            // Calculate score using scoring engine
            $finalScore = $this->scoringEngine->scoreFor($eventData, $tenantId);

            // Create intent event
            $intentEvent = IntentEvent::create([
                'event_type' => 'campaign_action',
                'event_name' => $eventData['action'],
                'event_data' => json_encode([
                    'page_url' => $eventData['page_url'],
                    'page_url_normalized' => $normalizedPageUrl,
                    'duration_seconds' => $eventData['duration_seconds'],
                    'action' => $eventData['action'],
                    'metadata' => $eventData['metadata'],
                    'session_id' => $eventData['session_id'],
                    'rc_vid' => $eventData['rc_vid'],
                    'referrer' => $eventData['referrer'],
                    'idempotency_key' => $idempotencyKey,
                    'status' => 'completed',
                ]),
                'intent_score' => $finalScore,
                'source' => 'campaign_webhook',
                'session_id' => $eventData['session_id'],
                'ip_address' => $this->getIpAddress($eventData),
                'user_agent' => $this->getUserAgent($eventData),
                'metadata' => json_encode(array_merge($eventData['metadata'], [
                    'idempotency_key' => $idempotencyKey,
                    'status' => 'completed',
                    'page_url_normalized' => $normalizedPageUrl,
                    'page_category' => $this->urlNormalizer->getPageCategory($normalizedPageUrl),
                    'is_high_value_page' => $this->urlNormalizer->isHighValuePage($normalizedPageUrl),
                    'base_score' => $baseScore,
                ])),
                'company_id' => $eventData['company_id'],
                'contact_id' => $eventData['contact_id'],
                'tenant_id' => $tenantId,
            ]);

            Log::info('Campaign intent event created', [
                'intent_event_id' => $intentEvent->id,
                'action' => $eventData['action'],
                'score' => $finalScore,
                'campaign_id' => $recipient->campaign_id,
                'contact_id' => $eventData['contact_id']
            ]);

            return $intentEvent;

        } catch (\Exception $e) {
            Log::error('Failed to create campaign intent event', [
                'recipient_id' => $recipient->id,
                'action' => $eventData['action'],
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * Get page URL for email events.
     */
    private function getEmailPageUrl(CampaignRecipient $recipient, string $eventType, ?string $clickUrl = null): string
    {
        if ($eventType === 'click' && $clickUrl) {
            return $clickUrl;
        }

        // For email opens, use a virtual URL representing the email
        $campaign = $recipient->campaign;
        return "email://{$campaign->id}/{$recipient->id}";
    }

    /**
     * Generate session ID for campaign events.
     */
    private function generateSessionId(CampaignRecipient $recipient): string
    {
        return 'campaign_' . $recipient->campaign_id . '_' . $recipient->id . '_' . time();
    }

    /**
     * Generate RC VID for campaign events.
     */
    private function generateRcVid(CampaignRecipient $recipient): string
    {
        return 'campaign_' . $recipient->campaign_id . '_' . $recipient->contact_id . '_' . Str::random(8);
    }

    /**
     * Generate idempotency key for campaign events.
     */
    private function generateIdempotencyKey(CampaignRecipient $recipient, string $action): string
    {
        $timestamp = now()->format('Y-m-d-H');
        return "campaign_{$recipient->campaign_id}_{$recipient->id}_{$action}_{$timestamp}";
    }

    /**
     * Get referrer for campaign events.
     */
    private function getReferrer(CampaignRecipient $recipient, array $webhookData = []): ?string
    {
        return $webhookData['referrer'] ?? $webhookData['user_agent'] ?? 'campaign_email';
    }

    /**
     * Get IP address from webhook data.
     */
    private function getIpAddress(array $eventData): ?string
    {
        return $eventData['metadata']['webhook_data']['ip'] ?? 
               $eventData['metadata']['webhook_data']['ip_address'] ?? 
               request()->ip();
    }

    /**
     * Get user agent from webhook data.
     */
    private function getUserAgent(array $eventData): ?string
    {
        return $eventData['metadata']['webhook_data']['user_agent'] ?? 
               $eventData['metadata']['webhook_data']['ua'] ?? 
               request()->userAgent();
    }

    /**
     * Get campaign intent statistics.
     */
    public function getCampaignIntentStats(int $tenantId, ?int $campaignId = null): array
    {
        // Note: Removed development mode restriction to allow real data display

        $query = IntentEvent::where('tenant_id', $tenantId)
            ->where('source', 'campaign_webhook');

        if ($campaignId) {
            $query->whereRaw('JSON_EXTRACT(event_data, "$.metadata.campaign_id") = ?', [$campaignId]);
        }

        $events = $query->get();

        $stats = [
            'total_campaign_events' => $events->count(),
            'email_opens' => $events->where('event_name', 'email_open')->count(),
            'email_clicks' => $events->where('event_name', 'email_click')->count(),
            'campaign_views' => $events->where('event_name', 'campaign_view')->count(),
            'average_score' => $events->avg('intent_score'),
            'high_intent_events' => $events->where('intent_score', '>=', 60)->count(),
            'campaigns_with_intent' => $events->pluck('event_data')
                ->map(function($data) {
                    $decoded = json_decode($data, true);
                    return $decoded['metadata']['campaign_id'] ?? null;
                })
                ->filter()
                ->unique()
                ->count(),
        ];

        return $stats;
    }
}
