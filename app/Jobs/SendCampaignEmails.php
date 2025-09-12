<?php

namespace App\Jobs;

use App\Models\Campaign;
use App\Models\AuditLog;
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

                    // Add tracking to the email content
                    $html = $this->addTrackingToEmail($html, $row->id);

                    try {
                        // Symfony Mailer: use html() to set HTML body with unsubscribe header
                        Mail::html($html, function ($m) use ($row, $subject, $campaign) {
                            $m->to($row->email, $row->name);
                            $m->subject($subject);
                            
                            // Add List-Unsubscribe header for Gmail compatibility
                            $baseUrl = config('app.url');
                            $unsubscribeUrl = $baseUrl . '/api/public/campaigns/unsubscribe/' . $row->id;
                            $m->getHeaders()->addTextHeader('List-Unsubscribe', '<' . $unsubscribeUrl . '>');
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

        // Log campaign completion event
        AuditLog::log('campaign_sent', [
            'campaign_id' => $campaign->id,
            'metadata' => [
                'sent_count' => $sent,
                'delivered_count' => $delivered,
                'bounced_count' => $bounced,
                'total_recipients' => $campaign->total_recipients,
                'sent_at' => $now->toISOString()
            ]
        ]);
    }

    /**
     * Add tracking pixel and click tracking to email content
     */
    private function addTrackingToEmail(string $html, int $recipientId): string
    {
        // Add tracking pixel for open tracking
        $trackingPixel = $this->getTrackingPixel($recipientId);
        
        // Add click tracking to all links
        $html = $this->addClickTracking($html, $recipientId);
        
        // Add unsubscribe footer
        $html = $this->addUnsubscribeFooter($html, $recipientId);
        
        // Insert tracking pixel before closing body tag, or at the end if no body tag
        if (stripos($html, '</body>') !== false) {
            $html = str_ireplace('</body>', $trackingPixel . '</body>', $html);
        } else {
            $html .= $trackingPixel;
        }
        
        return $html;
    }

    /**
     * Generate tracking pixel HTML
     */
    private function getTrackingPixel(int $recipientId): string
    {
        // Use the configured app URL instead of localhost for tracking
        $baseUrl = config('app.url');
        $trackingUrl = $baseUrl . '/track/open/' . $recipientId;
        
        return sprintf(
            '<img src="%s" width="1" height="1" style="display:none;width:1px;height:1px;border:0;" alt="" />',
            $trackingUrl
        );
    }

    /**
     * Add click tracking to all links in the email
     */
    private function addClickTracking(string $html, int $recipientId): string
    {
        // Pattern to match <a> tags with href attributes
        $pattern = '/<a\s+([^>]*?)href=["\']([^"\']+)["\']([^>]*?)>/i';
        
        return preg_replace_callback($pattern, function ($matches) use ($recipientId) {
            $beforeHref = $matches[1];
            $originalUrl = $matches[2];
            $afterHref = $matches[3];
            
            // Skip if it's already a tracking URL or mailto/tel links
            if (strpos($originalUrl, '/track/') !== false || 
                strpos($originalUrl, 'mailto:') === 0 || 
                strpos($originalUrl, 'tel:') === 0) {
                return $matches[0]; // Return original link unchanged
            }
            
            // Create tracking URL using configured app URL
            $baseUrl = config('app.url');
            $trackingUrl = $baseUrl . '/track/click/' . $recipientId . '?url=' . urlencode($originalUrl);
            
            // Rebuild the <a> tag with tracking URL
            return sprintf(
                '<a %shref="%s"%s>',
                $beforeHref,
                htmlspecialchars($trackingUrl, ENT_QUOTES, 'UTF-8'),
                $afterHref
            );
        }, $html);
    }

    /**
     * Add unsubscribe footer to email content
     */
    private function addUnsubscribeFooter(string $html, int $recipientId): string
    {
        $baseUrl = config('app.url');
        $unsubscribeUrl = $baseUrl . '/api/public/campaigns/unsubscribe/' . $recipientId;
        
        // Simple, clean unsubscribe footer as requested
        $footer = '<p style="margin-top: 20px; font-size: 12px; color: #666; text-align: center;">Don\'t want to receive these emails? <a href="' . htmlspecialchars($unsubscribeUrl, ENT_QUOTES, 'UTF-8') . '" style="color: #007bff; text-decoration: none;">Unsubscribe</a></p>';
        
        // Insert footer before closing body tag, or at the end if no body tag
        if (stripos($html, '</body>') !== false) {
            $html = str_ireplace('</body>', $footer . '</body>', $html);
        } else {
            $html .= $footer;
        }
        
        return $html;
    }
}


