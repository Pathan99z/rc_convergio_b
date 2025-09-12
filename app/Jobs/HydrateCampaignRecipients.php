<?php

namespace App\Jobs;

use App\Models\Campaign;
use App\Models\Contact;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class HydrateCampaignRecipients implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $campaignId;

    public function __construct(int $campaignId)
    {
        $this->campaignId = $campaignId;
    }

    public function handle(): void
    {
        $campaign = Campaign::find($this->campaignId);
        if (!$campaign) {
            return;
        }

        Log::info('HydrateCampaignRecipients job called - audience should already be frozen', [
            'campaign_id' => $campaign->id, 
            'tenant_id' => $campaign->tenant_id
        ]);

        // This job is now deprecated since audience freezing happens in the controller
        // The audience is frozen at the moment of scheduling/sending
        // This job is kept for backward compatibility but does nothing
        
        Log::info('HydrateCampaignRecipients job completed - no action needed (audience pre-frozen)', [
            'campaign_id' => $campaign->id
        ]);
    }
}


