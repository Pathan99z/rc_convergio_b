<?php

namespace App\Console\Commands;

use App\Models\Campaign;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Bus;

class CampaignSendNow extends Command
{
    protected $signature = 'campaign:send-now {id} {--delay=}';
    protected $description = 'Dispatch hydration + sending chain for a campaign';

    public function handle(): int
    {
        $id = (int) $this->argument('id');
        $campaign = Campaign::find($id);
        if (!$campaign) {
            $this->error('Campaign not found');
            return self::FAILURE;
        }

        $campaign->update(['status' => 'sending']);

        $chain = Bus::chain([
            new \App\Jobs\HydrateCampaignRecipients($campaign->id),
            new \App\Jobs\SendCampaignEmails($campaign->id),
        ]);

        $delay = $this->option('delay');
        if ($delay) {
            $chain->delay(now()->addSeconds((int) $delay));
        }
        $chain->dispatch();

        $this->info('Dispatched hydration + sending chain for campaign ID ' . $campaign->id);
        return self::SUCCESS;
    }
}


