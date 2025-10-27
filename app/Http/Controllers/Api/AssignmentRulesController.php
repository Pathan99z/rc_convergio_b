<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\AssignmentRules\StoreAssignmentRuleRequest;
use App\Http\Requests\AssignmentRules\UpdateAssignmentRuleRequest;
use App\Http\Resources\AssignmentRuleResource;
use App\Models\AssignmentRule;
use App\Services\AssignmentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Log;

class AssignmentRulesController extends Controller
{
    public function __construct(
        private AssignmentService $assignmentService
    ) {}

    /**
     * Display a listing of assignment rules.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', AssignmentRule::class);

        $tenantId = $request->user()->tenant_id ?? $request->user()->id;

        $query = AssignmentRule::forTenant($tenantId)
            ->with(['creator:id,name,email'])
            ->withCount('audits');

        // Apply filters
        if ($request->has('active')) {
            $query->where('active', $request->boolean('active'));
        }

        if ($request->has('record_type')) {
            $query->forRecordType($request->get('record_type'));
        }

        if ($request->has('search')) {
            $search = $request->get('search');
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%");
            });
        }

        // Apply sorting
        $sortBy = $request->get('sort_by', 'priority');
        $sortOrder = $request->get('sort_order', 'asc');
        $query->orderBy($sortBy, $sortOrder);

        $perPage = min((int) $request->get('per_page', 15), 100);
        $rules = $query->paginate($perPage);

        return AssignmentRuleResource::collection($rules);
    }

    /**
     * Store a newly created assignment rule.
     */
    public function store(StoreAssignmentRuleRequest $request): JsonResource
    {
        $this->authorize('create', AssignmentRule::class);

        $data = $request->validated();
        $data['tenant_id'] = $request->user()->tenant_id ?? $request->user()->id;
        $data['created_by'] = $request->user()->id;

        $rule = AssignmentRule::create($data);

        // Clear cache for this tenant
        $this->assignmentService->clearRulesCache($data['tenant_id']);

        Log::info('Assignment rule created', [
            'rule_id' => $rule->id,
            'tenant_id' => $data['tenant_id'],
            'created_by' => $data['created_by']
        ]);

        return new AssignmentRuleResource($rule->load('creator'));
    }

    /**
     * Display the specified assignment rule.
     */
    public function show(Request $request, int $id): JsonResource
    {
        $tenantId = $request->user()->tenant_id ?? $request->user()->id;
        
        $rule = AssignmentRule::forTenant($tenantId)
            ->with(['creator:id,name,email', 'audits' => function ($query) {
                $query->latest()->limit(10);
            }])
            ->findOrFail($id);

        $this->authorize('view', $rule);

        return new AssignmentRuleResource($rule);
    }

    /**
     * Update the specified assignment rule.
     */
    public function update(UpdateAssignmentRuleRequest $request, int $id): JsonResource
    {
        $tenantId = $request->user()->tenant_id ?? $request->user()->id;
        
        $rule = AssignmentRule::forTenant($tenantId)->findOrFail($id);
        $this->authorize('update', $rule);

        $data = $request->validated();
        $rule->update($data);

        // Clear cache for this tenant
        $this->assignmentService->clearRulesCache($tenantId);

        Log::info('Assignment rule updated', [
            'rule_id' => $rule->id,
            'tenant_id' => $tenantId,
            'updated_by' => $request->user()->id
        ]);

        return new AssignmentRuleResource($rule->load('creator'));
    }

    /**
     * Remove the specified assignment rule.
     */
    public function destroy(Request $request, int $id): JsonResponse
    {
        $tenantId = $request->user()->tenant_id ?? $request->user()->id;
        
        $rule = AssignmentRule::forTenant($tenantId)->findOrFail($id);
        $this->authorize('delete', $rule);

        $rule->delete();

        // Clear cache for this tenant
        $this->assignmentService->clearRulesCache($tenantId);

        Log::info('Assignment rule deleted', [
            'rule_id' => $id,
            'tenant_id' => $tenantId,
            'deleted_by' => $request->user()->id
        ]);

        return response()->json(['message' => 'Assignment rule deleted successfully']);
    }

    /**
     * Toggle the active status of an assignment rule.
     */
    public function toggle(Request $request, int $id): JsonResource
    {
        $tenantId = $request->user()->tenant_id ?? $request->user()->id;
        
        $rule = AssignmentRule::forTenant($tenantId)->findOrFail($id);
        $this->authorize('update', $rule);

        $rule->update(['active' => !$rule->active]);

        // Clear cache for this tenant
        $this->assignmentService->clearRulesCache($tenantId);

        Log::info('Assignment rule status toggled', [
            'rule_id' => $rule->id,
            'tenant_id' => $tenantId,
            'active' => $rule->active,
            'updated_by' => $request->user()->id
        ]);

        return new AssignmentRuleResource($rule->load('creator'));
    }

    /**
     * Get assignment statistics for rules.
     */
    public function stats(Request $request): JsonResponse
    {
        $this->authorize('viewAny', AssignmentRule::class);

        $tenantId = $request->user()->tenant_id ?? $request->user()->id;
        
        $filters = [
            'date_from' => $request->get('date_from'),
            'date_to' => $request->get('date_to'),
            'record_type' => $request->get('record_type'),
        ];

        $stats = $this->assignmentService->getAssignmentStats($tenantId, $filters);

        return response()->json([
            'data' => $stats,
            'meta' => [
                'tenant_id' => $tenantId,
                'filters' => array_filter($filters)
            ]
        ]);
    }

    /**
     * Get available operators for rule criteria.
     */
    public function operators(): JsonResponse
    {
        return response()->json([
            'data' => [
                'eq' => 'Equals',
                'ne' => 'Not equals',
                'in' => 'In list',
                'not_in' => 'Not in list',
                'contains' => 'Contains',
                'exists' => 'Exists',
                'not_exists' => 'Does not exist',
                'gt' => 'Greater than',
                'gte' => 'Greater than or equal',
                'lt' => 'Less than',
                'lte' => 'Less than or equal',
            ]
        ]);
    }

    /**
     * Get available fields for rule criteria.
     */
    public function fields(): JsonResponse
    {
        return response()->json([
            'data' => [
                'contact' => [
                    'first_name' => 'First Name',
                    'last_name' => 'Last Name',
                    'email' => 'Email',
                    'phone' => 'Phone',
                    'lifecycle_stage' => 'Lifecycle Stage',
                    'source' => 'Source',
                    'tags' => 'Tags',
                    'lead_score' => 'Lead Score',
                    'company_name' => 'Company Name',
                    'company_industry' => 'Company Industry',
                    'company_size' => 'Company Size',
                    'company_country' => 'Company Country',
                ],
                'deal' => [
                    'title' => 'Deal Title',
                    'value' => 'Deal Value',
                    'currency' => 'Currency',
                    'status' => 'Deal Status',
                    'probability' => 'Probability',
                    'pipeline_id' => 'Pipeline',
                    'stage_id' => 'Stage',
                    'tags' => 'Tags',
                    'contact_lifecycle_stage' => 'Contact Lifecycle Stage',
                    'contact_source' => 'Contact Source',
                    'contact_email' => 'Contact Email',
                    'company_name' => 'Company Name',
                    'company_industry' => 'Company Industry',
                    'company_size' => 'Company Size',
                    'company_country' => 'Company Country',
                ]
            ]
        ]);
    }
}
