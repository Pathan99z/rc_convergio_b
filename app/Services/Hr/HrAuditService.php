<?php

namespace App\Services\Hr;

use App\Constants\HrConstants;
use App\Models\Hr\HrAuditLog;
use Illuminate\Support\Facades\Auth;

class HrAuditService
{
    /**
     * Log an HR audit event.
     */
    public function log(string $action, string $entity, ?int $entityId = null, array $oldValues = [], array $newValues = []): HrAuditLog
    {
        $user = Auth::user();
        
        return HrAuditLog::log(
            $action,
            $entity,
            $entityId,
            $oldValues,
            $newValues
        );
    }

    /**
     * Log employee creation.
     */
    public function logEmployeeCreated(int $employeeId, array $data): void
    {
        $this->log(
            HrConstants::AUDIT_EMPLOYEE_CREATED,
            'employee',
            $employeeId,
            [],
            $data
        );
    }

    /**
     * Log employee update.
     */
    public function logEmployeeUpdated(int $employeeId, array $oldValues, array $newValues): void
    {
        $this->log(
            HrConstants::AUDIT_EMPLOYEE_UPDATED,
            'employee',
            $employeeId,
            $oldValues,
            $newValues
        );
    }

    /**
     * Log employee archive.
     */
    public function logEmployeeArchived(int $employeeId, string $reason): void
    {
        $this->log(
            HrConstants::AUDIT_EMPLOYEE_ARCHIVED,
            'employee',
            $employeeId,
            [],
            ['reason' => $reason]
        );
    }

    /**
     * Log leave balance adjustment.
     */
    public function logLeaveBalanceAdjusted(int $employeeId, int $leaveTypeId, array $data): void
    {
        $this->log(
            HrConstants::AUDIT_LEAVE_BALANCE_ADJUSTED,
            'leave_balance',
            $employeeId,
            [],
            array_merge($data, ['leave_type_id' => $leaveTypeId])
        );
    }

    /**
     * Log payslip upload.
     */
    public function logPayslipUploaded(int $payslipId, int $employeeId): void
    {
        $this->log(
            HrConstants::AUDIT_PAYSLIP_UPLOADED,
            'payslip',
            $payslipId,
            [],
            ['employee_id' => $employeeId]
        );
    }

    /**
     * Log payslip download.
     */
    public function logPayslipDownloaded(int $payslipId, int $employeeId): void
    {
        $this->log(
            HrConstants::AUDIT_PAYSLIP_DOWNLOADED,
            'payslip',
            $payslipId,
            [],
            ['employee_id' => $employeeId]
        );
    }

    /**
     * Log document upload.
     */
    public function logDocumentUploaded(int $documentId, int $employeeId, string $category): void
    {
        $this->log(
            HrConstants::AUDIT_DOCUMENT_UPLOADED,
            'document',
            $documentId,
            [],
            ['employee_id' => $employeeId, 'category' => $category]
        );
    }
}

