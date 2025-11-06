<?php

namespace App\Console\Commands;

use App\Models\Campaign;
use App\Jobs\SendCampaignEmails;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Log;

class ProcessScheduledCampaigns extends Command
{
    protected $signature = 'campaigns:process-scheduled';
    protected $description = 'Process scheduled campaigns that are due to be sent (fallback mechanism)';

    public function handle(): int
    {
        $now = now();
        
        // Find campaigns with status "scheduled" OR "draft" that have scheduled_at in the past or now
        // Use <= to include campaigns that are due now or were due in the past
        $dueCampaigns = Campaign::whereIn('status', ['scheduled', 'draft'])
            ->whereNotNull('scheduled_at')
            ->where('scheduled_at', '<=', $now)
            ->get();

        if ($dueCampaigns->isEmpty()) {
            Log::debug('No scheduled campaigns due at this time', [
                'current_time' => $now->toIso8601String(),
                'timezone' => config('app.timezone'),
            ]);
            return self::SUCCESS;
        }

        $this->info("Found {$dueCampaigns->count()} overdue scheduled campaign(s) at {$now->format('Y-m-d H:i:s')}");
        
        $processed = 0;
        foreach ($dueCampaigns as $campaign) {
            $scheduledTime = $campaign->scheduled_at->format('Y-m-d H:i:s');
            $this->line("Processing campaign: {$campaign->name} (ID: {$campaign->id}, Status: {$campaign->status}, Scheduled: {$scheduledTime})");
            
            // Update status to sending
            $campaign->update(['status' => 'sending']);
            
            // FREEZE AUDIENCE: Resolve and save contacts at the moment of sending
            // This ensures the audience is frozen before sending
            $this->freezeCampaignAudience($campaign);
            
            // Dispatch the sending job immediately (no delay since it's already past due)
            Bus::chain([
                new SendCampaignEmails($campaign->id),
            ])->dispatch();
            
            Log::info('Scheduled campaign processed and dispatched', [
                'campaign_id' => $campaign->id,
                'campaign_name' => $campaign->name,
                'scheduled_at' => $campaign->scheduled_at->toIso8601String(),
                'processed_at' => $now->toIso8601String(),
                'time_difference_seconds' => $now->diffInSeconds($campaign->scheduled_at),
            ]);
            
            $processed++;
        }

        if ($processed > 0) {
            $this->info("✅ Processed {$processed} overdue scheduled campaign(s).");
        }
        
        return self::SUCCESS;
    }

    /**
     * FREEZE AUDIENCE: Resolve and save contacts at the moment of sending
     * This prevents dynamic segments from changing the campaign audience later
     * Copied from CampaignsController to ensure consistency
     */
    private function freezeCampaignAudience(Campaign $campaign): void
    {
        $settings = $campaign->settings ?? [];
        $mode = $settings['recipient_mode'] ?? null;
        $contactIds = $settings['recipient_contact_ids'] ?? [];
        $segmentId = $settings['segment_id'] ?? null;
        $tenantId = $campaign->tenant_id;

        Log::info('Freezing campaign audience', [
            'campaign_id' => $campaign->id,
            'mode' => $mode,
            'contact_ids_count' => count($contactIds),
            'segment_id' => $segmentId
        ]);

        // Clear existing recipients to ensure clean state
        \Illuminate\Support\Facades\DB::table('campaign_recipients')->where('campaign_id', $campaign->id)->delete();

        $query = \App\Models\Contact::query()
            ->where('tenant_id', $tenantId)
            ->whereNotNull('email')
            ->whereNotExists(function ($q) {
                $q->select(\Illuminate\Support\Facades\DB::raw(1))
                  ->from('contact_subscriptions')
                  ->whereColumn('contact_subscriptions.contact_id', 'contacts.id')
                  ->where('contact_subscriptions.unsubscribed', true);
            }); // SUPPRESSION: Exclude unsubscribed contacts

        if ($mode === 'segment' && $segmentId) {
            // Resolve dynamic segment at this moment and freeze it
            $query->whereIn('id', function ($q) use ($segmentId) {
                $q->select('contact_id')->from('list_members')->where('list_id', $segmentId);
            });
        } elseif ($mode === 'manual' && !empty($contactIds)) {
            // Use manually selected contacts
            $query->whereIn('id', $contactIds);
        } else {
            // No recipients configured - return early
            Log::warning('Campaign has no recipients configured', ['campaign_id' => $campaign->id]);
            return;
        }

        $contacts = $query->get();

        // Create campaign recipients from frozen audience
        foreach ($contacts as $contact) {
            \Illuminate\Support\Facades\DB::table('campaign_recipients')->insert([
                'campaign_id' => $campaign->id,
                'contact_id' => $contact->id,
                'email' => $contact->email,
                'status' => 'pending',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // Update total recipients count
        $campaign->update(['total_recipients' => $contacts->count()]);

        Log::info('Campaign audience frozen', [
            'campaign_id' => $campaign->id,
            'recipients_count' => $contacts->count()
        ]);
    }
}

