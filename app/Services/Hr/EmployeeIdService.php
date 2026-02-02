<?php

namespace App\Services\Hr;

use App\Constants\HrConstants;
use App\Models\Hr\EmployeeIdSequence;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class EmployeeIdService
{
    /**
     * Generate a unique employee ID for a tenant.
     * Format: EMP-YYYY-XXXX
     */
    public function generate(int $tenantId): string
    {
        try {
            $year = (int) date('Y');
            
            DB::beginTransaction();
            
            // Get next sequence number (with lock to prevent duplicates)
            $sequence = EmployeeIdSequence::getNextSequence($tenantId, $year);
            
            // Format: EMP-2026-0001
            $employeeId = sprintf(
                '%s-%d-%04d',
                HrConstants::EMPLOYEE_ID_PREFIX,
                $year,
                $sequence
            );
            
            DB::commit();
            
            return $employeeId;
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to generate employee ID', [
                'tenant_id' => $tenantId,
                'error' => $e->getMessage(),
            ]);
            throw new \RuntimeException(HrConstants::ERROR_EMPLOYEE_ID_GENERATION_FAILED);
        }
    }
}

