<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\LeadScoringRule;
use App\Models\Contact;
use App\Services\LeadScoringService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class LeadScoringController extends Controller
{
    protected LeadScoringService $leadScoringService;

    public function __construct(LeadScoringService $leadScoringService)
    {
        $this->leadScoringService = $leadScoringService;
    }

    /**
     * Get all lead scoring rules for the authenticated user's tenant.
     */
    public function getRules(Request $request): JsonResponse
    {
        $user = Auth::user();
        
        if (!$user) {
            return response()->json([
                'message' => 'Unauthenticated',
                'error' => 'User not authenticated'
            ], 401);
        }
        
        $tenantId = $user->tenant_id;

        $query = LeadScoringRule::where('tenant_id', $tenantId);

        // Filter by active status if provided
        if ($request->has('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        $rules = $query->orderBy('priority', 'desc')
            ->orderBy('created_at', 'desc')
            ->paginate($request->get('per_page', 15));

        // Add condition descriptions to each rule
        $rules->getCollection()->transform(function ($rule) {
            $rule->condition_description = $rule->getConditionDescription();
            return $rule;
        });

        return response()->json([
            'data' => $rules->items(),
            'meta' => [
                'current_page' => $rules->currentPage(),
                'last_page' => $rules->lastPage(),
                'per_page' => $rules->perPage(),
                'total' => $rules->total(),
            ],
            'message' => 'Lead scoring rules retrieved successfully'
        ]);
    }

    /**
     * Create a new lead scoring rule.
     */
    public function createRule(Request $request): JsonResponse
    {
        $user = Auth::user();
        
        if (!$user) {
            return response()->json([
                'message' => 'Unauthenticated',
                'error' => 'User not authenticated'
            ], 401);
        }
        
        $tenantId = $user->tenant_id;

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'condition' => 'required|array',
            'condition.event' => [
                'required',
                'string',
                Rule::in(array_keys(LeadScoringRule::getAvailableEventTypes()))
            ],
            'condition.criteria' => 'nullable|array',
            'condition.criteria.*.field' => 'required_with:condition.criteria|string',
            'condition.criteria.*.operator' => [
                'required_with:condition.criteria',
                'string',
                Rule::in(array_keys(LeadScoringRule::getAvailableOperators()))
            ],
            'condition.criteria.*.value' => 'nullable',
            'points' => 'required|integer|min:0|max:1000',
            'priority' => 'nullable|integer|min:0|max:100',
            'metadata' => 'nullable|array',
        ]);

        try {
            DB::beginTransaction();

            $rule = LeadScoringRule::create([
                'name' => $validated['name'],
                'description' => $validated['description'],
                'condition' => $validated['condition'],
                'points' => $validated['points'],
                'priority' => $validated['priority'] ?? 0,
                'metadata' => $validated['metadata'] ?? [],
                'tenant_id' => $tenantId,
                'created_by' => $user->id,
            ]);

            // Validate the rule condition
            if (!$rule->validateCondition()) {
                DB::rollBack();
                return response()->json([
                    'message' => 'Invalid rule condition structure',
                    'errors' => ['condition' => ['The rule condition is invalid']]
                ], 422);
            }

            DB::commit();

            $rule->condition_description = $rule->getConditionDescription();

            return response()->json([
                'data' => $rule,
                'message' => 'Lead scoring rule created successfully'
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Failed to create lead scoring rule',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update an existing lead scoring rule.
     */
    public function updateRule(Request $request, $id): JsonResponse
    {
        $user = Auth::user();
        $tenantId = $user->tenant_id;

        $rule = LeadScoringRule::where('id', $id)
            ->where('tenant_id', $tenantId)
            ->firstOrFail();

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'description' => 'nullable|string',
            'condition' => 'sometimes|array',
            'condition.event' => [
                'sometimes',
                'string',
                Rule::in(array_keys(LeadScoringRule::getAvailableEventTypes()))
            ],
            'condition.criteria' => 'nullable|array',
            'condition.criteria.*.field' => 'required_with:condition.criteria|string',
            'condition.criteria.*.operator' => [
                'required_with:condition.criteria',
                'string',
                Rule::in(array_keys(LeadScoringRule::getAvailableOperators()))
            ],
            'condition.criteria.*.value' => 'nullable',
            'points' => 'sometimes|integer|min:0|max:1000',
            'priority' => 'nullable|integer|min:0|max:100',
            'is_active' => 'sometimes|boolean',
            'metadata' => 'nullable|array',
        ]);

        try {
            DB::beginTransaction();

            $rule->update($validated);

            // Validate the rule condition if it was updated
            if (isset($validated['condition']) && !$rule->validateCondition()) {
                DB::rollBack();
                return response()->json([
                    'message' => 'Invalid rule condition structure',
                    'errors' => ['condition' => ['The rule condition is invalid']]
                ], 422);
            }

            DB::commit();

            $rule->condition_description = $rule->getConditionDescription();

            return response()->json([
                'data' => $rule,
                'message' => 'Lead scoring rule updated successfully'
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Failed to update lead scoring rule',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete a lead scoring rule.
     */
    public function deleteRule($id): JsonResponse
    {
        $user = Auth::user();
        $tenantId = $user->tenant_id;

        $rule = LeadScoringRule::where('id', $id)
            ->where('tenant_id', $tenantId)
            ->firstOrFail();

        try {
            $rule->delete();

            return response()->json([
                'message' => 'Lead scoring rule deleted successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to delete lead scoring rule',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Recalculate lead score for a specific contact.
     */
    public function recalculateContactScore($contactId): JsonResponse
    {
        $user = Auth::user();
        $tenantId = $user->tenant_id;

        try {
            $result = $this->leadScoringService->recalculateContactScore($contactId, $tenantId);

            return response()->json([
                'data' => $result,
                'message' => 'Contact lead score recalculated successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to recalculate contact lead score',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get lead scoring statistics.
     */
    public function getStats(): JsonResponse
    {
        $user = Auth::user();
        
        if (!$user) {
            return response()->json([
                'message' => 'Unauthenticated',
                'error' => 'User not authenticated'
            ], 401);
        }
        
        $tenantId = $user->tenant_id;

        try {
            $stats = $this->leadScoringService->getScoringStats($tenantId);

            return response()->json([
                'data' => $stats,
                'message' => 'Lead scoring statistics retrieved successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to retrieve lead scoring statistics',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get top scoring contacts.
     */
    public function getTopScoringContacts(Request $request): JsonResponse
    {
        $user = Auth::user();
        $tenantId = $user->tenant_id;

        try {
            $limit = $request->get('limit', 10);
            $contacts = $this->leadScoringService->getTopScoringContacts($tenantId, $limit);

            return response()->json([
                'data' => $contacts,
                'message' => 'Top scoring contacts retrieved successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to retrieve top scoring contacts',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get available event types for scoring rules.
     */
    public function getEventTypes(): JsonResponse
    {
        return response()->json([
            'data' => LeadScoringRule::getAvailableEventTypes(),
            'message' => 'Available event types retrieved successfully'
        ]);
    }

    /**
     * Get available operators for rule conditions.
     */
    public function getOperators(): JsonResponse
    {
        return response()->json([
            'data' => LeadScoringRule::getAvailableOperators(),
            'message' => 'Available operators retrieved successfully'
        ]);
    }
}
