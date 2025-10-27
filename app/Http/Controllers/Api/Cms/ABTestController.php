<?php

namespace App\Http\Controllers\Api\Cms;

use App\Http\Controllers\Controller;
use App\Http\Requests\Cms\StoreABTestRequest;
use App\Http\Requests\Cms\UpdateABTestRequest;
use App\Http\Resources\Cms\ABTestResource;
use App\Models\Cms\ABTest;
use App\Models\Cms\ABTestVisitor;
use App\Services\Cms\ABTestingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class ABTestController extends Controller
{
    protected ABTestingService $abTestingService;

    public function __construct(ABTestingService $abTestingService)
    {
        $this->abTestingService = $abTestingService;
    }

    /**
     * Display a listing of A/B tests.
     */
    public function index(Request $request): JsonResponse
    {
        try {
            // Simplified query without problematic relationships
            $query = ABTest::query()
                          ->when($request->filled('page_id'), fn($q) => $q->where('page_id', $request->page_id))
                          ->when($request->filled('status'), fn($q) => $q->where('status', $request->status));

            // Add pagination
            $perPage = $request->input('per_page', 20);
            $tests = $query->orderBy('created_at', 'desc')->paginate($perPage);

            // Convert to simple array format to avoid relationship issues
            $testsArray = $tests->getCollection()->map(function ($test) {
                return [
                    'id' => $test->id,
                    'name' => $test->name,
                    'description' => $test->description,
                    'page_id' => $test->page_id,
                    'variant_a_id' => $test->variant_a_id,
                    'variant_b_id' => $test->variant_b_id,
                    'traffic_split' => $test->traffic_split,
                    'status' => $test->status,
                    'performance_data' => $test->performance_data,
                    'goals' => $test->goals,
                    'started_at' => $test->started_at?->toIso8601String(),
                    'ended_at' => $test->ended_at?->toIso8601String(),
                    'min_sample_size' => $test->min_sample_size,
                    'confidence_level' => $test->confidence_level,
                    'created_at' => $test->created_at->toIso8601String(),
                    'updated_at' => $test->updated_at->toIso8601String(),
                    'is_running' => $test->status === 'running'
                ];
            });

            return response()->json([
                'data' => $testsArray,
                'meta' => [
                    'current_page' => $tests->currentPage(),
                    'last_page' => $tests->lastPage(),
                    'per_page' => $tests->perPage(),
                    'total' => $tests->total(),
                    'from' => $tests->firstItem(),
                    'to' => $tests->lastItem(),
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to fetch A/B tests', [
                'error' => $e->getMessage(),
                'user_id' => Auth::id()
            ]);

            return response()->json([
                'message' => 'Failed to fetch A/B tests',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Store a newly created A/B test.
     */
    public function store(StoreABTestRequest $request): JsonResponse
    {
        try {
            // Log the incoming request for debugging
            Log::info('A/B test creation request received', [
                'user_id' => Auth::id(),
                'request_data' => $request->all()
            ]);

            $validatedData = $request->validated();
            $validatedData['created_by'] = Auth::id();

            Log::info('A/B test validation passed', [
                'validated_data' => $validatedData
            ]);

            $test = ABTest::create($validatedData);

            Log::info('A/B test created successfully', [
                'test_id' => $test->id,
                'test_name' => $test->name
            ]);

            // Return simple format to avoid relationship issues
            $testData = [
                'id' => $test->id,
                'name' => $test->name,
                'description' => $test->description,
                'page_id' => $test->page_id,
                'variant_a_id' => $test->variant_a_id,
                'variant_b_id' => $test->variant_b_id,
                'traffic_split' => $test->traffic_split,
                'status' => $test->status,
                'performance_data' => $test->performance_data,
                'goals' => $test->goals,
                'started_at' => $test->started_at?->toIso8601String(),
                'ended_at' => $test->ended_at?->toIso8601String(),
                'min_sample_size' => $test->min_sample_size,
                'confidence_level' => $test->confidence_level,
                'created_at' => $test->created_at->toIso8601String(),
                'updated_at' => $test->updated_at->toIso8601String(),
                'is_running' => $test->status === 'running'
            ];

            return response()->json([
                'message' => 'A/B test created successfully',
                'data' => $testData
            ], 201);

        } catch (\Illuminate\Validation\ValidationException $e) {
            Log::warning('A/B test validation failed', [
                'errors' => $e->errors(),
                'user_id' => Auth::id(),
                'request_data' => $request->all()
            ]);

            return response()->json([
                'message' => 'The given data was invalid.',
                'errors' => $e->errors()
            ], 422);

        } catch (\Exception $e) {
            Log::error('Failed to create A/B test', [
                'error' => $e->getMessage(),
                'user_id' => Auth::id(),
                'request_data' => $request->all()
            ]);

            return response()->json([
                'message' => 'Failed to create A/B test',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Display the specified A/B test.
     */
    public function show(int $id): JsonResponse
    {
        try {
            $test = ABTest::with(['page', 'variantA', 'variantB', 'creator', 'visitors'])
                          ->findOrFail($id);

            return response()->json([
                'data' => new ABTestResource($test)
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'A/B test not found',
                'error' => config('app.debug') ? $e->getMessage() : 'A/B test not found'
            ], 404);
        }
    }

    /**
     * Update the specified A/B test.
     */
    public function update(UpdateABTestRequest $request, int $id): JsonResponse
    {
        try {
            $test = ABTest::findOrFail($id);
            
            // Don't allow editing running tests
            if ($test->status === 'running') {
                return response()->json([
                    'message' => 'Cannot edit running A/B test. Pause it first.'
                ], 422);
            }

            $test->update($request->validated());

            return response()->json([
                'message' => 'A/B test updated successfully',
                'data' => new ABTestResource($test->fresh(['page', 'variantA', 'variantB', 'creator']))
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to update A/B test', [
                'test_id' => $id,
                'error' => $e->getMessage(),
                'user_id' => Auth::id()
            ]);

            return response()->json([
                'message' => 'Failed to update A/B test',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Start an A/B test.
     */
    public function start(int $id): JsonResponse
    {
        try {
            $test = ABTest::findOrFail($id);
            
            if ($test->status === 'running') {
                return response()->json([
                    'message' => 'A/B test is already running'
                ], 422);
            }

            $test->update([
                'status' => 'running',
                'started_at' => now()
            ]);

            return response()->json([
                'message' => 'A/B test started successfully',
                'data' => new ABTestResource($test->fresh(['page', 'variantA', 'variantB']))
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to start A/B test', [
                'test_id' => $id,
                'error' => $e->getMessage(),
                'user_id' => Auth::id()
            ]);

            return response()->json([
                'message' => 'Failed to start A/B test',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Stop an A/B test.
     */
    public function stop(int $id): JsonResponse
    {
        try {
            $test = ABTest::findOrFail($id);
            
            $test->update([
                'status' => 'completed',
                'ended_at' => now()
            ]);

            return response()->json([
                'message' => 'A/B test stopped successfully',
                'data' => new ABTestResource($test->fresh(['page', 'variantA', 'variantB']))
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to stop A/B test', [
                'test_id' => $id,
                'error' => $e->getMessage(),
                'user_id' => Auth::id()
            ]);

            return response()->json([
                'message' => 'Failed to stop A/B test',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Get A/B test results.
     */
    public function results(int $id): JsonResponse
    {
        try {
            $test = ABTest::with(['visitors'])->findOrFail($id);
            $results = $test->getStatisticalSignificance();

            return response()->json([
                'data' => [
                    'test_id' => $test->id,
                    'test_name' => $test->name,
                    'status' => $test->status,
                    'started_at' => $test->started_at?->toIso8601String(),
                    'ended_at' => $test->ended_at?->toIso8601String(),
                    'results' => $results,
                    'total_visitors' => $test->visitors()->count(),
                    'total_conversions' => $test->visitors()->where('converted', true)->count()
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to get A/B test results', [
                'test_id' => $id,
                'error' => $e->getMessage(),
                'user_id' => Auth::id()
            ]);

            return response()->json([
                'message' => 'Failed to get A/B test results',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Record a visitor for A/B testing.
     */
    public function recordVisitor(Request $request): JsonResponse
    {
        $request->validate([
            'test_id' => 'required|integer|exists:cms_ab_tests,id',
            'visitor_id' => 'required|string',
        ]);

        try {
            $test = ABTest::findOrFail($request->test_id);
            
            if (!$test->isRunning()) {
                return response()->json([
                    'message' => 'A/B test is not currently running'
                ], 422);
            }

            $variant = $test->getVariantForVisitor($request->visitor_id);

            // Check if visitor already recorded
            $existingVisitor = ABTestVisitor::where('ab_test_id', $test->id)
                                          ->where('visitor_id', $request->visitor_id)
                                          ->first();

            if (!$existingVisitor) {
                ABTestVisitor::create([
                    'ab_test_id' => $test->id,
                    'visitor_id' => $request->visitor_id,
                    'variant_shown' => $variant,
                    'ip_address' => $request->ip(),
                    'user_agent' => $request->userAgent(),
                    'referrer' => $request->header('referer'),
                    'visited_at' => now()
                ]);
            }

            return response()->json([
                'data' => [
                    'variant' => $variant,
                    'page_id' => $variant === 'a' ? $test->variant_a_id : $test->variant_b_id
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to record A/B test visitor', [
                'error' => $e->getMessage(),
                'test_id' => $request->test_id ?? null,
                'visitor_id' => $request->visitor_id ?? null
            ]);

            return response()->json([
                'message' => 'Failed to record visitor',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Record a conversion for A/B testing.
     */
    public function recordConversion(Request $request): JsonResponse
    {
        $request->validate([
            'test_id' => 'required|integer|exists:cms_ab_tests,id',
            'visitor_id' => 'required|string',
            'conversion_data' => 'nullable|array'
        ]);

        try {
            $visitor = ABTestVisitor::where('ab_test_id', $request->test_id)
                                   ->where('visitor_id', $request->visitor_id)
                                   ->first();

            if (!$visitor) {
                return response()->json([
                    'message' => 'Visitor not found for this A/B test'
                ], 404);
            }

            if (!$visitor->converted) {
                $visitor->markAsConverted($request->conversion_data ?? []);
            }

            return response()->json([
                'message' => 'Conversion recorded successfully'
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to record A/B test conversion', [
                'error' => $e->getMessage(),
                'test_id' => $request->test_id ?? null,
                'visitor_id' => $request->visitor_id ?? null
            ]);

            return response()->json([
                'message' => 'Failed to record conversion',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Get analytics for all A/B tests.
     */
    public function analytics(Request $request): JsonResponse
    {
        $request->validate([
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'page_id' => 'nullable|integer|exists:cms_pages,id'
        ]);

        try {
            $query = ABTest::with(['visitors']);

            if ($request->filled('page_id')) {
                $query->where('page_id', $request->page_id);
            }

            if ($request->filled('start_date')) {
                $query->where('created_at', '>=', $request->start_date);
            }

            if ($request->filled('end_date')) {
                $query->where('created_at', '<=', $request->end_date);
            }

            $tests = $query->get();

            $analytics = [
                'total_tests' => $tests->count(),
                'active_tests' => $tests->where('status', 'running')->count(),
                'completed_tests' => $tests->where('status', 'completed')->count(),
                'total_visitors' => $tests->sum(function ($test) {
                    return $test->visitors->count();
                }),
                'total_conversions' => $tests->sum(function ($test) {
                    return $test->visitors->where('converted', true)->count();
                }),
                'avg_conversion_rate' => 0,
                'tests_by_status' => [
                    'draft' => $tests->where('status', 'draft')->count(),
                    'running' => $tests->where('status', 'running')->count(),
                    'paused' => $tests->where('status', 'paused')->count(),
                    'completed' => $tests->where('status', 'completed')->count(),
                    'archived' => $tests->where('status', 'archived')->count(),
                ]
            ];

            // Calculate average conversion rate
            $totalVisitors = $analytics['total_visitors'];
            $totalConversions = $analytics['total_conversions'];
            $analytics['avg_conversion_rate'] = $totalVisitors > 0 
                ? round(($totalConversions / $totalVisitors) * 100, 2) 
                : 0;

            return response()->json([
                'data' => $analytics
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to fetch A/B test analytics', [
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
     * Get metrics for dashboard.
     */
    public function metrics(): JsonResponse
    {
        try {
            $metrics = [
                'active_tests' => ABTest::where('status', 'running')->count(),
                'total_tests' => ABTest::count(),
                'tests_this_month' => ABTest::whereMonth('created_at', now()->month)
                                           ->whereYear('created_at', now()->year)
                                           ->count(),
                'avg_test_duration' => $this->getAverageTestDuration(),
                'top_performing_tests' => $this->getTopPerformingTests(),
                'recent_activity' => $this->getRecentActivity()
            ];

            return response()->json([
                'data' => $metrics
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to fetch A/B test metrics', [
                'error' => $e->getMessage(),
                'user_id' => Auth::id()
            ]);

            return response()->json([
                'message' => 'Failed to fetch metrics',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Get active A/B tests for a specific page.
     */
    public function getActiveForPage(Request $request): JsonResponse
    {
        // Make page_id optional for general active tests query
        $request->validate([
            'page_id' => 'nullable|integer|exists:cms_pages,id'
        ]);

        try {
            $query = ABTest::with(['variantA', 'variantB'])
                          ->where('status', 'running');
            
            // Only filter by page_id if provided
            if ($request->filled('page_id')) {
                $query->where('page_id', $request->page_id);
            }
            
            $tests = $query->get();

            return response()->json([
                'data' => ABTestResource::collection($tests)
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to fetch active tests for page', [
                'error' => $e->getMessage(),
                'page_id' => $request->page_id ?? null,
                'user_id' => Auth::id()
            ]);

            return response()->json([
                'message' => 'Failed to fetch active tests',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Get average test duration.
     */
    private function getAverageTestDuration(): float
    {
        $completedTests = ABTest::where('status', 'completed')
                               ->whereNotNull('started_at')
                               ->whereNotNull('ended_at')
                               ->get();

        if ($completedTests->isEmpty()) {
            return 0;
        }

        $totalDuration = $completedTests->sum(function ($test) {
            return $test->started_at->diffInDays($test->ended_at);
        });

        return round($totalDuration / $completedTests->count(), 1);
    }

    /**
     * Get top performing tests.
     */
    private function getTopPerformingTests(): array
    {
        return ABTest::with(['page', 'visitors'])
                    ->where('status', '!=', 'draft')
                    ->get()
                    ->map(function ($test) {
                        $totalVisitors = $test->visitors->count();
                        $totalConversions = $test->visitors->where('converted', true)->count();
                        $conversionRate = $totalVisitors > 0 ? ($totalConversions / $totalVisitors) * 100 : 0;

                        return [
                            'id' => $test->id,
                            'name' => $test->name,
                            'page_title' => $test->page->title ?? 'Unknown Page',
                            'conversion_rate' => round($conversionRate, 2),
                            'visitors' => $totalVisitors,
                            'conversions' => $totalConversions,
                            'status' => $test->status
                        ];
                    })
                    ->sortByDesc('conversion_rate')
                    ->take(5)
                    ->values()
                    ->toArray();
    }

    /**
     * Get recent activity.
     */
    private function getRecentActivity(): array
    {
        return ABTest::with(['page'])
                    ->orderBy('updated_at', 'desc')
                    ->limit(10)
                    ->get()
                    ->map(function ($test) {
                        return [
                            'id' => $test->id,
                            'name' => $test->name,
                            'page_title' => $test->page->title ?? 'Unknown Page',
                            'status' => $test->status,
                            'updated_at' => $test->updated_at->toIso8601String()
                        ];
                    })
                    ->toArray();
    }
}



