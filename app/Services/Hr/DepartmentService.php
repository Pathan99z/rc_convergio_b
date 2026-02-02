<?php

namespace App\Services\Hr;

use App\Constants\HrConstants;
use App\Models\Hr\Department;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class DepartmentService
{
    /**
     * Get paginated departments with filters.
     */
    public function getDepartments(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $currentUser = Auth::user();
        $tenantId = (int) ($currentUser->tenant_id ?? $currentUser->id);

        // Disable global scope temporarily to use explicit filter with proper type casting
        // This ensures consistent tenant filtering without type mismatch issues
        $query = Department::withoutGlobalScope('tenant')
            ->where('tenant_id', $tenantId)
            ->with('creator');

        // Filter by active status
        if (isset($filters['is_active'])) {
            // Ensure is_active is boolean (handle both string and boolean values)
            $isActive = is_bool($filters['is_active']) 
                ? $filters['is_active'] 
                : filter_var($filters['is_active'], FILTER_VALIDATE_BOOLEAN);
            $query->where('is_active', $isActive);
        } else {
            // Default: show only active departments
            $query->where('is_active', true);
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
     * Create a new department.
     */
    public function createDepartment(array $data): Department
    {
        $currentUser = Auth::user();
        $tenantId = $currentUser->tenant_id ?? $currentUser->id;

        DB::beginTransaction();
        try {
            // Check if department with same name exists in tenant
            if (Department::where('tenant_id', $tenantId)
                ->where('name', $data['name'])
                ->exists()) {
                throw new \Exception(HrConstants::ERROR_DEPARTMENT_ALREADY_EXISTS);
            }

            // Check if code is provided and unique
            if (!empty($data['code'])) {
                if (Department::where('tenant_id', $tenantId)
                    ->where('code', $data['code'])
                    ->exists()) {
                    throw new \Exception('Department code already exists');
                }
            }

            $department = Department::create([
                'tenant_id' => $tenantId,
                'name' => $data['name'],
                'code' => $data['code'] ?? null,
                'description' => $data['description'] ?? null,
                'is_active' => $data['is_active'] ?? true,
                'created_by' => $currentUser->id,
            ]);

            DB::commit();
            return $department->load('creator');
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to create department', [
                'error' => $e->getMessage(),
                'data' => $data,
            ]);
            throw $e;
        }
    }

    /**
     * Update a department.
     */
    public function updateDepartment(Department $department, array $data): Department
    {
        $currentUser = Auth::user();
        $tenantId = $currentUser->tenant_id ?? $currentUser->id;

        DB::beginTransaction();
        try {
            // Check if name is being changed and already exists
            if (isset($data['name']) && $data['name'] !== $department->name) {
                if (Department::where('tenant_id', $tenantId)
                    ->where('name', $data['name'])
                    ->where('id', '!=', $department->id)
                    ->exists()) {
                    throw new \Exception(HrConstants::ERROR_DEPARTMENT_ALREADY_EXISTS);
                }
            }

            // Check if code is being changed and already exists
            if (isset($data['code']) && $data['code'] !== $department->code) {
                if (!empty($data['code'])) {
                    if (Department::where('tenant_id', $tenantId)
                        ->where('code', $data['code'])
                        ->where('id', '!=', $department->id)
                        ->exists()) {
                        throw new \Exception('Department code already exists');
                    }
                }
            }

            $department->update($data);
            $department->refresh();

            DB::commit();
            return $department->load('creator');
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to update department', [
                'department_id' => $department->id,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Delete a department.
     */
    public function deleteDepartment(Department $department): bool
    {
        // Check if department has employees
        if ($department->employees()->count() > 0) {
            throw new \Exception('Cannot delete department with assigned employees');
        }

        return $department->delete();
    }
}

