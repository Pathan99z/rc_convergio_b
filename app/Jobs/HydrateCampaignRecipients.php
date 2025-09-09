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

        Log::info('Hydrate recipients start', ['campaign_id' => $campaign->id, 'tenant_id' => $campaign->tenant_id]);

        // Honor pause
        if ($campaign->status === 'paused' || ($campaign->settings['paused'] ?? false)) {
            $this->release(30);
            return;
        }

        $settings = $campaign->settings ?? [];
        $mode = $settings['recipient_mode'] ?? null;
        $contactIds = $settings['recipient_contact_ids'] ?? [];
        $segmentId = $settings['segment_id'] ?? null;

        $tenantId = $campaign->tenant_id;

        $query = Contact::query()->where('tenant_id', $tenantId)->whereNotNull('email');

        if ($mode === 'segment' && $segmentId) {
            // list_members: list_id, contact_id
            $query->whereIn('id', function ($q) use ($segmentId) {
                $q->select('contact_id')->from('list_members')->where('list_id', $segmentId);
            });
        } elseif (in_array($mode, ['manual', 'static'], true) && !empty($contactIds)) {
            $query->whereIn('id', $contactIds);
        } else {
            // Nothing to hydrate
            return;
        }

        $now = now();
        $batch = [];
        $inserted = 0;
        $hasTenantColumn = Schema::hasColumn('campaign_recipients', 'tenant_id');
        $hasContactIdColumn = Schema::hasColumn('campaign_recipients', 'contact_id');

        $query->chunkById(500, function ($contacts) use (&$batch, &$inserted, $campaign, $now, $hasTenantColumn, $hasContactIdColumn) {
            $batch = [];
            foreach ($contacts as $contact) {
                $name = trim(($contact->first_name ? $contact->first_name : '') . ' ' . ($contact->last_name ? $contact->last_name : '')) ?: null;
                $batch[] = [
                    'campaign_id' => $campaign->id,
                    // include contact_id only if column exists
                    'contact_id' => $hasContactIdColumn ? $contact->id : null,
                    'email' => $contact->email,
                    'name' => $name,
                    'status' => 'pending',
                    // include tenant if column exists
                    'tenant_id' => $hasTenantColumn ? $campaign->tenant_id : null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }

            if (!empty($batch)) {
                // Upsert on (campaign_id, email)
                DB::table('campaign_recipients')->upsert(
                    array_map(function ($row) use ($hasTenantColumn, $hasContactIdColumn) {
                        if (!$hasTenantColumn) {
                            unset($row['tenant_id']);
                        }
                        if (!$hasContactIdColumn) {
                            unset($row['contact_id']);
                        }
                        return $row;
                    }, $batch),
                    ['campaign_id', 'email']
                );
                $inserted += count($batch);
            }
        });

        // Update hydrated count
        $total = DB::table('campaign_recipients')->where('campaign_id', $campaign->id)->count();
        $campaign->update(['total_recipients' => $total]);

        Log::info('Hydrate recipients end', ['campaign_id' => $campaign->id, 'inserted' => $inserted, 'total_after' => $total]);
    }
}


