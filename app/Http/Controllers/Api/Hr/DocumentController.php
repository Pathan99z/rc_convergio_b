<?php

namespace App\Http\Controllers\Api\Hr;

use App\Constants\HrConstants;
use App\Http\Controllers\Controller;
use App\Models\Document;
use App\Models\Hr\Employee;
use App\Models\Hr\EmployeeDocument;
use App\Models\Hr\OnboardingChecklist;
use App\Services\DocumentService;
use App\Services\Hr\HrAuditService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class DocumentController extends Controller
{
    public function __construct(
        private DocumentService $documentService,
        private HrAuditService $auditService
    ) {}

    /**
     * List employee documents.
     */
    public function index(int $employeeId, Request $request): JsonResponse
    {
        $employee = Employee::findOrFail($employeeId);

        $category = $request->query('category');
        $checklistId = $request->query('checklist_id');
        
        $query = EmployeeDocument::query()
            ->where('employee_id', $employeeId)
            ->with(['document', 'creator', 'verifier', 'rejector']);

        // Filter by checklist_id if provided (for onboarding sections)
        // This ensures each checklist section only shows its own documents
        if ($checklistId) {
            $checklist = OnboardingChecklist::where('id', $checklistId)
                ->where('employee_id', $employeeId)
                ->first();
            
            if ($checklist) {
                $metadata = $checklist->metadata ?? [];
                $documentIds = $metadata['document_ids'] ?? [];
                
                // Only return documents linked to this checklist
                if (!empty($documentIds)) {
                    $query->whereIn('document_id', $documentIds);
                } else {
                    // If checklist has no documents, return empty result
                    $query->whereRaw('1 = 0'); // Force empty result
                }
            } else {
                // Invalid checklist_id, return empty result
                $query->whereRaw('1 = 0'); // Force empty result
            }
        }

        // Only apply category filter if category is provided and not 'all'
        if ($category && $category !== 'all') {
            $query->where('category', $category);
        }

        $documents = $query->orderBy('created_at', 'desc')->get();

        return response()->json([
            'success' => true,
            'data' => $documents->map(function ($doc) use ($employeeId) {
                return [
                    'id' => $doc->id,
                    'document_id' => $doc->document_id,
                    'category' => $doc->category,
                    'is_hr_only' => $doc->is_hr_only,
                    'title' => $doc->document->title ?? null,
                    'file_type' => $doc->document->file_type ?? null,
                    'file_size' => $doc->document->file_size ?? null,
                    'download_url' => $doc->document ? url("/api/hr/employees/{$employeeId}/documents/{$doc->document_id}/download") : null,
                    'preview_url' => $doc->document ? url("/api/hr/employees/{$employeeId}/documents/{$doc->document_id}/preview") : null,
                    'verification_status' => $doc->verification_status ?? 'pending',
                    'rejection_reason' => $doc->rejection_reason,
                    'verified_by' => $doc->verifier ? [
                        'id' => $doc->verifier->id,
                        'name' => $doc->verifier->name,
                    ] : null,
                    'verified_at' => $doc->verified_at?->toISOString(),
                    'rejected_by' => $doc->rejector ? [
                        'id' => $doc->rejector->id,
                        'name' => $doc->rejector->name,
                    ] : null,
                    'rejected_at' => $doc->rejected_at?->toISOString(),
                    'created_at' => $doc->created_at?->toISOString(),
                    'created_by' => $doc->creator ? [
                        'id' => $doc->creator->id,
                        'name' => $doc->creator->name,
                    ] : null,
                ];
            }),
        ]);
    }

    /**
     * Upload employee document (HR Admin only).
     */
    public function store(int $employeeId, Request $request): JsonResponse
    {
        $this->authorize('create', Document::class);

        // Prepare request data for validation (convert string booleans to actual booleans)
        $requestData = $request->all();
        if (isset($requestData['is_hr_only'])) {
            $isHrOnly = $requestData['is_hr_only'];
            if (is_string($isHrOnly)) {
                $requestData['is_hr_only'] = filter_var($isHrOnly, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
            }
        }

        $validator = Validator::make($requestData, [
            'category' => 'required|in:' . implode(',', [
                HrConstants::DOC_CATEGORY_CONTRACT,
                HrConstants::DOC_CATEGORY_ID_DOCUMENT,
                HrConstants::DOC_CATEGORY_QUALIFICATION,
                HrConstants::DOC_CATEGORY_PERFORMANCE,
                HrConstants::DOC_CATEGORY_DISCIPLINARY,
                HrConstants::DOC_CATEGORY_PROFILE_PICTURE,
                HrConstants::DOC_CATEGORY_ONBOARDING,
                HrConstants::DOC_CATEGORY_OTHER,
            ]),
            'file' => 'required|file|max:51200', // 50MB max
            'description' => 'nullable|string|max:1000',
            'is_hr_only' => 'nullable|boolean',
            'checklist_id' => 'nullable|integer|exists:hr_onboarding_checklists,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $employee = Employee::findOrFail($employeeId);
            $data = $validator->validated();

            // Upload document
            $document = $this->documentService->uploadDocument(
                $request->file('file'),
                [
                    'title' => $request->file('file')->getClientOriginalName(),
                    'description' => $data['description'] ?? null,
                    'visibility' => ($data['is_hr_only'] ?? false) ? 'private' : 'team',
                    'related_type' => 'App\\Models\\Hr\\Employee',
                    'related_id' => $employeeId,
                ]
            );

            // Create employee document link
            $employeeDocument = EmployeeDocument::create([
                'tenant_id' => $employee->tenant_id,
                'employee_id' => $employeeId,
                'document_id' => $document->id,
                'category' => $data['category'],
                'is_hr_only' => $data['is_hr_only'] ?? false,
                'verification_status' => HrConstants::DOC_VERIFICATION_STATUS_PENDING,
                'created_by' => $request->user()->id,
            ]);

            // Link document to checklist if category is onboarding
            if ($data['category'] === HrConstants::DOC_CATEGORY_ONBOARDING) {
                $checklist = null;
                
                // If checklist_id is provided, use that specific checklist
                if ($request->has('checklist_id') && $request->checklist_id) {
                    $checklist = OnboardingChecklist::where('id', $request->checklist_id)
                        ->where('employee_id', $employeeId)
                        ->first();
                    
                    // Validate that the checklist belongs to this employee
                    if (!$checklist) {
                        return response()->json([
                            'success' => false,
                            'message' => 'Invalid checklist_id. The checklist does not belong to this employee.',
                        ], 422);
                    }
                }
                
                // Fallback: Find the first pending checklist item for this employee that needs documents
                if (!$checklist) {
                    $checklist = OnboardingChecklist::where('employee_id', $employeeId)
                        ->where('status', HrConstants::CHECKLIST_STATUS_PENDING)
                        ->whereHas('template', function($q) {
                            $q->where('category', HrConstants::CHECKLIST_CATEGORY_HR);
                        })
                        ->first();
                }
                
                if ($checklist) {
                    // Update checklist metadata to include document ID
                    $metadata = $checklist->metadata ?? [];
                    if (!isset($metadata['document_ids'])) {
                        $metadata['document_ids'] = [];
                    }
                    if (!in_array($document->id, $metadata['document_ids'])) {
                        $metadata['document_ids'][] = $document->id;
                        $checklist->update(['metadata' => $metadata]);
                    }
                }
            }

            // Log audit
            $this->auditService->logDocumentUploaded($document->id, $employeeId, $data['category']);

            return response()->json([
                'success' => true,
                'data' => [
                    'id' => $employeeDocument->id,
                    'document_id' => $document->id,
                    'category' => $employeeDocument->category,
                    'is_hr_only' => $employeeDocument->is_hr_only,
                    'title' => $document->title,
                    'file_type' => $document->file_type,
                    'file_size' => $document->file_size,
                    'download_url' => url("/api/hr/employees/{$employeeId}/documents/{$document->id}/download"),
                    'preview_url' => url("/api/hr/employees/{$employeeId}/documents/{$document->id}/preview"),
                    'verification_status' => $employeeDocument->verification_status,
                    'rejection_reason' => $employeeDocument->rejection_reason,
                    'created_at' => $employeeDocument->created_at?->toISOString(),
                    'document' => [
                        'id' => $document->id,
                        'title' => $document->title,
                        'file_type' => $document->file_type,
                        'file_size' => $document->file_size,
                        'file_path' => $document->file_path,
                    ],
                ],
                'message' => HrConstants::SUCCESS_DOCUMENT_UPLOADED,
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Download document.
     */
    public function download(int $employeeId, int $documentId)
    {
        $employee = EmployeeDocument::where('employee_id', $employeeId)
            ->where('document_id', $documentId)
            ->with('document')
            ->firstOrFail();

        $document = $employee->document;
        if (!$document) {
            return response()->json([
                'success' => false,
                'message' => HrConstants::ERROR_DOCUMENT_NOT_FOUND,
            ], 404);
        }

        // Check access based on category and visibility
        $user = request()->user();
        if ($employee->is_hr_only && !$user->hasRole('hr_admin') && !$user->hasRole('admin') && !$user->hasRole('system_admin')) {
            return response()->json([
                'success' => false,
                'message' => HrConstants::ERROR_UNAUTHORIZED_ACCESS,
            ], 403);
        }

        // Check if employee is trying to access their own documents
        $isOwnEmployee = false;
        if ($user->hasRole('employee')) {
            $employeeRecord = Employee::where('user_id', $user->id)->first();
            $isOwnEmployee = $employeeRecord && $employeeRecord->id === $employeeId;
        }

        // Allow employees to download their own onboarding and contract documents
        if ($user->hasRole('employee')) {
            if ($isOwnEmployee && 
                ($employee->category === HrConstants::DOC_CATEGORY_CONTRACT || 
                 $employee->category === HrConstants::DOC_CATEGORY_ONBOARDING)) {
                // Allow download - employee accessing their own onboarding/contract documents
            } else {
                return response()->json([
                    'success' => false,
                    'message' => HrConstants::ERROR_UNAUTHORIZED_ACCESS,
                ], 403);
            }
        }

        // Log download
        $this->auditService->log(
            HrConstants::AUDIT_DOCUMENT_DOWNLOADED,
            'document',
            $document->id,
            [],
            ['employee_id' => $employeeId, 'category' => $employee->category]
        );

        if (Storage::exists($document->file_path)) {
            return Response::download(Storage::path($document->file_path), $document->title);
        }

        return response()->json([
            'success' => false,
            'message' => HrConstants::ERROR_DOCUMENT_NOT_FOUND,
        ], 404);
    }

    /**
     * Preview document (inline viewing).
     */
    public function preview(int $employeeId, int $documentId)
    {
        $employee = EmployeeDocument::where('employee_id', $employeeId)
            ->where('document_id', $documentId)
            ->with('document')
            ->firstOrFail();

        $document = $employee->document;
        if (!$document) {
            return response()->json([
                'success' => false,
                'message' => HrConstants::ERROR_DOCUMENT_NOT_FOUND,
            ], 404);
        }

        // Check access based on category and visibility
        $user = request()->user();
        if ($employee->is_hr_only && !$user->hasRole('hr_admin') && !$user->hasRole('admin') && !$user->hasRole('system_admin')) {
            return response()->json([
                'success' => false,
                'message' => HrConstants::ERROR_UNAUTHORIZED_ACCESS,
            ], 403);
        }

        // Check if employee is trying to access their own documents
        $isOwnEmployee = false;
        if ($user->hasRole('employee')) {
            $employeeRecord = Employee::where('user_id', $user->id)->first();
            $isOwnEmployee = $employeeRecord && $employeeRecord->id === $employeeId;
        }

        // Allow employees to preview their own onboarding and contract documents
        if ($user->hasRole('employee')) {
            if ($isOwnEmployee && 
                ($employee->category === HrConstants::DOC_CATEGORY_CONTRACT || 
                 $employee->category === HrConstants::DOC_CATEGORY_ONBOARDING)) {
                // Allow preview - employee accessing their own onboarding/contract documents
            } else {
                return response()->json([
                    'success' => false,
                    'message' => HrConstants::ERROR_UNAUTHORIZED_ACCESS,
                ], 403);
            }
        }

        // Check if file exists
        if (!Storage::exists($document->file_path)) {
            return response()->json([
                'success' => false,
                'message' => HrConstants::ERROR_DOCUMENT_NOT_FOUND,
            ], 404);
        }

        // Get file content and MIME type
        $fileContent = Storage::get($document->file_path);
        $mimeType = Storage::mimeType($document->file_path);

        // For images and PDFs, return inline content for preview
        if (in_array($mimeType, ['image/jpeg', 'image/png', 'image/gif', 'image/webp', 'application/pdf'])) {
            return response($fileContent)
                ->header('Content-Type', $mimeType)
                ->header('Content-Disposition', 'inline; filename="' . $document->title . '"')
                ->header('Cache-Control', 'public, max-age=3600');
        }

        // For text files, return as plain text
        if (str_starts_with($mimeType, 'text/')) {
            return response($fileContent)
                ->header('Content-Type', 'text/plain; charset=utf-8')
                ->header('Content-Disposition', 'inline; filename="' . $document->title . '"');
        }

        // For other file types, return as download (can't preview inline)
        return Response::download(Storage::path($document->file_path), $document->title);
    }

    /**
     * Delete document (HR Admin only).
     */
    public function destroy(int $employeeId, int $documentId): JsonResponse
    {
        $this->authorize('delete', Document::class);

        $employeeDocument = EmployeeDocument::where('employee_id', $employeeId)
            ->where('document_id', $documentId)
            ->with('document')
            ->firstOrFail();

        try {
            $document = $employeeDocument->document;

            // Delete file
            if ($document && Storage::exists($document->file_path)) {
                Storage::delete($document->file_path);
            }

            // Delete document record
            if ($document) {
                $document->delete();
            }

            // Delete employee document link
            $employeeDocument->delete();

            // Log audit
            $this->auditService->log(
                HrConstants::AUDIT_DOCUMENT_DELETED,
                'document',
                $document->id ?? $documentId,
                [],
                ['employee_id' => $employeeId]
            );

            return response()->json([
                'success' => true,
                'message' => 'Document deleted successfully',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Verify a document (HR Admin only).
     */
    public function verify(int $employeeId, int $documentId, Request $request): JsonResponse
    {
        $this->authorize('update', Employee::findOrFail($employeeId));

        $employeeDocument = EmployeeDocument::where('employee_id', $employeeId)
            ->where('document_id', $documentId)
            ->with(['document', 'verifier'])
            ->firstOrFail();

        // Only HR admins can verify documents
        $user = $request->user();
        if (!$user->hasRole('hr_admin') && !$user->hasRole('admin') && !$user->hasRole('system_admin')) {
            return response()->json([
                'success' => false,
                'message' => HrConstants::ERROR_UNAUTHORIZED_ACCESS,
            ], 403);
        }

        // Update verification status
        $employeeDocument->update([
            'verification_status' => HrConstants::DOC_VERIFICATION_STATUS_VERIFIED,
            'verified_by' => $user->id,
            'verified_at' => now(),
            'rejected_by' => null,
            'rejected_at' => null,
            'rejection_reason' => null,
        ]);

        // Log audit
        $this->auditService->log(
            HrConstants::AUDIT_DOCUMENT_VERIFIED,
            'document',
            $employeeDocument->document_id,
            [],
            ['employee_id' => $employeeId, 'category' => $employeeDocument->category]
        );

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $employeeDocument->id,
                'document_id' => $employeeDocument->document_id,
                'verification_status' => $employeeDocument->verification_status,
                'verified_by' => $employeeDocument->verifier ? [
                    'id' => $employeeDocument->verifier->id,
                    'name' => $employeeDocument->verifier->name,
                ] : null,
                'verified_at' => $employeeDocument->verified_at?->toISOString(),
            ],
            'message' => HrConstants::SUCCESS_DOCUMENT_VERIFIED,
        ]);
    }

    /**
     * Reject a document (HR Admin only).
     */
    public function reject(int $employeeId, int $documentId, Request $request): JsonResponse
    {
        $this->authorize('update', Employee::findOrFail($employeeId));

        $validator = Validator::make($request->all(), [
            'rejection_reason' => 'required|string|max:1000',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $employeeDocument = EmployeeDocument::where('employee_id', $employeeId)
            ->where('document_id', $documentId)
            ->with(['document', 'rejector'])
            ->firstOrFail();

        // Only HR admins can reject documents
        $user = $request->user();
        if (!$user->hasRole('hr_admin') && !$user->hasRole('admin') && !$user->hasRole('system_admin')) {
            return response()->json([
                'success' => false,
                'message' => HrConstants::ERROR_UNAUTHORIZED_ACCESS,
            ], 403);
        }

        // Update verification status
        $employeeDocument->update([
            'verification_status' => HrConstants::DOC_VERIFICATION_STATUS_REJECTED,
            'rejection_reason' => $validator->validated()['rejection_reason'],
            'rejected_by' => $user->id,
            'rejected_at' => now(),
            'verified_by' => null,
            'verified_at' => null,
        ]);

        // Log audit
        $this->auditService->log(
            HrConstants::AUDIT_DOCUMENT_REJECTED,
            'document',
            $employeeDocument->document_id,
            [],
            [
                'employee_id' => $employeeId,
                'category' => $employeeDocument->category,
                'rejection_reason' => $employeeDocument->rejection_reason,
            ]
        );

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $employeeDocument->id,
                'document_id' => $employeeDocument->document_id,
                'verification_status' => $employeeDocument->verification_status,
                'rejection_reason' => $employeeDocument->rejection_reason,
                'rejected_by' => $employeeDocument->rejector ? [
                    'id' => $employeeDocument->rejector->id,
                    'name' => $employeeDocument->rejector->name,
                ] : null,
                'rejected_at' => $employeeDocument->rejected_at?->toISOString(),
            ],
            'message' => HrConstants::SUCCESS_DOCUMENT_REJECTED,
        ]);
    }
}

