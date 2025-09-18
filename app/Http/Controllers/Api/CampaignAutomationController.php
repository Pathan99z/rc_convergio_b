<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Campaign;
use App\Models\CampaignAutomation;
use App\Models\CampaignAutomationLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class CampaignAutomationController extends Controller
{
    /**
     * Get all automation rules for a specific campaign or all automations.
     */
    public function index(Request $request, $campaignId = null): JsonResponse
    {
        // Get tenant_id from header or use user's organization as fallback (same as CampaignsController)
        $tenantId = optional($request->user())->tenant_id ?? $request->user()->id;
        if ($tenantId === 0) {
            // Use organization_name to determine tenant_id
            $user = $request->user();
            if ($user->organization_name === 'Globex LLC') {
                $tenantId = 4; // chitti's organization
            } else {
                $tenantId = 1; // default tenant
            }
        }

        $query = CampaignAutomation::where('tenant_id', $tenantId);

        // If campaignId is provided, filter by it and verify campaign exists
        if ($campaignId !== null) {
            // Verify campaign exists and belongs to tenant
            $campaign = Campaign::where('id', $campaignId)
                ->where('tenant_id', $tenantId)
                ->firstOrFail();
            
            $query->where('campaign_id', $campaignId);
        }

        $automations = $query->orderBy('created_at', 'desc')->get();

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
        // Get tenant_id from header or use user's organization as fallback (same as CampaignsController)
        $tenantId = optional($request->user())->tenant_id ?? $request->user()->id;
        if ($tenantId === 0) {
            // Use organization_name to determine tenant_id
            $user = $request->user();
            if ($user->organization_name === 'Globex LLC') {
                $tenantId = 4; // chitti's organization
            } else {
                $tenantId = 1; // default tenant
            }
        }

        // Verify campaign exists and belongs to tenant
        $campaign = Campaign::where('id', $campaignId)
            ->where('tenant_id', $tenantId)
            ->firstOrFail();

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'trigger_event' => [
                'required',
                'string',
                Rule::in(array_keys(CampaignAutomation::getAvailableTriggerEvents()))
            ],
            'campaign_id' => 'nullable|exists:campaigns,id',
            'template_id' => 'required|exists:campaign_templates,id',
            'content_type' => 'required|in:template,campaign',
            'delay_minutes' => 'required|integer|min:0|max:10080', // Max 7 days
            'action' => [
                'required',
                'string',
                Rule::in(array_keys(CampaignAutomation::getAvailableActions()))
            ],
            'metadata' => 'nullable|array',
            'is_active' => 'boolean',
        ]);

        // Professional validation: campaign_id required for email_opened and link_clicked
        if (in_array($validated['trigger_event'], ['email_opened', 'link_clicked']) && !$validated['campaign_id']) {
            return response()->json([
                'message' => 'Campaign selection is required for email_opened and link_clicked triggers',
                'error' => true
            ], 422);
        }

        try {
            DB::beginTransaction();

            // Professional logic: Only use campaign_id for triggers that require it
            $finalCampaignId = null;
            if (in_array($validated['trigger_event'], ['email_opened', 'link_clicked'])) {
                // For email_opened and link_clicked, campaign_id is required
                $finalCampaignId = $validated['campaign_id'] ?? $campaignId;
            } else {
                // For contact_created and form_submitted, campaign_id should be null if not explicitly provided
                $finalCampaignId = $validated['campaign_id'] ?? null;
            }

            $automation = CampaignAutomation::create([
                'name' => $validated['name'],
                'description' => $validated['description'] ?? null,
                'campaign_id' => $finalCampaignId,
                'template_id' => $validated['template_id'],
                'content_type' => $validated['content_type'],
                'trigger_event' => $validated['trigger_event'],
                'delay_minutes' => $validated['delay_minutes'],
                'action' => $validated['action'],
                'metadata' => $validated['metadata'] ?? [],
                'is_active' => $validated['is_active'] ?? true,
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
        // Get tenant_id from header or use user's organization as fallback (same as CampaignsController)
        $tenantId = optional(request()->user())->tenant_id ?? request()->user()->id;
        if ($tenantId === 0) {
            // Use organization_name to determine tenant_id
            $user = request()->user();
            if ($user->organization_name === 'Globex LLC') {
                $tenantId = 4; // chitti's organization
            } else {
                $tenantId = 1; // default tenant
            }
        }

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
     * Update a specific automation rule.
     */
    public function update(Request $request, $id): JsonResponse
    {
        // Get tenant_id from header or use user's organization as fallback (same as CampaignsController)
        $tenantId = optional($request->user())->tenant_id ?? $request->user()->id;
        if ($tenantId === 0) {
            // Use organization_name to determine tenant_id
            $user = $request->user();
            if ($user->organization_name === 'Globex LLC') {
                $tenantId = 4; // chitti's organization
            } else {
                $tenantId = 1; // default tenant
            }
        }

        $automation = CampaignAutomation::where('id', $id)
            ->where('tenant_id', $tenantId)
            ->firstOrFail();

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'description' => 'sometimes|nullable|string',
            'trigger_event' => [
                'sometimes',
                'string',
                Rule::in(array_keys(CampaignAutomation::getAvailableTriggerEvents()))
            ],
            'campaign_id' => 'sometimes|nullable|exists:campaigns,id',
            'template_id' => 'sometimes|exists:campaign_templates,id',
            'content_type' => 'sometimes|in:template,campaign',
            'delay_minutes' => 'sometimes|integer|min:0|max:10080', // Max 7 days
            'action' => [
                'sometimes',
                'string',
                Rule::in(array_keys(CampaignAutomation::getAvailableActions()))
            ],
            'metadata' => 'sometimes|array',
            'is_active' => 'sometimes|boolean',
        ]);

        // Professional validation: campaign_id required for email_opened and link_clicked
        if (isset($validated['trigger_event']) && in_array($validated['trigger_event'], ['email_opened', 'link_clicked'])) {
            $campaignId = $validated['campaign_id'] ?? $automation->campaign_id;
            if (!$campaignId) {
                return response()->json([
                    'message' => 'Campaign selection is required for email_opened and link_clicked triggers',
                    'error' => true
                ], 422);
            }
        }

        try {
            DB::beginTransaction();

            $automation->update($validated);

            DB::commit();

            return response()->json([
                'data' => $automation,
                'message' => 'Automation updated successfully'
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Failed to update automation',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Toggle automation status (active/inactive).
     */
    public function updateStatus(Request $request, $id): JsonResponse
    {
        // Get tenant_id from header or use user's organization as fallback (same as CampaignsController)
        $tenantId = optional($request->user())->tenant_id ?? $request->user()->id;
        if ($tenantId === 0) {
            // Use organization_name to determine tenant_id
            $user = $request->user();
            if ($user->organization_name === 'Globex LLC') {
                $tenantId = 4; // chitti's organization
            } else {
                $tenantId = 1; // default tenant
            }
        }

        $automation = CampaignAutomation::where('id', $id)
            ->where('tenant_id', $tenantId)
            ->firstOrFail();

        $validated = $request->validate([
            'is_active' => 'required|boolean',
        ]);

        try {
            DB::beginTransaction();

            $automation->update(['is_active' => $validated['is_active']]);

            DB::commit();

            return response()->json([
                'data' => $automation,
                'message' => 'Automation status updated successfully'
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Failed to update automation status',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get automation execution logs.
     */
    public function logs(Request $request, $id): JsonResponse
    {
        // Get tenant_id from header or use user's organization as fallback (same as CampaignsController)
        $tenantId = optional($request->user())->tenant_id ?? $request->user()->id;
        if ($tenantId === 0) {
            // Use organization_name to determine tenant_id
            $user = $request->user();
            if ($user->organization_name === 'Globex LLC') {
                $tenantId = 4; // chitti's organization
            } else {
                $tenantId = 1; // default tenant
            }
        }

        // Verify automation exists and belongs to tenant
        $automation = CampaignAutomation::where('id', $id)
            ->where('tenant_id', $tenantId)
            ->firstOrFail();

        $query = CampaignAutomationLog::where('automation_id', $id)
            ->where('tenant_id', $tenantId)
            ->with(['contact:id,email,first_name,last_name']);

        // Filter by status if provided
        if ($request->has('status')) {
            $query->where('status', $request->get('status'));
        }

        // Filter by date range if provided
        if ($request->has('from_date')) {
            $query->where('created_at', '>=', $request->get('from_date'));
        }

        if ($request->has('to_date')) {
            $query->where('created_at', '<=', $request->get('to_date'));
        }

        $logs = $query->orderBy('created_at', 'desc')
            ->paginate($request->get('per_page', 15));

        return response()->json([
            'data' => $logs->items(),
            'meta' => [
                'current_page' => $logs->currentPage(),
                'last_page' => $logs->lastPage(),
                'per_page' => $logs->perPage(),
                'total' => $logs->total(),
                'from' => $logs->firstItem(),
                'to' => $logs->lastItem(),
            ],
            'message' => 'Automation logs retrieved successfully'
        ]);
    }

    /**
     * Get available trigger events and actions for dropdowns.
     */
    public function options(Request $request): JsonResponse
    {
        // Get tenant_id from header or use user's organization as fallback (same as CampaignsController)
        $tenantId = optional($request->user())->tenant_id ?? $request->user()->id;
        if ($tenantId === 0) {
            // Use organization_name to determine tenant_id
            $user = $request->user();
            if ($user->organization_name === 'Globex LLC') {
                $tenantId = 4; // chitti's organization
            } else {
                $tenantId = 1; // default tenant
            }
        }

        return response()->json([
            'data' => [
                'trigger_events' => CampaignAutomation::getAvailableTriggerEvents(),
                'actions' => CampaignAutomation::getAvailableActions(),
                'templates' => \App\Models\CampaignTemplate::where('tenant_id', $tenantId)->where('is_active', true)->get(['id', 'name', 'subject']),
                'campaigns' => Campaign::where('tenant_id', $tenantId)->get(['id', 'name', 'subject']),
            ],
            'message' => 'Automation options retrieved successfully'
        ]);
    }
}