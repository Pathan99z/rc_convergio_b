<?php

namespace App\Http\Controllers\Api\Hr;

use App\Http\Controllers\Controller;
use App\Models\Hr\InductionContent;
use App\Services\Hr\InductionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class InductionContentController extends Controller
{
    public function __construct(
        private InductionService $inductionService
    ) {}

    /**
     * List all induction contents (HR view).
     * 
     * GET /api/hr/induction/contents
     * Query params: status, category, search, page, per_page
     */
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', InductionContent::class);

        $currentUser = $request->user();
        $tenantId = $currentUser->tenant_id ?? $currentUser->id;

        $query = InductionContent::where('tenant_id', $tenantId)
            ->with('creator')
            ->orderBy('created_at', 'desc');

        // Filter by status (only if not empty)
        if ($request->filled('status')) {
            $query->where('status', $request->query('status'));
        }

        // Filter by category (only if not empty)
        if ($request->filled('category')) {
            $query->where('category', $request->query('category'));
        }

        // Search (only if not empty)
        if ($request->filled('search')) {
            $search = $request->query('search');
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%");
            });
        }

        $perPage = min((int) $request->query('per_page', 15), 100);
        $contents = $query->paginate($perPage);

        // Add statistics for each content
        $contents->getCollection()->transform(function ($content) {
            $assignments = $content->assignments;
            $content->assigned_count = $assignments->count();
            $content->completed_count = $assignments->where('status', 'completed')->count();
            $content->pending_count = $assignments->where('status', 'pending')->count();
            return $content;
        });

        return response()->json([
            'success' => true,
            'data' => $contents->items(),
            'meta' => [
                'current_page' => $contents->currentPage(),
                'last_page' => $contents->lastPage(),
                'per_page' => $contents->perPage(),
                'total' => $contents->total(),
            ],
        ]);
    }

    /**
     * Get single induction content.
     * 
     * GET /api/hr/induction/contents/{id}
     */
    public function show(int $id): JsonResponse
    {
        $content = InductionContent::with(['creator', 'assignments.employee'])->findOrFail($id);
        $this->authorize('view', $content);

        // Add statistics
        $assignments = $content->assignments;
        $content->assigned_count = $assignments->count();
        $content->completed_count = $assignments->where('status', 'completed')->count();
        $content->pending_count = $assignments->where('status', 'pending')->count();

        return response()->json([
            'success' => true,
            'data' => $content,
        ]);
    }

    /**
     * Create induction content.
     * 
     * POST /api/hr/induction/contents
     * Body: {
     *   title, description, content_type, category, file_url, video_url,
     *   support_documents, target_audience_type, target_departments,
     *   is_mandatory, due_date, estimated_time, status
     * }
     */
    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', InductionContent::class);

        $validator = Validator::make($request->all(), [
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'content_type' => 'required|in:document,video,both',
            'category' => 'required|in:induction,policy,training',
            'file_url' => 'nullable|string|max:500',
            'video_url' => 'nullable|url|max:1000',
            'support_documents' => 'nullable|array',
            'support_documents.*' => 'integer|exists:documents,id',
            'target_audience_type' => 'required|in:all_employees,onboarding_only,department_specific',
            'target_departments' => 'nullable|array|required_if:target_audience_type,department_specific',
            'target_departments.*' => 'integer|exists:hr_departments,id',
            'is_mandatory' => 'boolean',
            'due_date' => 'nullable|date',
            'estimated_time' => 'nullable|integer|min:1',
            'status' => 'in:draft,published,archived',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $content = $this->inductionService->createContent($validator->validated());

            return response()->json([
                'success' => true,
                'data' => $content,
                'message' => 'Induction content created successfully',
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Update induction content.
     * 
     * PUT /api/hr/induction/contents/{id}
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $content = InductionContent::findOrFail($id);
        $this->authorize('update', $content);

        $validator = Validator::make($request->all(), [
            'title' => 'sometimes|required|string|max:255',
            'description' => 'nullable|string',
            'content_type' => 'sometimes|in:document,video,both',
            'category' => 'sometimes|in:induction,policy,training',
            'file_url' => 'nullable|string|max:500',
            'video_url' => 'nullable|url|max:1000',
            'support_documents' => 'nullable|array',
            'support_documents.*' => 'integer|exists:documents,id',
            'target_audience_type' => 'sometimes|in:all_employees,onboarding_only,department_specific',
            'target_departments' => 'nullable|array|required_if:target_audience_type,department_specific',
            'target_departments.*' => 'integer|exists:hr_departments,id',
            'is_mandatory' => 'boolean',
            'due_date' => 'nullable|date',
            'estimated_time' => 'nullable|integer|min:1',
            'status' => 'in:draft,published,archived',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $content = $this->inductionService->updateContent($content, $validator->validated());

            return response()->json([
                'success' => true,
                'data' => $content,
                'message' => 'Induction content updated successfully',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Publish content and assign to employees.
     * 
     * POST /api/hr/induction/contents/{id}/publish
     */
    public function publish(int $id): JsonResponse
    {
        $content = InductionContent::findOrFail($id);
        $this->authorize('update', $content);

        try {
            $result = $this->inductionService->publishContent($content);

            return response()->json([
                'success' => true,
                'data' => $result['content'],
                'message' => "Content published successfully. Assigned to {$result['assigned_count']} employees.",
                'assigned_count' => $result['assigned_count'],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Archive content.
     * 
     * DELETE /api/hr/induction/contents/{id}
     */
    public function destroy(int $id): JsonResponse
    {
        $content = InductionContent::findOrFail($id);
        $this->authorize('delete', $content);

        try {
            $this->inductionService->archiveContent($content);

            return response()->json([
                'success' => true,
                'message' => 'Induction content archived successfully',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }
}

