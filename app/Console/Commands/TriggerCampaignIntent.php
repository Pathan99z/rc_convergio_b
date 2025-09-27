<?php

namespace App\Console\Commands;

use App\Models\CampaignRecipient;
use App\Services\CampaignIntentService;
use Illuminate\Console\Command;

class TriggerCampaignIntent extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'campaign:intent {action} {--recipient= : Recipient ID} {--campaign= : Campaign ID} {--url= : Landing URL for campaign_view}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Manually trigger campaign intent events for testing';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $action = $this->argument('action');
        $recipientId = $this->option('recipient');
        $campaignId = $this->option('campaign');
        $landingUrl = $this->option('url');

        $this->info("Triggering campaign intent event: {$action}");

        try {
            $campaignIntentService = new CampaignIntentService();

            // Find recipient
            $query = CampaignRecipient::query();
            
            if ($recipientId) {
                $query->where('id', $recipientId);
            } elseif ($campaignId) {
                $query->where('campaign_id', $campaignId)->first();
            } else {
                $this->error('Please specify either --recipient or --campaign');
                return 1;
            }

            $recipient = $query->first();

            if (!$recipient) {
                $this->error('Recipient not found');
                return 1;
            }

            $this->info("Found recipient: {$recipient->email} (Campaign: {$recipient->campaign->name})");

            // Trigger appropriate intent event
            $intentEvent = null;
            switch ($action) {
                case 'email_open':
                    $intentEvent = $campaignIntentService->convertEmailOpenToIntent($recipient);
                    break;

                case 'email_click':
                    $intentEvent = $campaignIntentService->convertEmailClickToIntent($recipient);
                    break;

                case 'campaign_view':
                    if (!$landingUrl) {
                        $this->error('Please specify --url for campaign_view action');
                        return 1;
                    }
                    $intentEvent = $campaignIntentService->convertCampaignLandingToIntent($recipient, $landingUrl);
                    break;

                default:
                    $this->error("Unknown action: {$action}. Valid actions: email_open, email_click, campaign_view");
                    return 1;
            }

            if ($intentEvent) {
                $this->info("✅ Intent event created successfully!");
                $this->info("Intent Event ID: {$intentEvent->id}");
                $this->info("Action: {$intentEvent->event_name}");
                $this->info("Score: {$intentEvent->intent_score}");
                $this->info("Contact ID: {$intentEvent->contact_id}");
                $this->info("Company ID: {$intentEvent->company_id}");
            } else {
                $this->error("❌ Failed to create intent event");
                return 1;
            }

        } catch (\Exception $e) {
            $this->error("❌ Error: " . $e->getMessage());
            return 1;
        }

        return 0;
    }
}