<?php

namespace App\Services\Hr;

use App\Constants\HrConstants;
use App\Models\Hr\DocumentType;
use App\Models\Hr\Employee;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class DocumentTypeService
{
    /**
     * Get paginated document types with filters.
     */
    public function getDocumentTypes(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $currentUser = Auth::user();
        $tenantId = (int) ($currentUser->tenant_id ?? $currentUser->id);

        $query = DocumentType::withoutGlobalScope('tenant')
            ->where('tenant_id', $tenantId)
            ->with('creator');

        // Filter by category if provided
        if (!empty($filters['category'])) {
            $query->where('category', $filters['category']);
        }

        // Filter by active status
        if (isset($filters['is_active'])) {
            $isActive = is_bool($filters['is_active']) 
                ? $filters['is_active'] 
                : filter_var($filters['is_active'], FILTER_VALIDATE_BOOLEAN);
            $query->where('is_active', $isActive);
        } else {
            // Default: show only active document types
            $query->where('is_active', true);
        }

        // Filter by mandatory
        if (isset($filters['is_mandatory'])) {
            $isMandatory = is_bool($filters['is_mandatory']) 
                ? $filters['is_mandatory'] 
                : filter_var($filters['is_mandatory'], FILTER_VALIDATE_BOOLEAN);
            $query->where('is_mandatory', $isMandatory);
        }

        // Filter by employee uploadable
        if (isset($filters['employee_can_upload'])) {
            $canUpload = is_bool($filters['employee_can_upload']) 
                ? $filters['employee_can_upload'] 
                : filter_var($filters['employee_can_upload'], FILTER_VALIDATE_BOOLEAN);
            $query->where('employee_can_upload', $canUpload);
        }

        // Search filter
        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('code', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%");
            });
        }

        // Sorting
        $sortBy = $filters['sortBy'] ?? 'name';
        $sortOrder = $filters['sortOrder'] ?? 'asc';
        $query->orderBy($sortBy, $sortOrder);

        return $query->paginate($perPage);
    }

    /**
     * Get a single document type by ID.
     */
    public function getDocumentType(int $id): DocumentType
    {
        $currentUser = Auth::user();
        $tenantId = $currentUser->tenant_id ?? $currentUser->id;

        $documentType = DocumentType::withoutGlobalScope('tenant')
            ->where('tenant_id', $tenantId)
            ->with('creator')
            ->findOrFail($id);

        return $documentType;
    }

    /**
     * Create a new document type.
     */
    public function createDocumentType(array $data): DocumentType
    {
        $currentUser = Auth::user();
        $tenantId = $currentUser->tenant_id ?? $currentUser->id;

        DB::beginTransaction();
        try {
            // Check if code is provided and unique
            if (!empty($data['code'])) {
                if (DocumentType::where('tenant_id', $tenantId)
                    ->where('code', $data['code'])
                    ->exists()) {
                    throw new \Exception('Document type code already exists');
                }
            }

            $documentType = DocumentType::create([
                'tenant_id' => $tenantId,
                'name' => $data['name'],
                'code' => $data['code'] ?? null,
                'description' => $data['description'] ?? null,
                'category' => $data['category'] ?? 'other',
                'is_mandatory' => $data['is_mandatory'] ?? false,
                'employee_can_upload' => $data['employee_can_upload'] ?? true,
                'is_hr_only' => $data['is_hr_only'] ?? false,
                'allowed_file_types' => $data['allowed_file_types'] ?? null,
                'max_file_size_mb' => $data['max_file_size_mb'] ?? 10,
                'target_departments' => $data['target_departments'] ?? null,
                'target_designations' => $data['target_designations'] ?? null,
                'is_active' => $data['is_active'] ?? true,
                'created_by' => $currentUser->id,
            ]);

            DB::commit();
            return $documentType->load('creator');
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to create document type', [
                'error' => $e->getMessage(),
                'data' => $data,
            ]);
            throw $e;
        }
    }

    /**
     * Update a document type.
     */
    public function updateDocumentType(DocumentType $documentType, array $data): DocumentType
    {
        $currentUser = Auth::user();
        $tenantId = $currentUser->tenant_id ?? $currentUser->id;

        // Ensure document type belongs to tenant
        if ($documentType->tenant_id != $tenantId) {
            throw new \Exception(HrConstants::ERROR_UNAUTHORIZED_ACCESS);
        }

        DB::beginTransaction();
        try {
            // Check if code is being updated and is unique
            if (isset($data['code']) && $data['code'] !== $documentType->code) {
                if (DocumentType::where('tenant_id', $tenantId)
                    ->where('code', $data['code'])
                    ->where('id', '!=', $documentType->id)
                    ->exists()) {
                    throw new \Exception('Document type code already exists');
                }
            }

            $documentType->update($data);
            $documentType->refresh();

            DB::commit();
            return $documentType->load('creator');
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to update document type', [
                'document_type_id' => $documentType->id,
                'error' => $e->getMessage(),
                'data' => $data,
            ]);
            throw $e;
        }
    }

    /**
     * Delete a document type (soft delete).
     */
    public function deleteDocumentType(DocumentType $documentType): bool
    {
        $currentUser = Auth::user();
        $tenantId = $currentUser->tenant_id ?? $currentUser->id;

        // Ensure document type belongs to tenant
        if ($documentType->tenant_id != $tenantId) {
            throw new \Exception(HrConstants::ERROR_UNAUTHORIZED_ACCESS);
        }

        DB::beginTransaction();
        try {
            // Check if document type is in use
            $inUse = $documentType->employeeDocuments()->exists() || 
                     $documentType->payslips()->exists();

            if ($inUse) {
                // Soft delete instead of hard delete
                $documentType->delete();
            } else {
                // Hard delete if not in use
                $documentType->forceDelete();
            }

            DB::commit();
            return true;
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to delete document type', [
                'document_type_id' => $documentType->id,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Get document types applicable to an employee.
     */
    public function getApplicableDocumentTypes(Employee $employee, bool $employeeView = false): \Illuminate\Database\Eloquent\Collection
    {
        $currentUser = Auth::user();
        $tenantId = $currentUser->tenant_id ?? $currentUser->id;

        $query = DocumentType::withoutGlobalScope('tenant')
            ->where('tenant_id', $tenantId)
            ->where('is_active', true);

        // If employee view, only show types they can upload
        if ($employeeView) {
            $query->where('employee_can_upload', true);
        }

        $documentTypes = $query->get();

        // Filter by department and designation applicability
        return $documentTypes->filter(function ($documentType) use ($employee) {
            return $documentType->isApplicableToEmployee($employee);
        });
    }

    /**
     * Get mandatory document types for an employee.
     */
    public function getMandatoryDocumentTypes(Employee $employee): \Illuminate\Database\Eloquent\Collection
    {
        return $this->getApplicableDocumentTypes($employee)
            ->where('is_mandatory', true);
    }

    /**
     * Check which mandatory documents are missing for an employee.
     */
    public function getMissingMandatoryDocuments(Employee $employee): array
    {
        $mandatoryTypes = $this->getMandatoryDocumentTypes($employee);
        $missing = [];

        foreach ($mandatoryTypes as $documentType) {
            // Check if employee has at least one verified document of this type
            $hasDocument = $employee->documents()
                ->where('document_type_id', $documentType->id)
                ->where('verification_status', 'verified')
                ->exists();

            if (!$hasDocument) {
                $missing[] = [
                    'document_type_id' => $documentType->id,
                    'name' => $documentType->name,
                    'code' => $documentType->code,
                    'category' => $documentType->category,
                ];
            }
        }

        return $missing;
    }
}

