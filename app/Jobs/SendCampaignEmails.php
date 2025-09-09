<?php

namespace App\Jobs;

use App\Models\Campaign;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class SendCampaignEmails implements ShouldQueue
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

        // Respect pause flag
        if ($campaign->status === 'paused' || ($campaign->settings['paused'] ?? false)) {
            $this->release(30);
            return;
        }

        $now = now();
        $sent = 0;
        $delivered = 0;
        $bounced = 0;
        Log::info('Campaign send start', ['campaign_id' => $campaign->id, 'tenant_id' => $campaign->tenant_id]);

        DB::table('campaign_recipients')
            ->where('campaign_id', $campaign->id)
            ->where('status', 'pending')
            ->orderBy('id')
            ->chunkById(200, function ($rows) use (&$sent, &$delivered, &$bounced, $campaign, $now) {
                foreach ($rows as $row) {
                    // Basic variable replacement
                    $name = $row->name ?: 'there';
                    $subject = str_replace(['{{name}}'], [$name], $campaign->subject);
                    $html = str_replace(['{{name}}'], [$name], $campaign->content);

                    try {
                        // Symfony Mailer: use html() to set HTML body
                        Mail::html($html, function ($m) use ($row, $subject) {
                            $m->to($row->email, $row->name);
                            $m->subject($subject);
                        });

                        DB::table('campaign_recipients')->where('id', $row->id)->update([
                            'status' => 'sent',
                            'sent_at' => $now,
                            'delivered_at' => $now,
                            'bounced_at' => null,
                            'error_message' => null,
                            'updated_at' => $now,
                        ]);
                        $sent++;
                        $delivered++;
                    } catch (\Throwable $e) {
                        DB::table('campaign_recipients')->where('id', $row->id)->update([
                            'status' => 'bounced',
                            'bounced_at' => $now,
                            'error_message' => $e->getMessage(),
                            'updated_at' => $now,
                        ]);
                        $bounced++;
                        Log::error('Campaign email send failed', ['recipient_id' => $row->id, 'error' => $e->getMessage()]);
                    }
                }
            });

        // Update campaign counters
        $campaign->increment('sent_count', $sent);
        $campaign->increment('delivered_count', $delivered);
        $campaign->increment('bounced_count', $bounced);
        $campaign->update(['status' => 'sent', 'sent_at' => $now]);

        Log::info('Campaign send end', ['campaign_id' => $campaign->id, 'sent' => $sent, 'delivered' => $delivered, 'bounced' => $bounced]);
    }
}


