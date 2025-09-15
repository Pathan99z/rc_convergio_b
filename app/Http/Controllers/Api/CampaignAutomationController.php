<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Campaign;
use App\Models\CampaignAutomation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class CampaignAutomationController extends Controller
{
    /**
     * Get all automation rules for a specific campaign.
     */
    public function index(Request $request, $campaignId): JsonResponse
    {
        $user = Auth::user();
        $tenantId = $user->tenant_id;

        // Verify campaign exists and belongs to tenant
        $campaign = Campaign::where('id', $campaignId)
            ->where('tenant_id', $tenantId)
            ->firstOrFail();

        $automations = CampaignAutomation::where('campaign_id', $campaignId)
            ->where('tenant_id', $tenantId)
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'data' => $automations,
            'message' => 'Campaign automations retrieved successfully'
        ]);
    }

    /**
     * Create a new automation rule for a campaign.
     */
    public function store(Request $request, $campaignId): JsonResponse
    {
        $user = Auth::user();
        $tenantId = $user->tenant_id;

        // Verify campaign exists and belongs to tenant
        $campaign = Campaign::where('id', $campaignId)
            ->where('tenant_id', $tenantId)
            ->firstOrFail();

        $validated = $request->validate([
            'trigger_event' => [
                'required',
                'string',
                Rule::in(array_keys(CampaignAutomation::getAvailableTriggerEvents()))
            ],
            'delay_minutes' => 'required|integer|min:0|max:10080', // Max 7 days
            'action' => [
                'required',
                'string',
                Rule::in(array_keys(CampaignAutomation::getAvailableActions()))
            ],
            'metadata' => 'nullable|array',
        ]);

        try {
            DB::beginTransaction();

            $automation = CampaignAutomation::create([
                'campaign_id' => $campaignId,
                'trigger_event' => $validated['trigger_event'],
                'delay_minutes' => $validated['delay_minutes'],
                'action' => $validated['action'],
                'metadata' => $validated['metadata'] ?? [],
                'tenant_id' => $tenantId,
            ]);

            DB::commit();

            return response()->json([
                'data' => $automation,
                'message' => 'Campaign automation created successfully'
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Failed to create campaign automation',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete a specific automation rule.
     */
    public function destroy($automationId): JsonResponse
    {
        $user = Auth::user();
        $tenantId = $user->tenant_id;

        $automation = CampaignAutomation::where('id', $automationId)
            ->where('tenant_id', $tenantId)
            ->firstOrFail();

        try {
            $automation->delete();

            return response()->json([
                'message' => 'Campaign automation deleted successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to delete campaign automation',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get available trigger events and actions for dropdowns.
     */
    public function options(): JsonResponse
    {
        return response()->json([
            'data' => [
                'trigger_events' => CampaignAutomation::getAvailableTriggerEvents(),
                'actions' => CampaignAutomation::getAvailableActions(),
            ],
            'message' => 'Automation options retrieved successfully'
        ]);
    }
}
