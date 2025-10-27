<?php

namespace App\Console\Commands;

use App\Models\Campaign;
use App\Models\CampaignRecipient;
use App\Jobs\SendCampaignEmails;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class FixStuckCampaigns extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'campaigns:fix-stuck {--force : Force fix without confirmation}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Fix campaigns that are stuck in sending status';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Checking for stuck campaigns...');
        
        // Find campaigns stuck in sending status
        $stuckCampaigns = Campaign::where('status', 'sending')
            ->where('created_at', '<', now()->subMinutes(5)) // Stuck for more than 5 minutes
            ->get();

        if ($stuckCampaigns->isEmpty()) {
            $this->info('No stuck campaigns found.');
            return 0;
        }

        $this->info("Found {$stuckCampaigns->count()} stuck campaign(s):");
        
        foreach ($stuckCampaigns as $campaign) {
            $this->line("- Campaign ID: {$campaign->id}, Name: {$campaign->name}");
            $this->line("  Status: {$campaign->status}, Sent: {$campaign->sent_count}/{$campaign->total_recipients}");
        }

        if (!$this->option('force')) {
            if (!$this->confirm('Do you want to fix these stuck campaigns?')) {
                $this->info('Operation cancelled.');
                return 0;
            }
        }

        $fixed = 0;
        foreach ($stuckCampaigns as $campaign) {
            $this->info("Processing campaign: {$campaign->name}");
            
            // Check if campaign has recipients
            $recipients = $campaign->recipients()->count();
            if ($recipients === 0) {
                $this->warn("Campaign {$campaign->id} has no recipients. Resetting to draft.");
                $campaign->update(['status' => 'draft']);
                $fixed++;
                continue;
            }

            // Check if all recipients are already sent
            $pendingRecipients = $campaign->recipients()->where('status', 'pending')->count();
            if ($pendingRecipients === 0) {
                $this->info("Campaign {$campaign->id} has no pending recipients. Marking as sent.");
                $campaign->update([
                    'status' => 'sent',
                    'sent_at' => now(),
                ]);
                $fixed++;
                continue;
            }

            // Re-dispatch the campaign sending job
            $this->info("Re-dispatching campaign {$campaign->id} for sending...");
            SendCampaignEmails::dispatch($campaign->id);
            
            Log::info('Stuck campaign re-dispatched', [
                'campaign_id' => $campaign->id,
                'campaign_name' => $campaign->name,
                'pending_recipients' => $pendingRecipients,
            ]);
            
            $fixed++;
        }

        $this->info("Fixed {$fixed} stuck campaign(s).");
        return 0;
    }
}
