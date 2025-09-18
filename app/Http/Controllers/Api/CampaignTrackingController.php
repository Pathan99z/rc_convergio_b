<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CampaignRecipient;
use App\Services\AutomationEngine;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CampaignTrackingController extends Controller
{
    protected AutomationEngine $automationEngine;

    public function __construct(AutomationEngine $automationEngine)
    {
        $this->automationEngine = $automationEngine;
    }
    /**
     * Track email open event
     * Public endpoint - no authentication required
     */
    public function open(Request $request): JsonResponse
    {
        $recipientId = $request->query('recipient_id');
        
        if (!$recipientId) {
            return response()->json([
                'success' => false,
                'message' => 'recipient_id parameter is required'
            ], 400);
        }

        try {
            DB::beginTransaction();
            
            $recipient = CampaignRecipient::find($recipientId);
            
            if (!$recipient) {
                return response()->json([
                    'success' => false,
                    'message' => 'Recipient not found'
                ], 404);
            }

            // Update opened_at and status if not already opened
            if (!$recipient->opened_at) {
                $recipient->update([
                    'opened_at' => now(),
                    'status' => 'opened'
                ]);
                
                // Increment campaign opened count
                DB::table('campaigns')
                    ->where('id', $recipient->campaign_id)
                    ->increment('opened_count');
                
                Log::info('Email open tracked via API', [
                    'recipient_id' => $recipientId,
                    'campaign_id' => $recipient->campaign_id,
                    'email' => $recipient->email,
                    'opened_at' => now()
                ]);
            }
            
            DB::commit();
            
            // Trigger automation for email opened event
            $this->automationEngine->processEmailEvent('opened', $recipientId);
            
            return response()->json([
                'success' => true,
                'message' => 'Open event recorded',
                'recipient_id' => (int) $recipientId
            ]);
            
        } catch (\Exception $e) {
            DB::rollBack();
            
            Log::error('Failed to track email open via API', [
                'recipient_id' => $recipientId,
                'error' => $e->getMessage()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to record open event'
            ], 500);
        }
    }

    /**
     * Track email click event
     * Public endpoint - no authentication required
     */
    public function click(Request $request): JsonResponse
    {
        $recipientId = $request->query('recipient_id');
        $url = $request->query('url');
        
        if (!$recipientId) {
            return response()->json([
                'success' => false,
                'message' => 'recipient_id parameter is required'
            ], 400);
        }

        try {
            DB::beginTransaction();
            
            $recipient = CampaignRecipient::find($recipientId);
            
            if (!$recipient) {
                return response()->json([
                    'success' => false,
                    'message' => 'Recipient not found'
                ], 404);
            }

            // Update clicked_at and status if not already clicked
            if (!$recipient->clicked_at) {
                $updateData = [
                    'clicked_at' => now(),
                    'status' => 'clicked'
                ];
                
                // Store URL in metadata if provided
                if ($url) {
                    $metadata = $recipient->metadata ?? [];
                    $metadata['clicked_url'] = $url;
                    $updateData['metadata'] = $metadata;
                }
                
                $recipient->update($updateData);
                
                // Increment campaign clicked count
                DB::table('campaigns')
                    ->where('id', $recipient->campaign_id)
                    ->increment('clicked_count');
                
                Log::info('Email click tracked via API', [
                    'recipient_id' => $recipientId,
                    'campaign_id' => $recipient->campaign_id,
                    'email' => $recipient->email,
                    'clicked_url' => $url,
                    'clicked_at' => now()
                ]);
            }
            
            DB::commit();
            
            // Trigger automation for link clicked event
            $this->automationEngine->processEmailEvent('clicked', $recipientId);
            
            return response()->json([
                'success' => true,
                'message' => 'Click event recorded',
                'recipient_id' => (int) $recipientId,
                'url' => $url
            ]);
            
        } catch (\Exception $e) {
            DB::rollBack();
            
            Log::error('Failed to track email click via API', [
                'recipient_id' => $recipientId,
                'url' => $url,
                'error' => $e->getMessage()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to record click event'
            ], 500);
        }
    }

    /**
     * Track email bounce event
     * Public endpoint - no authentication required
     */
    public function bounce(Request $request): JsonResponse
    {
        $recipientId = $request->query('recipient_id');
        $errorReason = $request->query('error_reason', 'Unknown bounce reason');
        
        if (!$recipientId) {
            return response()->json([
                'success' => false,
                'message' => 'recipient_id parameter is required'
            ], 400);
        }

        try {
            DB::beginTransaction();
            
            $recipient = CampaignRecipient::find($recipientId);
            
            if (!$recipient) {
                return response()->json([
                    'success' => false,
                    'message' => 'Recipient not found'
                ], 404);
            }

            // Update bounced_at and status if not already bounced
            if (!$recipient->bounced_at) {
                $updateData = [
                    'bounced_at' => now(),
                    'status' => 'bounced'
                ];
                
                // Store error reason in metadata
                $metadata = $recipient->metadata ?? [];
                $metadata['bounce_reason'] = $errorReason;
                $updateData['metadata'] = $metadata;
                
                $recipient->update($updateData);
                
                // Increment campaign bounced count
                DB::table('campaigns')
                    ->where('id', $recipient->campaign_id)
                    ->increment('bounced_count');
                
                Log::info('Email bounce tracked via API', [
                    'recipient_id' => $recipientId,
                    'campaign_id' => $recipient->campaign_id,
                    'email' => $recipient->email,
                    'bounce_reason' => $errorReason,
                    'bounced_at' => now()
                ]);
            }
            
            DB::commit();
            
            return response()->json([
                'success' => true,
                'message' => 'Bounce event recorded',
                'recipient_id' => (int) $recipientId,
                'error_reason' => $errorReason
            ]);
            
        } catch (\Exception $e) {
            DB::rollBack();
            
            Log::error('Failed to track email bounce via API', [
                'recipient_id' => $recipientId,
                'error_reason' => $errorReason,
                'error' => $e->getMessage()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to record bounce event'
            ], 500);
        }
    }

    /**
     * Get all recipients who opened emails for a specific campaign
     * Public endpoint - no authentication required
     */
    public function getOpensByCampaign(Request $request, int $campaignId): JsonResponse
    {
        try {
            $perPage = min((int) $request->query('per_page', 50), 100);
            
            $recipients = CampaignRecipient::where('campaign_id', $campaignId)
                ->whereNotNull('opened_at')
                ->with(['contact:id,email,name'])
                ->select([
                    'id as recipient_id',
                    'email',
                    'name',
                    'opened_at',
                    'metadata'
                ])
                ->orderBy('opened_at', 'desc')
                ->paginate($perPage);

            return response()->json([
                'success' => true,
                'campaign_id' => $campaignId,
                'data' => $recipients->items(),
                'meta' => [
                    'current_page' => $recipients->currentPage(),
                    'last_page' => $recipients->lastPage(),
                    'per_page' => $recipients->perPage(),
                    'total' => $recipients->total(),
                    'from' => $recipients->firstItem(),
                    'to' => $recipients->lastItem(),
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to get campaign opens', [
                'campaign_id' => $campaignId,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve campaign opens'
            ], 500);
        }
    }

    /**
     * Get all recipients who clicked links for a specific campaign
     * Public endpoint - no authentication required
     */
    public function getClicksByCampaign(Request $request, int $campaignId): JsonResponse
    {
        try {
            $perPage = min((int) $request->query('per_page', 50), 100);
            
            $recipients = CampaignRecipient::where('campaign_id', $campaignId)
                ->whereNotNull('clicked_at')
                ->with(['contact:id,email,name'])
                ->select([
                    'id as recipient_id',
                    'email',
                    'name',
                    'clicked_at',
                    'metadata'
                ])
                ->orderBy('clicked_at', 'desc')
                ->paginate($perPage);

            // Extract clicked URL from metadata for each recipient
            $data = collect($recipients->items())->map(function ($recipient) {
                $metadata = $recipient->metadata ?? [];
                $recipient->clicked_url = $metadata['clicked_url'] ?? null;
                return $recipient;
            });

            return response()->json([
                'success' => true,
                'campaign_id' => $campaignId,
                'data' => $data,
                'meta' => [
                    'current_page' => $recipients->currentPage(),
                    'last_page' => $recipients->lastPage(),
                    'per_page' => $recipients->perPage(),
                    'total' => $recipients->total(),
                    'from' => $recipients->firstItem(),
                    'to' => $recipients->lastItem(),
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to get campaign clicks', [
                'campaign_id' => $campaignId,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve campaign clicks'
            ], 500);
        }
    }

    /**
     * Get all recipients who bounced for a specific campaign
     * Public endpoint - no authentication required
     */
    public function getBouncesByCampaign(Request $request, int $campaignId): JsonResponse
    {
        try {
            $perPage = min((int) $request->query('per_page', 50), 100);
            
            $recipients = CampaignRecipient::where('campaign_id', $campaignId)
                ->whereNotNull('bounced_at')
                ->with(['contact:id,email,name'])
                ->select([
                    'id as recipient_id',
                    'email',
                    'name',
                    'bounced_at',
                    'metadata'
                ])
                ->orderBy('bounced_at', 'desc')
                ->paginate($perPage);

            // Extract bounce reason from metadata for each recipient
            $data = collect($recipients->items())->map(function ($recipient) {
                $metadata = $recipient->metadata ?? [];
                $recipient->bounce_reason = $metadata['bounce_reason'] ?? 'Unknown';
                return $recipient;
            });

            return response()->json([
                'success' => true,
                'campaign_id' => $campaignId,
                'data' => $data,
                'meta' => [
                    'current_page' => $recipients->currentPage(),
                    'last_page' => $recipients->lastPage(),
                    'per_page' => $recipients->perPage(),
                    'total' => $recipients->total(),
                    'from' => $recipients->firstItem(),
                    'to' => $recipients->lastItem(),
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to get campaign bounces', [
                'campaign_id' => $campaignId,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve campaign bounces'
            ], 500);
        }
    }
}


