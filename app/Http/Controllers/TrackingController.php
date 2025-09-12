<?php

namespace App\Http\Controllers;

use App\Models\CampaignRecipient;
use App\Models\AuditLog;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

class TrackingController extends Controller
{
    /**
     * Track email open event
     * Returns a 1x1 transparent GIF pixel
     */
    public function open(Request $request, int $recipientId): Response
    {
        try {
            $recipient = CampaignRecipient::findOrFail($recipientId);
            
            // Only record the first open
            if (!$recipient->opened_at) {
                $recipient->update([
                    'opened_at' => now(),
                    'status' => 'opened'
                ]);
                
                Log::info('Email opened', [
                    'recipient_id' => $recipientId,
                    'campaign_id' => $recipient->campaign_id,
                    'email' => $recipient->email,
                    'opened_at' => now()
                ]);

                // Log email open event
                AuditLog::log('recipient_opened', [
                    'recipient_id' => $recipientId,
                    'campaign_id' => $recipient->campaign_id,
                    'contact_id' => $recipient->contact_id,
                    'metadata' => [
                        'email' => $recipient->email,
                        'opened_at' => now()->toISOString(),
                        'ip_address' => $request->ip(),
                        'user_agent' => $request->userAgent()
                    ]
                ]);
            }
            
        } catch (\Exception $e) {
            Log::error('Failed to track email open', [
                'recipient_id' => $recipientId,
                'error' => $e->getMessage()
            ]);
        }
        
        // Return 1x1 transparent GIF
        return $this->getTransparentGif();
    }
    
    /**
     * Track email click event
     * Records the click and redirects to the original URL
     */
    public function click(Request $request, int $recipientId): \Illuminate\Http\RedirectResponse
    {
        try {
            $recipient = CampaignRecipient::findOrFail($recipientId);
            
            // Get the target URL from query parameter
            $targetUrl = $request->query('url');
            
            if (!$targetUrl) {
                Log::warning('Click tracking called without URL', [
                    'recipient_id' => $recipientId,
                    'campaign_id' => $recipient->campaign_id
                ]);
                
                // Redirect to a default page or return error
                return redirect()->away('https://example.com');
            }
            
            // Validate URL for security
            if (!filter_var($targetUrl, FILTER_VALIDATE_URL)) {
                Log::warning('Invalid URL in click tracking', [
                    'recipient_id' => $recipientId,
                    'url' => $targetUrl
                ]);
                
                return redirect()->away('https://example.com');
            }
            
            // Only record the first click
            if (!$recipient->clicked_at) {
                $recipient->update([
                    'clicked_at' => now(),
                    'status' => 'clicked'
                ]);
                
                Log::info('Email link clicked', [
                    'recipient_id' => $recipientId,
                    'campaign_id' => $recipient->campaign_id,
                    'email' => $recipient->email,
                    'target_url' => $targetUrl,
                    'clicked_at' => now()
                ]);

                // Log email click event
                AuditLog::log('recipient_clicked', [
                    'recipient_id' => $recipientId,
                    'campaign_id' => $recipient->campaign_id,
                    'contact_id' => $recipient->contact_id,
                    'metadata' => [
                        'email' => $recipient->email,
                        'target_url' => $targetUrl,
                        'clicked_at' => now()->toISOString(),
                        'ip_address' => $request->ip(),
                        'user_agent' => $request->userAgent()
                    ]
                ]);
            }
            
            // Redirect to the original URL
            return redirect()->away($targetUrl);
            
        } catch (\Exception $e) {
            Log::error('Failed to track email click', [
                'recipient_id' => $recipientId,
                'error' => $e->getMessage()
            ]);
            
            // Fallback redirect
            return redirect()->away('https://example.com');
        }
    }
    
    /**
     * Generate a 1x1 transparent GIF pixel
     */
    private function getTransparentGif(): Response
    {
        // 1x1 transparent GIF (43 bytes)
        $gif = base64_decode('R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7');
        
        return response($gif, 200, [
            'Content-Type' => 'image/gif',
            'Content-Length' => strlen($gif),
            'Cache-Control' => 'no-cache, no-store, must-revalidate',
            'Pragma' => 'no-cache',
            'Expires' => '0'
        ]);
    }
}
