<?php

namespace App\Http\Controllers\Api\Cms;

use App\Http\Controllers\Controller;
use App\Http\Requests\Cms\StorePersonalizationRuleRequest;
use App\Http\Requests\Cms\UpdatePersonalizationRuleRequest;
use App\Http\Resources\Cms\PersonalizationRuleResource;
use App\Models\Cms\PersonalizationRule;
use App\Services\Cms\PersonalizationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class PersonalizationController extends Controller
{
    protected PersonalizationService $personalizationService;

    public function __construct(PersonalizationService $personalizationService)
    {
        $this->personalizationService = $personalizationService;
    }

    /**
     * Display personalization rules for a page.
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $query = PersonalizationRule::with(['page', 'creator'])
                                       ->when($request->filled('page_id'), fn($q) => $q->where('page_id', $request->page_id))
                                       ->when($request->filled('section_id'), fn($q) => $q->where('section_id', $request->section_id))
                                       ->when($request->boolean('active_only', true), fn($q) => $q->active());

            $rules = $query->byPriority()->get();

            return response()->json([
                'success' => true,
                'data' => PersonalizationRuleResource::collection($rules)
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to fetch personalization rules', [
                'error' => $e->getMessage(),
                'user_id' => Auth::id()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch personalization rules',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Store a newly created personalization rule.
     */
    public function store(StorePersonalizationRuleRequest $request): JsonResponse
    {
        try {
            $validatedData = $request->validated();
            $validatedData['created_by'] = Auth::id();

            $rule = PersonalizationRule::create($validatedData);

            return response()->json([
                'success' => true,
                'message' => 'Personalization rule created successfully',
                'data' => new PersonalizationRuleResource($rule->load(['page', 'creator']))
            ], 201);

        } catch (\Exception $e) {
            Log::error('Failed to create personalization rule', [
                'error' => $e->getMessage(),
                'user_id' => Auth::id(),
                'request_data' => $request->all()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to create personalization rule',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Update the specified personalization rule.
     */
    public function update(UpdatePersonalizationRuleRequest $request, int $id): JsonResponse
    {
        try {
            $rule = PersonalizationRule::findOrFail($id);
            $rule->update($request->validated());

            return response()->json([
                'success' => true,
                'message' => 'Personalization rule updated successfully',
                'data' => new PersonalizationRuleResource($rule->fresh(['page', 'creator']))
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to update personalization rule', [
                'rule_id' => $id,
                'error' => $e->getMessage(),
                'user_id' => Auth::id()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to update personalization rule',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Remove the specified personalization rule.
     */
    public function destroy(int $id): JsonResponse
    {
        try {
            $rule = PersonalizationRule::findOrFail($id);
            $rule->delete();

            return response()->json([
                'success' => true,
                'message' => 'Personalization rule deleted successfully'
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to delete personalization rule', [
                'rule_id' => $id,
                'error' => $e->getMessage(),
                'user_id' => Auth::id()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to delete personalization rule',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Evaluate personalization rules for a page.
     */
    public function evaluate(Request $request): JsonResponse
    {
        $request->validate([
            'page_id' => 'required|integer|exists:cms_pages,id',
            'context' => 'nullable|array'
        ]);

        try {
            $pageId = $request->page_id;
            $context = $request->context ?? $this->getPersonalizationContext($request);

            $personalizedContent = $this->personalizationService->evaluateRules($pageId, $context);

            return response()->json([
                'success' => true,
                'data' => [
                    'page_id' => $pageId,
                    'personalized_content' => $personalizedContent,
                    'context_used' => $context,
                    'rules_applied' => count($personalizedContent)
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to evaluate personalization rules', [
                'error' => $e->getMessage(),
                'user_id' => Auth::id(),
                'page_id' => $request->page_id ?? null
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to evaluate personalization rules',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Get available condition operators.
     */
    public function operators(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => [
                ['value' => 'equals', 'label' => 'Equals'],
                ['value' => 'not_equals', 'label' => 'Not Equals'],
                ['value' => 'contains', 'label' => 'Contains'],
                ['value' => 'starts_with', 'label' => 'Starts With'],
                ['value' => 'in', 'label' => 'In List'],
                ['value' => 'not_in', 'label' => 'Not In List'],
            ]
        ]);
    }

    /**
     * Get available condition fields.
     */
    public function fields(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => [
                ['value' => 'country', 'label' => 'Country'],
                ['value' => 'device', 'label' => 'Device Type'],
                ['value' => 'referrer', 'label' => 'Referrer'],
                ['value' => 'user_agent', 'label' => 'User Agent'],
                ['value' => 'user_id', 'label' => 'User ID'],
                ['value' => 'ip_address', 'label' => 'IP Address'],
            ]
        ]);
    }

    /**
     * Get personalization context from request.
     */
    protected function getPersonalizationContext(Request $request): array
    {
        return [
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'referrer' => $request->header('referer'),
            'country' => $request->header('cf-ipcountry'),
            'device' => $this->detectDevice($request->userAgent()),
            'user_id' => Auth::id(),
            'timestamp' => now()->toIso8601String()
        ];
    }

    /**
     * Simple device detection.
     */
    protected function detectDevice(string $userAgent): string
    {
        if (preg_match('/mobile|android|iphone|ipad/i', $userAgent)) {
            return 'mobile';
        }
        if (preg_match('/tablet|ipad/i', $userAgent)) {
            return 'tablet';
        }
        return 'desktop';
    }
}
