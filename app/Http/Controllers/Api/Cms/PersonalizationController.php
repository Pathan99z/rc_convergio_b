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
                                       ->when($request->filled('status'), fn($q) => $q->where('is_active', $request->status === 'enabled'))
                                       ->when($request->filled('search'), fn($q) => $q->where('name', 'like', '%' . $request->search . '%'))
                                       ->when($request->boolean('active_only', false), fn($q) => $q->active());

            // Pagination
            $perPage = $request->input('per_page', 20);
            $rules = $query->byPriority()->paginate($perPage);

            return response()->json([
                'data' => PersonalizationRuleResource::collection($rules->items()),
                'meta' => [
                    'current_page' => $rules->currentPage(),
                    'last_page' => $rules->lastPage(),
                    'per_page' => $rules->perPage(),
                    'total' => $rules->total(),
                    'from' => $rules->firstItem(),
                    'to' => $rules->lastItem(),
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to fetch personalization rules', [
                'error' => $e->getMessage(),
                'user_id' => Auth::id()
            ]);

            return response()->json([
                'message' => 'Failed to fetch personalization rules',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Display a specific personalization rule.
     */
    public function show(int $id): JsonResponse
    {
        try {
            $rule = PersonalizationRule::with(['page', 'creator'])->findOrFail($id);

            return response()->json([
                'data' => new PersonalizationRuleResource($rule)
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to fetch personalization rule', [
                'rule_id' => $id,
                'error' => $e->getMessage(),
                'user_id' => Auth::id()
            ]);

            return response()->json([
                'message' => 'Personalization rule not found',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 404);
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

            // Initialize performance data
            if (!isset($validatedData['performance_data'])) {
                $validatedData['performance_data'] = [
                    'impressions' => 0,
                    'conversions' => 0,
                    'conversion_rate' => 0.0
                ];
            }

            $rule = PersonalizationRule::create($validatedData);

            return response()->json([
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
                'message' => 'Personalization rule deleted successfully'
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to delete personalization rule', [
                'rule_id' => $id,
                'error' => $e->getMessage(),
                'user_id' => Auth::id()
            ]);

            return response()->json([
                'message' => 'Failed to delete personalization rule',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Evaluate personalization rules for a visitor (runtime).
     */
    public function evaluate(Request $request): JsonResponse
    {
        $request->validate([
            'page_id' => 'required|integer|exists:cms_pages,id',
            'visitor_data' => 'nullable|array'
        ]);

        try {
            $pageId = $request->page_id;
            $visitorData = $request->visitor_data ?? $this->getPersonalizationContext($request);

            // Get all enabled rules for the page, ordered by priority
            $rules = PersonalizationRule::where('page_id', $pageId)
                ->where('is_active', true)
                ->orderBy('priority', 'desc')
                ->get();

            // Find first matching rule
            foreach ($rules as $rule) {
                if ($this->evaluateRuleConditions($rule->conditions, $visitorData)) {
                    // Generate tracking ID
                    $trackingId = 'track_' . uniqid();
                    
                    // Track impression
                    $this->incrementImpression($rule->id);

                    return response()->json([
                        'matched_rule' => [
                            'id' => $rule->id,
                            'name' => $rule->name,
                            'variant_content' => $rule->variant_data
                        ],
                        'default_content' => false,
                        'tracking_id' => $trackingId
                    ]);
                }
            }

            // No rules matched
            return response()->json([
                'matched_rule' => null,
                'default_content' => true,
                'tracking_id' => null
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to evaluate personalization rules', [
                'error' => $e->getMessage(),
                'user_id' => Auth::id(),
                'page_id' => $request->page_id ?? null
            ]);

            return response()->json([
                'message' => 'Failed to evaluate personalization rules',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Toggle personalization rule status.
     */
    public function toggle(int $id): JsonResponse
    {
        try {
            $rule = PersonalizationRule::findOrFail($id);
            $rule->is_active = !$rule->is_active;
            $rule->save();

            $status = $rule->is_active ? 'enabled' : 'disabled';

            return response()->json([
                'message' => "Personalization rule {$status} successfully",
                'data' => [
                    'id' => $rule->id,
                    'is_active' => $rule->is_active,
                    'updated_at' => $rule->updated_at
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to toggle personalization rule', [
                'rule_id' => $id,
                'error' => $e->getMessage(),
                'user_id' => Auth::id()
            ]);

            return response()->json([
                'message' => 'Failed to toggle personalization rule',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Test a personalization rule with visitor data.
     */
    public function test(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'test_data' => 'required|array'
        ]);

        try {
            $rule = PersonalizationRule::findOrFail($id);
            $testData = $request->test_data;

            $matches = $this->evaluateRuleConditions($rule->conditions, $testData);
            $matchedConditions = [];

            // Evaluate each condition individually for detailed feedback
            foreach ($rule->conditions as $condition) {
                $result = $this->evaluateSingleCondition($condition, $testData);
                $matchedConditions[] = [
                    'attribute' => $condition['attribute'] ?? $condition['field'],
                    'operator' => $condition['operator'],
                    'value' => $condition['value'],
                    'result' => $result
                ];
            }

            return response()->json([
                'matches' => $matches,
                'matched_conditions' => $matchedConditions,
                'variant_content' => $matches ? $rule->variant_data : null,
                'rule' => [
                    'id' => $rule->id,
                    'name' => $rule->name
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to test personalization rule', [
                'rule_id' => $id,
                'error' => $e->getMessage(),
                'user_id' => Auth::id()
            ]);

            return response()->json([
                'message' => 'Failed to test personalization rule',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Get analytics for a specific rule.
     */
    public function analytics(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date'
        ]);

        try {
            $rule = PersonalizationRule::findOrFail($id);
            $performanceData = $rule->performance_data ?? [
                'impressions' => 0,
                'conversions' => 0,
                'conversion_rate' => 0.0
            ];

            return response()->json([
                'analytics' => [
                    'rule_id' => $rule->id,
                    'rule_name' => $rule->name,
                    'impressions' => $performanceData['impressions'] ?? 0,
                    'conversions' => $performanceData['conversions'] ?? 0,
                    'conversion_rate' => $performanceData['conversion_rate'] ?? 0.0,
                    'improvement_over_default' => $performanceData['improvement'] ?? 0.0,
                    'daily_breakdown' => $performanceData['daily_breakdown'] ?? [],
                    'top_converting_conditions' => $performanceData['top_conditions'] ?? []
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to fetch rule analytics', [
                'rule_id' => $id,
                'error' => $e->getMessage(),
                'user_id' => Auth::id()
            ]);

            return response()->json([
                'message' => 'Failed to fetch analytics',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Get global analytics for all personalization rules.
     */
    public function globalAnalytics(Request $request): JsonResponse
    {
        $request->validate([
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'page_id' => 'nullable|integer|exists:cms_pages,id'
        ]);

        try {
            $query = PersonalizationRule::query();

            if ($request->filled('page_id')) {
                $query->where('page_id', $request->page_id);
            }

            $rules = $query->get();
            
            $totalRules = $rules->count();
            $activeRules = $rules->where('is_active', true)->count();
            $pagesWithRules = $rules->pluck('page_id')->unique()->count();

            $totalImpressions = 0;
            $totalConversions = 0;
            $topPerformingRules = [];

            foreach ($rules as $rule) {
                $performance = $rule->performance_data ?? [];
                $impressions = $performance['impressions'] ?? 0;
                $conversions = $performance['conversions'] ?? 0;
                
                $totalImpressions += $impressions;
                $totalConversions += $conversions;

                if ($impressions > 0) {
                    $topPerformingRules[] = [
                        'id' => $rule->id,
                        'name' => $rule->name,
                        'conversion_rate' => $impressions > 0 ? round(($conversions / $impressions) * 100, 2) : 0,
                        'improvement' => $performance['improvement'] ?? 0
                    ];
                }
            }

            // Sort by conversion rate
            usort($topPerformingRules, fn($a, $b) => $b['conversion_rate'] <=> $a['conversion_rate']);
            $topPerformingRules = array_slice($topPerformingRules, 0, 10);

            $avgConversionRate = $totalImpressions > 0 ? round(($totalConversions / $totalImpressions) * 100, 2) : 0;

            return response()->json([
                'analytics' => [
                    'total_rules' => $totalRules,
                    'active_rules' => $activeRules,
                    'pages_with_rules' => $pagesWithRules,
                    'total_impressions' => $totalImpressions,
                    'total_conversions' => $totalConversions,
                    'avg_conversion_rate' => $avgConversionRate,
                    'top_performing_rules' => $topPerformingRules
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to fetch global analytics', [
                'error' => $e->getMessage(),
                'user_id' => Auth::id()
            ]);

            return response()->json([
                'message' => 'Failed to fetch global analytics',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Bulk toggle personalization rules.
     */
    public function bulkToggle(Request $request): JsonResponse
    {
        $request->validate([
            'rule_ids' => 'required|array|min:1',
            'rule_ids.*' => 'integer|exists:cms_personalization_rules,id',
            'is_active' => 'required|boolean'
        ]);

        try {
            $updatedCount = PersonalizationRule::whereIn('id', $request->rule_ids)
                ->update(['is_active' => $request->is_active]);

            $status = $request->is_active ? 'enabled' : 'disabled';

            return response()->json([
                'message' => "{$updatedCount} personalization rules {$status} successfully",
                'updated_count' => $updatedCount
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to bulk toggle rules', [
                'error' => $e->getMessage(),
                'user_id' => Auth::id(),
                'rule_ids' => $request->rule_ids ?? []
            ]);

            return response()->json([
                'message' => 'Failed to bulk toggle rules',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Track impression for a personalization rule.
     */
    public function trackImpression(Request $request): JsonResponse
    {
        $request->validate([
            'rule_id' => 'required|integer|exists:cms_personalization_rules,id',
            'tracking_id' => 'required|string',
            'visitor_data' => 'nullable|array'
        ]);

        try {
            $this->incrementImpression($request->rule_id);

            return response()->json([
                'tracked' => true
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to track impression', [
                'rule_id' => $request->rule_id ?? null,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'message' => 'Failed to track impression',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Track conversion for a personalization rule.
     */
    public function trackConversion(Request $request): JsonResponse
    {
        $request->validate([
            'rule_id' => 'required|integer|exists:cms_personalization_rules,id',
            'tracking_id' => 'required|string',
            'conversion_type' => 'nullable|string',
            'conversion_value' => 'nullable|numeric'
        ]);

        try {
            $rule = PersonalizationRule::findOrFail($request->rule_id);
            $performance = $rule->performance_data ?? [
                'impressions' => 0,
                'conversions' => 0,
                'conversion_rate' => 0.0
            ];

            // Increment conversions
            $performance['conversions'] = ($performance['conversions'] ?? 0) + 1;

            // Recalculate conversion rate
            if ($performance['impressions'] > 0) {
                $performance['conversion_rate'] = round(
                    ($performance['conversions'] / $performance['impressions']) * 100,
                    2
                );
            }

            $rule->update(['performance_data' => $performance]);

            return response()->json([
                'tracked' => true,
                'new_conversion_rate' => $performance['conversion_rate']
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to track conversion', [
                'rule_id' => $request->rule_id ?? null,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'message' => 'Failed to track conversion',
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
            'data' => [
                ['value' => 'country', 'label' => 'Country'],
                ['value' => 'device', 'label' => 'Device Type'],
                ['value' => 'referrer', 'label' => 'Referrer'],
                ['value' => 'user_agent', 'label' => 'User Agent'],
                ['value' => 'user_id', 'label' => 'User ID'],
                ['value' => 'ip_address', 'label' => 'IP Address'],
                ['value' => 'language', 'label' => 'Language'],
                ['value' => 'lifecycle_stage', 'label' => 'Lifecycle Stage'],
                ['value' => 'list_membership', 'label' => 'List Membership'],
            ]
        ]);
    }

    /**
     * Evaluate all conditions for a rule.
     */
    protected function evaluateRuleConditions(array $conditions, array $context): bool
    {
        foreach ($conditions as $condition) {
            if (!$this->evaluateSingleCondition($condition, $context)) {
                return false; // All conditions must match (AND logic)
            }
        }
        return true;
    }

    /**
     * Evaluate a single condition.
     */
    protected function evaluateSingleCondition(array $condition, array $context): bool
    {
        $attribute = $condition['attribute'] ?? $condition['field'] ?? '';
        $operator = $condition['operator'] ?? 'equals';
        $expectedValue = $condition['value'] ?? '';
        $actualValue = $context[$attribute] ?? null;

        switch ($operator) {
            case '=':
            case 'equals':
                return strtolower((string)$actualValue) == strtolower((string)$expectedValue);
            
            case '!=':
            case 'not_equals':
                return strtolower((string)$actualValue) != strtolower((string)$expectedValue);
            
            case 'contains':
                return str_contains(strtolower((string)$actualValue), strtolower((string)$expectedValue));
            
            case 'starts_with':
                return str_starts_with(strtolower((string)$actualValue), strtolower((string)$expectedValue));
            
            case 'in':
                $list = is_array($expectedValue) ? $expectedValue : explode(',', $expectedValue);
                return in_array($actualValue, $list);
            
            case 'not_in':
                $list = is_array($expectedValue) ? $expectedValue : explode(',', $expectedValue);
                return !in_array($actualValue, $list);
            
            default:
                return false;
        }
    }

    /**
     * Increment impression count for a rule.
     */
    protected function incrementImpression(int $ruleId): void
    {
        $rule = PersonalizationRule::find($ruleId);
        if (!$rule) {
            return;
        }

        $performance = $rule->performance_data ?? [
            'impressions' => 0,
            'conversions' => 0,
            'conversion_rate' => 0.0
        ];

        $performance['impressions'] = ($performance['impressions'] ?? 0) + 1;

        // Recalculate conversion rate
        if ($performance['impressions'] > 0 && isset($performance['conversions'])) {
            $performance['conversion_rate'] = round(
                ($performance['conversions'] / $performance['impressions']) * 100,
                2
            );
        }

        $rule->update(['performance_data' => $performance]);
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
            'country' => $request->header('cf-ipcountry') ?? 'US',
            'device' => $this->detectDevice($request->userAgent() ?? ''),
            'language' => $request->header('accept-language'),
            'user_id' => Auth::id(),
            'timestamp' => now()->toIso8601String()
        ];
    }

    /**
     * Simple device detection.
     */
    protected function detectDevice(?string $userAgent): string
    {
        if (!$userAgent) {
            return 'desktop';
        }
        
        if (preg_match('/mobile|android|iphone/i', $userAgent)) {
            return 'mobile';
        }
        if (preg_match('/tablet|ipad/i', $userAgent)) {
            return 'tablet';
        }
        return 'desktop';
    }
}


