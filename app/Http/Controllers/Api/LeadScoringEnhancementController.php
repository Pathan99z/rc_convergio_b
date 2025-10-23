<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\LeadScoringRule;
use App\Models\Contact;
use App\Services\LeadScoringEnhancementService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class LeadScoringEnhancementController extends Controller
{
    protected LeadScoringEnhancementService $enhancementService;

    public function __construct(LeadScoringEnhancementService $enhancementService)
    {
        $this->enhancementService = $enhancementService;
    }

    /**
     * Bulk recalculate lead scores.
     */
    public function bulkRecalculate(Request $request): JsonResponse
    {
        $user = Auth::user();
        $tenantId = $user->tenant_id;

        $validated = $request->validate([
            'contact_ids' => 'sometimes|array|max:50',
            'contact_ids.*' => 'integer|exists:contacts,id',
            'rule_ids' => 'sometimes|array|max:20',
            'rule_ids.*' => 'integer|exists:lead_scoring_rules,id',
        ]);

        try {
            $result = $this->enhancementService->bulkRecalculateScores(
                $tenantId, 
                $validated['contact_ids'] ?? null,
                $validated['rule_ids'] ?? null
            );

            return response()->json([
                'success' => true,
                'message' => 'Bulk recalculate operation completed',
                'data' => $result
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to bulk recalculate scores',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Bulk activate lead scoring rules.
     */
    public function bulkActivate(Request $request): JsonResponse
    {
        $user = Auth::user();
        $tenantId = $user->tenant_id;

        $validated = $request->validate([
            'rule_ids' => 'required|array|min:1|max:20',
            'rule_ids.*' => 'integer|exists:lead_scoring_rules,id',
        ]);

        try {
            $result = $this->enhancementService->bulkActivateRules($tenantId, $validated['rule_ids']);

            return response()->json([
                'success' => true,
                'message' => 'Bulk activate operation completed',
                'data' => $result
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to bulk activate rules',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Bulk deactivate lead scoring rules.
     */
    public function bulkDeactivate(Request $request): JsonResponse
    {
        $user = Auth::user();
        $tenantId = $user->tenant_id;

        $validated = $request->validate([
            'rule_ids' => 'required|array|min:1|max:20',
            'rule_ids.*' => 'integer|exists:lead_scoring_rules,id',
        ]);

        try {
            $result = $this->enhancementService->bulkDeactivateRules($tenantId, $validated['rule_ids']);

            return response()->json([
                'success' => true,
                'message' => 'Bulk deactivate operation completed',
                'data' => $result
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to bulk deactivate rules',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Export lead scoring rules.
     */
    public function export(Request $request): JsonResponse
    {
        $user = Auth::user();
        $tenantId = $user->tenant_id;

        $validated = $request->validate([
            'format' => ['sometimes', Rule::in(['csv', 'excel', 'json'])],
            'is_active' => 'sometimes|boolean',
            'date_from' => 'sometimes|date',
            'date_to' => 'sometimes|date|after_or_equal:date_from',
        ]);

        try {
            $result = $this->enhancementService->exportRules($tenantId, $validated);

            return response()->json([
                'success' => true,
                'message' => 'Lead scoring rules exported successfully',
                'data' => $result
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to export lead scoring rules',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Import lead scoring rules.
     */
    public function import(Request $request): JsonResponse
    {
        $user = Auth::user();
        $tenantId = $user->tenant_id;

        $validated = $request->validate([
            'file' => 'required|file|mimes:csv,xlsx,xls,json|max:10240', // 10MB max
        ]);

        try {
            $result = $this->enhancementService->importRules($tenantId, $validated['file'], $user->id);

            return response()->json([
                'success' => true,
                'message' => 'Lead scoring rules imported successfully',
                'data' => $result
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to import lead scoring rules',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Export contacts with lead scores.
     */
    public function exportContacts(Request $request)
    {
        $user = Auth::user();
        $tenantId = $user->tenant_id;

        $validated = $request->validate([
            'format' => ['sometimes', Rule::in(['csv', 'excel', 'json'])],
            'min_score' => 'sometimes|integer|min:0',
            'max_score' => 'sometimes|integer|min:0',
            'date_from' => 'sometimes|date',
            'date_to' => 'sometimes|date|after_or_equal:date_from',
        ]);

        try {
            $result = $this->enhancementService->exportContactsWithScores($tenantId, $validated);

            // If CSV format, return the file directly
            if (($validated['format'] ?? 'csv') === 'csv') {
                $filePath = storage_path('app/public/' . $result['file_path']);
                
                if (file_exists($filePath)) {
                    return response()->download($filePath, $result['filename'], [
                        'Content-Type' => 'text/csv',
                        'Content-Disposition' => 'attachment; filename="' . $result['filename'] . '"'
                    ]);
                }
            }

            // For JSON format, return JSON response
            return response()->json([
                'success' => true,
                'message' => 'Contacts with lead scores exported successfully',
                'data' => $result
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to export contacts with scores',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}

