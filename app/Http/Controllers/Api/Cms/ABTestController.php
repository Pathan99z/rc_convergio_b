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
            $query = ABTest::with(['page', 'variantA', 'variantB', 'creator'])
                          ->when($request->filled('page_id'), fn($q) => $q->where('page_id', $request->page_id))
                          ->when($request->filled('status'), fn($q) => $q->where('status', $request->status));

            $tests = $query->orderBy('created_at', 'desc')->get();

            return response()->json([
                'success' => true,
                'data' => ABTestResource::collection($tests)
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to fetch A/B tests', [
                'error' => $e->getMessage(),
                'user_id' => Auth::id()
            ]);

            return response()->json([
                'success' => false,
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
            $validatedData = $request->validated();
            $validatedData['created_by'] = Auth::id();

            $test = ABTest::create($validatedData);

            return response()->json([
                'success' => true,
                'message' => 'A/B test created successfully',
                'data' => new ABTestResource($test->load(['page', 'variantA', 'variantB', 'creator']))
            ], 201);

        } catch (\Exception $e) {
            Log::error('Failed to create A/B test', [
                'error' => $e->getMessage(),
                'user_id' => Auth::id(),
                'request_data' => $request->all()
            ]);

            return response()->json([
                'success' => false,
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
                'success' => true,
                'data' => new ABTestResource($test)
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
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
                    'success' => false,
                    'message' => 'Cannot edit running A/B test. Pause it first.'
                ], 422);
            }

            $test->update($request->validated());

            return response()->json([
                'success' => true,
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
                'success' => false,
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
                    'success' => false,
                    'message' => 'A/B test is already running'
                ], 422);
            }

            $test->update([
                'status' => 'running',
                'started_at' => now()
            ]);

            return response()->json([
                'success' => true,
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
                'success' => false,
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
                'success' => true,
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
                'success' => false,
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
                'success' => true,
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
                'success' => false,
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
                    'success' => false,
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
                'success' => true,
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
                'success' => false,
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
                    'success' => false,
                    'message' => 'Visitor not found for this A/B test'
                ], 404);
            }

            if (!$visitor->converted) {
                $visitor->markAsConverted($request->conversion_data ?? []);
            }

            return response()->json([
                'success' => true,
                'message' => 'Conversion recorded successfully'
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to record A/B test conversion', [
                'error' => $e->getMessage(),
                'test_id' => $request->test_id ?? null,
                'visitor_id' => $request->visitor_id ?? null
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to record conversion',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }
}
