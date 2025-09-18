<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Campaign;
use App\Models\CampaignTemplate;
use App\Models\Contact;
use App\Services\CampaignEnhancementService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Carbon\Carbon;

class CampaignEnhancementController extends Controller
{
    protected CampaignEnhancementService $enhancementService;

    public function __construct(CampaignEnhancementService $enhancementService)
    {
        $this->enhancementService = $enhancementService;
    }

    /**
     * Send test email for a campaign.
     */
    public function test(Request $request, int $id): JsonResponse
    {
        $user = Auth::user();
        $tenantId = $user->tenant_id;

        $validated = $request->validate([
            'test_emails' => 'required|array|min:1|max:5',
            'test_emails.*' => 'required|email',
        ]);

        try {
            $campaign = Campaign::forTenant($tenantId)->findOrFail($id);
            
            if ($campaign->status === 'archived') {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot send test email for archived campaign'
                ], 400);
            }

            $result = $this->enhancementService->sendTestEmail($campaign, $validated['test_emails']);

            return response()->json([
                'success' => true,
                'message' => 'Test email sent successfully',
                'data' => $result
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to send test email',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get campaign preview.
     */
    public function preview(Request $request, int $id): JsonResponse
    {
        $user = Auth::user();
        $tenantId = $user->tenant_id;

        try {
            $campaign = Campaign::forTenant($tenantId)->findOrFail($id);
            
            $preview = $this->enhancementService->generatePreview($campaign);

            return response()->json([
                'success' => true,
                'data' => $preview
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to generate preview',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Validate campaign.
     */
    public function validateCampaign(Request $request, int $id): JsonResponse
    {
        $user = Auth::user();
        $tenantId = $user->tenant_id;

        try {
            $campaign = Campaign::forTenant($tenantId)->findOrFail($id);
            
            $validation = $this->enhancementService->validateCampaign($campaign);

            return response()->json([
                'success' => true,
                'data' => $validation
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to validate campaign',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Schedule campaign.
     */
    public function schedule(Request $request, int $id): JsonResponse
    {
        $user = Auth::user();
        $tenantId = $user->tenant_id;

        $validated = $request->validate([
            'scheduled_at' => 'required|date|after:now',
        ]);

        try {
            $campaign = Campaign::forTenant($tenantId)->findOrFail($id);
            
            if (!in_array($campaign->status, ['draft', 'cancelled'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Only draft or cancelled campaigns can be scheduled'
                ], 400);
            }

            $result = $this->enhancementService->scheduleCampaign($campaign, $validated['scheduled_at']);

            return response()->json([
                'success' => true,
                'message' => 'Campaign scheduled successfully',
                'data' => $result
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to schedule campaign',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Unschedule campaign.
     */
    public function unschedule(Request $request, int $id): JsonResponse
    {
        $user = Auth::user();
        $tenantId = $user->tenant_id;

        try {
            $campaign = Campaign::forTenant($tenantId)->findOrFail($id);
            
            if ($campaign->status !== 'scheduled') {
                return response()->json([
                    'success' => false,
                    'message' => 'Only scheduled campaigns can be unscheduled'
                ], 400);
            }

            $result = $this->enhancementService->unscheduleCampaign($campaign);

            return response()->json([
                'success' => true,
                'message' => 'Campaign unscheduled successfully',
                'data' => $result
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to unschedule campaign',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Archive campaign.
     */
    public function archive(Request $request, int $id): JsonResponse
    {
        $user = Auth::user();
        $tenantId = $user->tenant_id;

        try {
            $campaign = Campaign::forTenant($tenantId)->findOrFail($id);
            
            if ($campaign->status === 'archived') {
                return response()->json([
                    'success' => false,
                    'message' => 'Campaign is already archived'
                ], 400);
            }

            $result = $this->enhancementService->archiveCampaign($campaign);

            return response()->json([
                'success' => true,
                'message' => 'Campaign archived successfully',
                'data' => $result
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to archive campaign',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Restore archived campaign.
     */
    public function restore(Request $request, int $id): JsonResponse
    {
        $user = Auth::user();
        $tenantId = $user->tenant_id;

        try {
            $campaign = Campaign::forTenant($tenantId)->findOrFail($id);
            
            if ($campaign->status !== 'archived') {
                return response()->json([
                    'success' => false,
                    'message' => 'Only archived campaigns can be restored'
                ], 400);
            }

            $result = $this->enhancementService->restoreCampaign($campaign);

            return response()->json([
                'success' => true,
                'message' => 'Campaign restored successfully',
                'data' => $result
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to restore campaign',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Create campaign template.
     */
    public function createTemplate(Request $request): JsonResponse
    {
        $user = Auth::user();
        $tenantId = optional($request->user())->tenant_id ?? $request->user()->id;

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'required|string',
            'subject' => 'required|string|max:255',
            'content' => 'required|string',
            'type' => ['required', Rule::in(['email', 'sms'])],
            'category' => ['nullable', Rule::in(array_keys(CampaignTemplate::getAvailableCategories()))],
            'settings' => 'nullable|array',
            'variables' => 'nullable|array',
            'is_public' => 'boolean',
        ]);

        try {
            // Log what we're receiving for debugging
            Log::info('Template creation via CampaignEnhancementController', [
                'validated_data' => $validated,
                'tenant_id' => $tenantId,
                'user_id' => $user->id
            ]);
            
            $template = CampaignTemplate::create([
                ...$validated,
                'tenant_id' => $tenantId,
                'created_by' => $user->id,
                'is_active' => true,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Campaign template created successfully',
                'data' => $template
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create campaign template',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update campaign template.
     */
    public function updateTemplate(Request $request, int $id): JsonResponse
    {
        $user = Auth::user();
        $tenantId = optional($request->user())->tenant_id ?? $request->user()->id;

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'description' => 'sometimes|required|string',
            'subject' => 'sometimes|string|max:255',
            'content' => 'sometimes|string',
            'type' => ['sometimes', Rule::in(['email', 'sms'])],
            'category' => ['nullable', Rule::in(array_keys(CampaignTemplate::getAvailableCategories()))],
            'settings' => 'nullable|array',
            'variables' => 'nullable|array',
            'is_active' => 'boolean',
            'is_public' => 'boolean',
        ]);

        try {
            $template = CampaignTemplate::forTenant($tenantId)->findOrFail($id);
            $template->update($validated);

            return response()->json([
                'success' => true,
                'message' => 'Campaign template updated successfully',
                'data' => $template
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update campaign template',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete campaign template.
     */
    public function deleteTemplate(Request $request, int $id): JsonResponse
    {
        $user = Auth::user();
        $tenantId = optional($request->user())->tenant_id ?? $request->user()->id;

        try {
            $template = CampaignTemplate::forTenant($tenantId)->findOrFail($id);
            $template->delete();

            return response()->json([
                'success' => true,
                'message' => 'Campaign template deleted successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete campaign template',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Bulk send campaigns.
     */
    public function bulkSend(Request $request): JsonResponse
    {
        $user = Auth::user();
        $tenantId = $user->tenant_id;

        $validated = $request->validate([
            'campaign_ids' => 'required|array|min:1|max:10',
            'campaign_ids.*' => 'integer|exists:campaigns,id',
        ]);

        try {
            $result = $this->enhancementService->bulkSendCampaigns($tenantId, $validated['campaign_ids']);

            return response()->json([
                'success' => true,
                'message' => 'Bulk send operation completed',
                'data' => $result
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to bulk send campaigns',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Bulk pause campaigns.
     */
    public function bulkPause(Request $request): JsonResponse
    {
        $user = Auth::user();
        $tenantId = $user->tenant_id;

        $validated = $request->validate([
            'campaign_ids' => 'required|array|min:1|max:10',
            'campaign_ids.*' => 'integer|exists:campaigns,id',
        ]);

        try {
            $result = $this->enhancementService->bulkPauseCampaigns($tenantId, $validated['campaign_ids']);

            return response()->json([
                'success' => true,
                'message' => 'Bulk pause operation completed',
                'data' => $result
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to bulk pause campaigns',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Bulk resume campaigns.
     */
    public function bulkResume(Request $request): JsonResponse
    {
        $user = Auth::user();
        $tenantId = $user->tenant_id;

        $validated = $request->validate([
            'campaign_ids' => 'required|array|min:1|max:10',
            'campaign_ids.*' => 'integer|exists:campaigns,id',
        ]);

        try {
            $result = $this->enhancementService->bulkResumeCampaigns($tenantId, $validated['campaign_ids']);

            return response()->json([
                'success' => true,
                'message' => 'Bulk resume operation completed',
                'data' => $result
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to bulk resume campaigns',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Bulk archive campaigns.
     */
    public function bulkArchive(Request $request): JsonResponse
    {
        $user = Auth::user();
        $tenantId = $user->tenant_id;

        $validated = $request->validate([
            'campaign_ids' => 'required|array|min:1|max:10',
            'campaign_ids.*' => 'integer|exists:campaigns,id',
        ]);

        try {
            $result = $this->enhancementService->bulkArchiveCampaigns($tenantId, $validated['campaign_ids']);

            return response()->json([
                'success' => true,
                'message' => 'Bulk archive operation completed',
                'data' => $result
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to bulk archive campaigns',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Export campaigns.
     */
    public function export(Request $request): JsonResponse
    {
        $user = Auth::user();
        $tenantId = $user->tenant_id;

        $validated = $request->validate([
            'format' => ['sometimes', Rule::in(['csv', 'excel', 'json'])],
            'status' => ['sometimes', Rule::in(['draft', 'scheduled', 'sending', 'sent', 'cancelled', 'archived'])],
            'type' => ['sometimes', Rule::in(['email', 'sms'])],
            'date_from' => 'sometimes|date',
            'date_to' => 'sometimes|date|after_or_equal:date_from',
        ]);

        try {
            $result = $this->enhancementService->exportCampaigns($tenantId, $validated);

            return response()->json([
                'success' => true,
                'message' => 'Campaigns exported successfully',
                'data' => $result
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to export campaigns',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Import campaigns.
     */
    public function import(Request $request): JsonResponse
    {
        $user = Auth::user();
        $tenantId = $user->tenant_id;

        $validated = $request->validate([
            'file' => 'required|file|mimes:csv,xlsx,xls|max:10240', // 10MB max
            'template_id' => 'nullable|integer|exists:campaign_templates,id',
        ]);

        try {
            $result = $this->enhancementService->importCampaigns($tenantId, $validated['file'], $validated['template_id'] ?? null, $user->id);

            return response()->json([
                'success' => true,
                'message' => 'Campaigns imported successfully',
                'data' => $result
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to import campaigns',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
