<?php

namespace App\Listeners;

use App\Models\CampaignRecipient;
use App\Services\CampaignIntentService;
use Illuminate\Support\Facades\Log;

class CampaignIntentListener
{
    protected $campaignIntentService;

    public function __construct(CampaignIntentService $campaignIntentService)
    {
        $this->campaignIntentService = $campaignIntentService;
    }

    /**
     * Handle email opened events.
     */
    public function handleEmailOpened($event): void
    {
        try {
            if (isset($event->recipient) && $event->recipient instanceof CampaignRecipient) {
                $this->campaignIntentService->convertEmailOpenToIntent($event->recipient, $event->webhookData ?? []);
            }
        } catch (\Exception $e) {
            Log::error('Failed to process email opened intent event', [
                'error' => $e->getMessage(),
                'event' => $event
            ]);
        }
    }

    /**
     * Handle email clicked events.
     */
    public function handleEmailClicked($event): void
    {
        try {
            if (isset($event->recipient) && $event->recipient instanceof CampaignRecipient) {
                $this->campaignIntentService->convertEmailClickToIntent($event->recipient, $event->webhookData ?? []);
            }
        } catch (\Exception $e) {
            Log::error('Failed to process email clicked intent event', [
                'error' => $e->getMessage(),
                'event' => $event
            ]);
        }
    }

    /**
     * Handle campaign landing view events.
     */
    public function handleCampaignLandingView($event): void
    {
        try {
            if (isset($event->recipient) && $event->recipient instanceof CampaignRecipient) {
                $landingUrl = $event->landingUrl ?? $event->url ?? null;
                if ($landingUrl) {
                    $this->campaignIntentService->convertCampaignLandingToIntent($event->recipient, $landingUrl, $event->webhookData ?? []);
                }
            }
        } catch (\Exception $e) {
            Log::error('Failed to process campaign landing view intent event', [
                'error' => $e->getMessage(),
                'event' => $event
            ]);
        }
    }
}
