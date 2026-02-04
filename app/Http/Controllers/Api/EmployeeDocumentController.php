<?php

namespace App\Http\Controllers\Api;

use App\Constants\HrConstants;
use App\Http\Controllers\Controller;
use App\Models\Hr\Employee;
use App\Models\Hr\EmployeeDocument;
use App\Services\DocumentService;
use App\Services\Hr\DocumentTypeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class EmployeeDocumentController extends Controller
{
    public function __construct(
        private DocumentService $documentService,
        private DocumentTypeService $documentTypeService
    ) {}

    /**
     * Get employee's own documents (employee self-service).
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        
        // Get employee record for current user
        $employee = Employee::where('user_id', $user->id)->firstOrFail();
        
        $category = $request->query('category');
        $documentTypeId = $request->query('document_type_id');
        
        $query = EmployeeDocument::query()
            ->where('employee_id', $employee->id)
            ->where('is_hr_only', false) // Employees can't see HR-only documents
            ->with(['document', 'documentType', 'creator', 'verifier', 'rejector']);

        // Filter by category if provided
        if ($category) {
            $query->where('category', $category);
        }

        // Filter by document type if provided
        if ($documentTypeId) {
            $query->where('document_type_id', $documentTypeId);
        }

        $perPage = min((int) $request->query('per_page', 15), 100);
        $documents = $query->orderBy('created_at', 'desc')->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $documents->items(),
            'meta' => [
                'current_page' => $documents->currentPage(),
                'per_page' => $documents->perPage(),
                'total' => $documents->total(),
                'last_page' => $documents->lastPage(),
            ],
        ]);
    }

    /**
     * Get document types that employee can upload.
     */
    public function getDocumentTypes(Request $request): JsonResponse
    {
        $user = $request->user();
        
        // Get employee record for current user
        $employee = Employee::where('user_id', $user->id)->firstOrFail();
        
        // Get applicable document types that employees can upload
        $documentTypes = $this->documentTypeService->getApplicableDocumentTypes($employee, true);

        return response()->json([
            'success' => true,
            'data' => $documentTypes->values(),
        ]);
    }

    /**
     * Get missing mandatory documents for employee.
     */
    public function getMissingMandatoryDocuments(Request $request): JsonResponse
    {
        $user = $request->user();
        
        // Get employee record for current user
        $employee = Employee::where('user_id', $user->id)->firstOrFail();
        
        // Get missing mandatory documents
        $missing = $this->documentTypeService->getMissingMandatoryDocuments($employee);

        return response()->json([
            'success' => true,
            'data' => $missing,
        ]);
    }

    /**
     * Upload document (employee self-service).
     */
    public function store(Request $request): JsonResponse
    {
        $user = $request->user();
        
        // Get employee record for current user
        $employee = Employee::where('user_id', $user->id)->firstOrFail();

        // Prepare request data for validation
        $requestData = $request->all();

        $validator = Validator::make($requestData, [
            'document_type_id' => 'required|integer|exists:hr_document_types,id',
            'file' => 'required|file|max:51200', // 50MB max
            'description' => 'nullable|string|max:1000',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $data = $validator->validated();

            // Validate document type
            $documentType = $this->documentTypeService->getDocumentType($data['document_type_id']);

            // Check if employee can upload this document type
            if (!$documentType->employee_can_upload) {
                return response()->json([
                    'success' => false,
                    'message' => 'You are not allowed to upload this type of document. Please contact HR.',
                ], 403);
            }

            // Check if document type is applicable to this employee
            if (!$documentType->isApplicableToEmployee($employee)) {
                return response()->json([
                    'success' => false,
                    'message' => 'This document type is not applicable to your role.',
                ], 403);
            }

            // Validate file type if specified
            if ($documentType->allowed_file_types && !empty($documentType->allowed_file_types)) {
                $fileExtension = strtolower($request->file('file')->getClientOriginalExtension());
                if (!in_array($fileExtension, $documentType->allowed_file_types)) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Invalid file type. Allowed types: ' . implode(', ', $documentType->allowed_file_types),
                    ], 422);
                }
            }

            // Validate file size
            $fileSizeMB = $request->file('file')->getSize() / 1024 / 1024;
            if ($fileSizeMB > $documentType->max_file_size_mb) {
                return response()->json([
                    'success' => false,
                    'message' => "File size exceeds maximum allowed size of {$documentType->max_file_size_mb}MB",
                ], 422);
            }

            // Upload document
            $document = $this->documentService->uploadDocument(
                $request->file('file'),
                [
                    'title' => $request->file('file')->getClientOriginalName(),
                    'description' => $data['description'] ?? null,
                    'visibility' => ($documentType->is_hr_only ?? false) ? 'private' : 'team',
                    'related_type' => 'App\\Models\\Hr\\Employee',
                    'related_id' => $employee->id,
                ]
            );

            // Create employee document link
            $employeeDocument = EmployeeDocument::create([
                'tenant_id' => $employee->tenant_id,
                'employee_id' => $employee->id,
                'document_id' => $document->id,
                'category' => $documentType->category,
                'document_type_id' => $documentType->id,
                'is_hr_only' => $documentType->is_hr_only ?? false,
                'verification_status' => HrConstants::DOC_VERIFICATION_STATUS_PENDING,
                'created_by' => $user->id,
            ]);

            return response()->json([
                'success' => true,
                'data' => [
                    'id' => $employeeDocument->id,
                    'document_id' => $document->id,
                    'document_type' => [
                        'id' => $documentType->id,
                        'name' => $documentType->name,
                        'code' => $documentType->code,
                    ],
                    'category' => $employeeDocument->category,
                    'title' => $document->title,
                    'file_type' => $document->file_type,
                    'file_size' => $document->file_size,
                    'verification_status' => $employeeDocument->verification_status,
                    'created_at' => $employeeDocument->created_at?->toISOString(),
                ],
                'message' => 'Document uploaded successfully. It will be reviewed by HR.',
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }
}
