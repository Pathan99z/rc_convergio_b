<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;

class AuditLogController extends Controller
{
    /**
     * Get audit logs with optional filtering by campaign_id
     */
    public function index(Request $request): JsonResponse
    {
        // Validate request parameters
        $validator = Validator::make($request->all(), [
            'campaign_id' => 'nullable|integer|min:1',
            'page' => 'nullable|integer|min:1',
            'per_page' => 'nullable|integer|min:1|max:100',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $campaignId = $request->query('campaign_id');
        $perPage = $request->query('per_page', 50);

        // Build query
        $query = AuditLog::with(['user:id,name,email', 'campaign:id,name', 'contact:id,first_name,last_name,email'])
            ->orderBy('created_at', 'desc');

        // Filter by campaign_id if provided
        if ($campaignId) {
            $query->where('campaign_id', $campaignId);
        }

        // Get paginated results
        $logs = $query->paginate($perPage);

        // Transform the data
        $transformedData = $logs->map(function ($log) {
            return [
                'id' => $log->id,
                'action' => $log->action,
                'user' => $log->user ? [
                    'id' => $log->user->id,
                    'name' => $log->user->name,
                    'email' => $log->user->email,
                ] : null,
                'campaign_id' => $log->campaign_id,
                'campaign' => $log->campaign ? [
                    'id' => $log->campaign->id,
                    'name' => $log->campaign->name,
                ] : null,
                'contact_id' => $log->contact_id,
                'contact' => $log->contact ? [
                    'id' => $log->contact->id,
                    'name' => trim(($log->contact->first_name ?: '') . ' ' . ($log->contact->last_name ?: '')),
                    'email' => $log->contact->email,
                ] : null,
                'recipient_id' => $log->recipient_id,
                'metadata' => $log->metadata,
                'ip_address' => $log->ip_address,
                'user_agent' => $log->user_agent,
                'created_at' => $log->created_at->toISOString(),
                'updated_at' => $log->updated_at->toISOString(),
            ];
        });

        return response()->json([
            'data' => $transformedData,
            'meta' => [
                'campaign_id' => $campaignId,
                'current_page' => $logs->currentPage(),
                'per_page' => $logs->perPage(),
                'total' => $logs->total(),
                'last_page' => $logs->lastPage(),
                'from' => $logs->firstItem(),
                'to' => $logs->lastItem(),
            ],
            'links' => [
                'first' => $logs->url(1),
                'last' => $logs->url($logs->lastPage()),
                'prev' => $logs->previousPageUrl(),
                'next' => $logs->nextPageUrl(),
            ]
        ]);
    }
}
