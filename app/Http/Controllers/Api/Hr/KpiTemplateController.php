<?php

namespace App\Http\Controllers\Api\Hr;

use App\Constants\HrConstants;
use App\Http\Controllers\Controller;
use App\Models\Hr\KpiTemplate;
use App\Services\Hr\KpiService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class KpiTemplateController extends Controller
{
    protected KpiService $kpiService;

    public function __construct(KpiService $kpiService)
    {
        $this->kpiService = $kpiService;
    }

    /**
     * Display a listing of KPI templates.
     */
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', KpiTemplate::class);

        $filters = $request->only(['search', 'status', 'department_id', 'designation_id', 'review_period', 'sortBy', 'sortOrder']);
        $perPage = min((int) $request->get('per_page', 15), 100);

        $templates = $this->kpiService->getTemplates($filters, $perPage);

        // Add total weight for each template
        $templates->getCollection()->transform(function ($template) {
            $template->total_weight = $template->items->sum('weight');
            return $template;
        });

        return response()->json([
            'success' => true,
            'data' => $templates->items(),
            'meta' => [
                'current_page' => $templates->currentPage(),
                'per_page' => $templates->perPage(),
                'total' => $templates->total(),
                'last_page' => $templates->lastPage(),
            ],
        ]);
    }

    /**
     * Store a newly created KPI template.
     */
    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', KpiTemplate::class);

        // Filter out empty items before validation
        $requestData = $request->all();
        if (isset($requestData['items']) && is_array($requestData['items'])) {
            $requestData['items'] = array_filter($requestData['items'], function($item) {
                return !empty($item['name']) && trim($item['name']) !== '';
            });
            // Re-index array after filtering
            $requestData['items'] = array_values($requestData['items']);
        }

        $validator = Validator::make($requestData, [
            'name' => 'required|string|max:255',
            'department_id' => 'nullable|exists:hr_departments,id',
            'designation_id' => 'nullable|exists:hr_designations,id',
            'review_period' => 'required|in:monthly,quarterly,yearly,once',
            'description' => 'nullable|string',
            'status' => 'nullable|in:draft,published,archived',
            'items' => 'required|array|min:1',
            'items.*.name' => 'required|string|max:255',
            'items.*.weight' => 'required|numeric|min:0|max:100',
            'items.*.description' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $template = $this->kpiService->createTemplate($validator->validated());

            return response()->json([
                'success' => true,
                'data' => $template->load(['items']),
                'message' => 'KPI template created successfully',
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Display the specified KPI template.
     */
    public function show(int $id): JsonResponse
    {
        $template = $this->kpiService->getTemplate($id);
        $this->authorize('view', $template);

        $template->total_weight = $template->items->sum('weight');

        return response()->json([
            'success' => true,
            'data' => $template,
        ]);
    }

    /**
     * Update the specified KPI template.
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $template = $this->kpiService->getTemplate($id);
        $this->authorize('update', $template);

        // Filter out empty items before validation
        $requestData = $request->all();
        if (isset($requestData['items']) && is_array($requestData['items'])) {
            $requestData['items'] = array_filter($requestData['items'], function($item) {
                return !empty($item['name']) && trim($item['name']) !== '';
            });
            // Re-index array after filtering
            $requestData['items'] = array_values($requestData['items']);
        }

        $validator = Validator::make($requestData, [
            'name' => 'sometimes|required|string|max:255',
            'department_id' => 'nullable|exists:hr_departments,id',
            'designation_id' => 'nullable|exists:hr_designations,id',
            'review_period' => 'sometimes|required|in:monthly,quarterly,yearly,once',
            'description' => 'nullable|string',
            'status' => 'nullable|in:draft,published,archived',
            'items' => 'sometimes|required|array|min:1',
            'items.*.name' => 'required|string|max:255',
            'items.*.weight' => 'required|numeric|min:0|max:100',
            'items.*.description' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $template = $this->kpiService->updateTemplate($template, $validator->validated());

            return response()->json([
                'success' => true,
                'data' => $template->load(['items']),
                'message' => 'KPI template updated successfully',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Remove the specified KPI template.
     */
    public function destroy(int $id): JsonResponse
    {
        $template = $this->kpiService->getTemplate($id);
        $this->authorize('delete', $template);

        try {
            $this->kpiService->deleteTemplate($template);

            return response()->json([
                'success' => true,
                'message' => 'KPI template deleted successfully',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Assign template to employees.
     */
    public function assign(Request $request): JsonResponse
    {
        $this->authorize('create', KpiTemplate::class);

        $validator = Validator::make($request->all(), [
            'kpi_template_id' => 'required|exists:hr_kpi_templates,id',
            'employee_ids' => 'required|array|min:1',
            'employee_ids.*' => 'required|exists:hr_employees,id',
            'review_period_value' => 'required|string|max:50',
            'review_period_start' => 'required|date',
            'review_period_end' => 'required|date|after_or_equal:review_period_start',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $assignments = $this->kpiService->assignTemplate($validator->validated());

            return response()->json([
                'success' => true,
                'data' => $assignments,
                'message' => 'KPI template assigned to ' . count($assignments) . ' employee(s) successfully',
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Get analytics data.
     */
    public function analytics(Request $request): JsonResponse
    {
        $this->authorize('viewAny', KpiTemplate::class);

        $filters = $request->only(['review_period_value', 'department_id', 'status']);
        $analytics = $this->kpiService->getAnalytics($filters);

        return response()->json([
            'success' => true,
            'data' => $analytics,
        ]);
    }
}

