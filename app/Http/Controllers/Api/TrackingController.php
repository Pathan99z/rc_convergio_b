<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\VisitorIntent;
use App\Models\Company;
use App\Models\Contact;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class TrackingController extends Controller
{
    /**
     * Log a visitor tracking event.
     */
    public function logEvent(Request $request): JsonResponse
    {
        $user = Auth::user();
        $tenantId = $user->tenant_id;

        $validated = $request->validate([
            'company_id' => 'nullable|exists:companies,id',
            'contact_id' => 'nullable|exists:contacts,id',
            'page_url' => 'required|string|max:500',
            'duration_seconds' => 'nullable|integer|min:0',
            'action' => [
                'required',
                'string',
                Rule::in(array_keys(VisitorIntent::getAvailableActions()))
            ],
            'score' => 'nullable|integer|min:0|max:100',
            'metadata' => 'nullable|array',
            'session_id' => 'nullable|string|max:255',
        ]);

        // Verify company belongs to tenant if provided
        if (isset($validated['company_id'])) {
            $company = Company::where('id', $validated['company_id'])
                ->where('tenant_id', $tenantId)
                ->firstOrFail();
        }

        // Verify contact belongs to tenant if provided
        if (isset($validated['contact_id'])) {
            $contact = Contact::where('id', $validated['contact_id'])
                ->where('tenant_id', $tenantId)
                ->firstOrFail();
        }

        try {
            DB::beginTransaction();

            // Calculate score if not provided
            $score = $validated['score'] ?? VisitorIntent::calculateScore(
                $validated['action'],
                $validated['duration_seconds'] ?? 0,
                $validated['metadata'] ?? []
            );

            $visitorIntent = VisitorIntent::create([
                'company_id' => $validated['company_id'] ?? null,
                'contact_id' => $validated['contact_id'] ?? null,
                'page_url' => $validated['page_url'],
                'duration_seconds' => $validated['duration_seconds'] ?? 0,
                'action' => $validated['action'],
                'score' => $score,
                'metadata' => $validated['metadata'] ?? [],
                'session_id' => $validated['session_id'] ?? null,
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'tenant_id' => $tenantId,
            ]);

            DB::commit();

            return response()->json([
                'data' => [
                    'id' => $visitorIntent->id,
                    'contact_id' => $visitorIntent->contact_id,
                    'company_id' => $visitorIntent->company_id,
                    'page_url' => $visitorIntent->page_url,
                    'duration_seconds' => $visitorIntent->duration_seconds,
                    'action' => $visitorIntent->action,
                    'score' => $visitorIntent->score,
                    'intent_level' => $visitorIntent->getIntentLevel(),
                    'intent_level_label' => $visitorIntent->getIntentLevelLabel(),
                    'created_at' => $visitorIntent->created_at,
                ],
                'message' => 'Visitor event logged successfully'
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Failed to log visitor event',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get visitor intent signals with filtering and analytics.
     */
    public function getIntentSignals(Request $request): JsonResponse
    {
        $user = Auth::user();
        $tenantId = $user->tenant_id;

        $query = VisitorIntent::where('tenant_id', $tenantId)
            ->with(['company:id,name', 'contact:id,name,email']);

        // Filter by company if provided
        if ($request->has('company_id')) {
            $query->where('company_id', $request->get('company_id'));
        }

        // Filter by contact if provided
        if ($request->has('contact_id')) {
            $query->where('contact_id', $request->get('contact_id'));
        }

        // Filter by action if provided
        if ($request->has('action')) {
            $query->where('action', $request->get('action'));
        }

        // Filter by page URL if provided
        if ($request->has('page_url')) {
            $query->where('page_url', 'like', '%' . $request->get('page_url') . '%');
        }

        // Filter by minimum score if provided
        if ($request->has('min_score')) {
            $query->where('score', '>=', $request->get('min_score'));
        }

        // Filter by intent level if provided
        if ($request->has('intent_level')) {
            $intentLevel = $request->get('intent_level');
            switch ($intentLevel) {
                case 'very_high':
                    $query->where('score', '>=', 80);
                    break;
                case 'high':
                    $query->where('score', '>=', 60)->where('score', '<', 80);
                    break;
                case 'medium':
                    $query->where('score', '>=', 40)->where('score', '<', 60);
                    break;
                case 'low':
                    $query->where('score', '>=', 20)->where('score', '<', 40);
                    break;
                case 'very_low':
                    $query->where('score', '<', 20);
                    break;
            }
        }

        // Filter by date range if provided
        if ($request->has('date_from')) {
            $query->where('created_at', '>=', $request->get('date_from'));
        }

        if ($request->has('date_to')) {
            $query->where('created_at', '<=', $request->get('date_to'));
        }

        // Filter by session if provided
        if ($request->has('session_id')) {
            $query->where('session_id', $request->get('session_id'));
        }

        // Sort by score (highest first) or created_at (newest first)
        $sortBy = $request->get('sort_by', 'created_at');
        $sortOrder = $request->get('sort_order', 'desc');
        
        if ($sortBy === 'score') {
            $query->orderBy('score', $sortOrder);
        } else {
            $query->orderBy('created_at', $sortOrder);
        }

        $intents = $query->paginate($request->get('per_page', 15));

        // Add intent level information to each record
        $intents->getCollection()->transform(function ($intent) {
            $intent->intent_level = $intent->getIntentLevel();
            $intent->intent_level_label = $intent->getIntentLevelLabel();
            return $intent;
        });

        return response()->json([
            'data' => $intents->items(),
            'meta' => [
                'current_page' => $intents->currentPage(),
                'last_page' => $intents->lastPage(),
                'per_page' => $intents->perPage(),
                'total' => $intents->total(),
            ],
            'message' => 'Visitor intent signals retrieved successfully'
        ]);
    }

    /**
     * Get intent analytics and statistics.
     */
    public function getIntentAnalytics(Request $request): JsonResponse
    {
        $user = Auth::user();
        $tenantId = $user->tenant_id;

        $query = VisitorIntent::where('tenant_id', $tenantId);

        // Apply date range filter if provided
        if ($request->has('date_from')) {
            $query->where('created_at', '>=', $request->get('date_from'));
        }

        if ($request->has('date_to')) {
            $query->where('created_at', '<=', $request->get('date_to'));
        }

        // Get basic statistics
        $totalEvents = $query->count();
        $avgScore = $query->avg('score');
        $highIntentCount = $query->highIntent()->count();

        // Get action breakdown
        $actionBreakdown = $query->selectRaw('action, COUNT(*) as count, AVG(score) as avg_score')
            ->groupBy('action')
            ->get()
            ->keyBy('action');

        // Get top pages by intent
        $topPages = $query->selectRaw('page_url, COUNT(*) as visits, AVG(score) as avg_score, MAX(score) as max_score')
            ->groupBy('page_url')
            ->orderBy('avg_score', 'desc')
            ->limit(10)
            ->get();

        // Get intent level distribution
        $intentDistribution = [
            'very_high' => $query->where('score', '>=', 80)->count(),
            'high' => $query->where('score', '>=', 60)->where('score', '<', 80)->count(),
            'medium' => $query->where('score', '>=', 40)->where('score', '<', 60)->count(),
            'low' => $query->where('score', '>=', 20)->where('score', '<', 40)->count(),
            'very_low' => $query->where('score', '<', 20)->count(),
        ];

        // Get top companies by intent
        $topCompanies = $query->whereNotNull('company_id')
            ->with('company:id,name')
            ->selectRaw('company_id, COUNT(*) as events, AVG(score) as avg_score, MAX(score) as max_score')
            ->groupBy('company_id')
            ->orderBy('avg_score', 'desc')
            ->limit(10)
            ->get();

        return response()->json([
            'data' => [
                'overview' => [
                    'total_events' => $totalEvents,
                    'average_score' => round($avgScore, 2),
                    'high_intent_events' => $highIntentCount,
                    'high_intent_percentage' => $totalEvents > 0 ? round(($highIntentCount / $totalEvents) * 100, 2) : 0,
                ],
                'action_breakdown' => $actionBreakdown,
                'top_pages' => $topPages,
                'intent_distribution' => $intentDistribution,
                'top_companies' => $topCompanies,
            ],
            'message' => 'Intent analytics retrieved successfully'
        ]);
    }

    /**
     * Get available tracking actions.
     */
    public function getAvailableActions(): JsonResponse
    {
        return response()->json([
            'data' => VisitorIntent::getAvailableActions(),
            'message' => 'Available tracking actions retrieved successfully'
        ]);
    }

    /**
     * Get intent level definitions.
     */
    public function getIntentLevels(): JsonResponse
    {
        return response()->json([
            'data' => [
                'very_high' => ['label' => 'Very High Intent', 'min_score' => 80, 'max_score' => 100],
                'high' => ['label' => 'High Intent', 'min_score' => 60, 'max_score' => 79],
                'medium' => ['label' => 'Medium Intent', 'min_score' => 40, 'max_score' => 59],
                'low' => ['label' => 'Low Intent', 'min_score' => 20, 'max_score' => 39],
                'very_low' => ['label' => 'Very Low Intent', 'min_score' => 0, 'max_score' => 19],
            ],
            'message' => 'Intent level definitions retrieved successfully'
        ]);
    }
}
