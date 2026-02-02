<?php

namespace App\Services\Hr;

use App\Constants\HrConstants;
use App\Models\Hr\Designation;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class DesignationService
{
    /**
     * Get paginated designations with filters.
     */
    public function getDesignations(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $currentUser = Auth::user();
        $tenantId = (int) ($currentUser->tenant_id ?? $currentUser->id);

        // Disable global scope temporarily to use explicit filter with proper type casting
        // This ensures consistent tenant filtering without type mismatch issues
        $query = Designation::withoutGlobalScope('tenant')
            ->where('tenant_id', $tenantId)
            ->with(['creator', 'department']);

        // Filter by department if provided
        if (!empty($filters['department_id'])) {
            $query->where('department_id', $filters['department_id']);
        }

        // Filter by active status
        if (isset($filters['is_active'])) {
            // Ensure is_active is boolean (handle both string and boolean values)
            $isActive = is_bool($filters['is_active']) 
                ? $filters['is_active'] 
                : filter_var($filters['is_active'], FILTER_VALIDATE_BOOLEAN);
            $query->where('is_active', $isActive);
        } else {
            // Default: show only active designations
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
     * Create a new designation.
     */
    public function createDesignation(array $data): Designation
    {
        $currentUser = Auth::user();
        $tenantId = $currentUser->tenant_id ?? $currentUser->id;

        DB::beginTransaction();
        try {
            // Check if designation with same name exists in tenant
            if (Designation::where('tenant_id', $tenantId)
                ->where('name', $data['name'])
                ->exists()) {
                throw new \Exception(HrConstants::ERROR_DESIGNATION_ALREADY_EXISTS);
            }

            // Check if code is provided and unique
            if (!empty($data['code'])) {
                if (Designation::where('tenant_id', $tenantId)
                    ->where('code', $data['code'])
                    ->exists()) {
                    throw new \Exception('Designation code already exists');
                }
            }

            $designation = Designation::create([
                'tenant_id' => $tenantId,
                'name' => $data['name'],
                'code' => $data['code'] ?? null,
                'description' => $data['description'] ?? null,
                'department_id' => $data['department_id'] ?? null,
                'is_active' => $data['is_active'] ?? true,
                'is_manager' => $data['is_manager'] ?? false,
                'created_by' => $currentUser->id,
            ]);

            DB::commit();
            return $designation->load(['creator', 'department']);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to create designation', [
                'error' => $e->getMessage(),
                'data' => $data,
            ]);
            throw $e;
        }
    }

    /**
     * Update a designation.
     */
    public function updateDesignation(Designation $designation, array $data): Designation
    {
        $currentUser = Auth::user();
        $tenantId = $currentUser->tenant_id ?? $currentUser->id;

        DB::beginTransaction();
        try {
            // Check if name is being changed and already exists
            if (isset($data['name']) && $data['name'] !== $designation->name) {
                if (Designation::where('tenant_id', $tenantId)
                    ->where('name', $data['name'])
                    ->where('id', '!=', $designation->id)
                    ->exists()) {
                    throw new \Exception(HrConstants::ERROR_DESIGNATION_ALREADY_EXISTS);
                }
            }

            // Check if code is being changed and already exists
            if (isset($data['code']) && $data['code'] !== $designation->code) {
                if (!empty($data['code'])) {
                    if (Designation::where('tenant_id', $tenantId)
                        ->where('code', $data['code'])
                        ->where('id', '!=', $designation->id)
                        ->exists()) {
                        throw new \Exception('Designation code already exists');
                    }
                }
            }

            $designation->update($data);
            $designation->refresh();

            DB::commit();
            return $designation->load(['creator', 'department']);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to update designation', [
                'designation_id' => $designation->id,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Delete a designation.
     */
    public function deleteDesignation(Designation $designation): bool
    {
        // Check if designation has employees
        if ($designation->employees()->count() > 0) {
            throw new \Exception('Cannot delete designation with assigned employees');
        }

        return $designation->delete();
    }
}

