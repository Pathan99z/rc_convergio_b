<?php

namespace App\Http\Controllers\Api\Hr;

use App\Constants\HrConstants;
use App\Http\Controllers\Controller;
use App\Models\Hr\DocumentType;
use App\Services\Hr\DocumentTypeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class DocumentTypeController extends Controller
{
    protected DocumentTypeService $documentTypeService;

    public function __construct(DocumentTypeService $documentTypeService)
    {
        $this->documentTypeService = $documentTypeService;
    }

    /**
     * Display a listing of document types.
     */
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', DocumentType::class);

        $filters = $request->only(['search', 'category', 'sortBy', 'sortOrder']);
        
        // Convert string booleans to actual booleans
        if ($request->filled('is_active')) {
            $filters['is_active'] = $request->boolean('is_active');
        }
        if ($request->filled('is_mandatory')) {
            $filters['is_mandatory'] = $request->boolean('is_mandatory');
        }
        if ($request->filled('employee_can_upload')) {
            $filters['employee_can_upload'] = $request->boolean('employee_can_upload');
        }
        
        $perPage = min((int) $request->get('per_page', 15), 100);

        $documentTypes = $this->documentTypeService->getDocumentTypes($filters, $perPage);

        return response()->json([
            'success' => true,
            'data' => $documentTypes->items(),
            'meta' => [
                'current_page' => $documentTypes->currentPage(),
                'per_page' => $documentTypes->perPage(),
                'total' => $documentTypes->total(),
                'last_page' => $documentTypes->lastPage(),
            ],
        ]);
    }

    /**
     * Store a newly created document type.
     */
    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', DocumentType::class);

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'code' => 'nullable|string|max:50',
            'description' => 'nullable|string',
            'category' => 'required|in:' . implode(',', [
                'contract',
                'id_document',
                'qualification',
                'performance',
                'disciplinary',
                'onboarding',
                'profile_picture',
                'payslip',
                'other'
            ]),
            'is_mandatory' => 'nullable|boolean',
            'employee_can_upload' => 'nullable|boolean',
            'is_hr_only' => 'nullable|boolean',
            'allowed_file_types' => 'nullable|array',
            'allowed_file_types.*' => 'string|max:10',
            'max_file_size_mb' => 'nullable|integer|min:1|max:100',
            'target_departments' => 'nullable|array',
            'target_departments.*' => 'integer|exists:hr_departments,id',
            'target_designations' => 'nullable|array',
            'target_designations.*' => 'integer|exists:hr_designations,id',
            'is_active' => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $documentType = $this->documentTypeService->createDocumentType($validator->validated());

            return response()->json([
                'success' => true,
                'data' => $documentType,
                'message' => 'Document type created successfully',
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Display the specified document type.
     */
    public function show(int $id): JsonResponse
    {
        $documentType = $this->documentTypeService->getDocumentType($id);
        $this->authorize('view', $documentType);

        return response()->json([
            'success' => true,
            'data' => $documentType,
        ]);
    }

    /**
     * Update the specified document type.
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $documentType = $this->documentTypeService->getDocumentType($id);
        $this->authorize('update', $documentType);

        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|required|string|max:255',
            'code' => 'nullable|string|max:50',
            'description' => 'nullable|string',
            'category' => 'sometimes|required|in:' . implode(',', [
                'contract',
                'id_document',
                'qualification',
                'performance',
                'disciplinary',
                'onboarding',
                'profile_picture',
                'payslip',
                'other'
            ]),
            'is_mandatory' => 'nullable|boolean',
            'employee_can_upload' => 'nullable|boolean',
            'is_hr_only' => 'nullable|boolean',
            'allowed_file_types' => 'nullable|array',
            'allowed_file_types.*' => 'string|max:10',
            'max_file_size_mb' => 'nullable|integer|min:1|max:100',
            'target_departments' => 'nullable|array',
            'target_departments.*' => 'integer|exists:hr_departments,id',
            'target_designations' => 'nullable|array',
            'target_designations.*' => 'integer|exists:hr_designations,id',
            'is_active' => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $documentType = $this->documentTypeService->updateDocumentType($documentType, $validator->validated());

            return response()->json([
                'success' => true,
                'data' => $documentType,
                'message' => 'Document type updated successfully',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Remove the specified document type.
     */
    public function destroy(int $id): JsonResponse
    {
        $documentType = $this->documentTypeService->getDocumentType($id);
        $this->authorize('delete', $documentType);

        try {
            $this->documentTypeService->deleteDocumentType($documentType);

            return response()->json([
                'success' => true,
                'message' => 'Document type deleted successfully',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }
}
