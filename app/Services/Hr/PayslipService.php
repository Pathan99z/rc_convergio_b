<?php

namespace App\Services\Hr;

use App\Constants\HrConstants;
use App\Models\Document;
use App\Models\Hr\Employee;
use App\Models\Hr\Payslip;
use Illuminate\Http\UploadedFile;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class PayslipService
{
    protected HrAuditService $auditService;

    public function __construct(HrAuditService $auditService)
    {
        $this->auditService = $auditService;
    }

    /**
     * Get payslips with filters.
     */
    public function getPayslips(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $currentUser = Auth::user();

        $query = Payslip::query()
            ->with(['employee', 'document', 'uploadedBy']);

        // Role-based filtering
        if ($currentUser->hasRole('employee')) {
            $query->whereHas('employee', function ($q) use ($currentUser) {
                $q->where('user_id', $currentUser->id);
            });
        } elseif ($currentUser->hasRole('line_manager')) {
            $query->whereHas('employee', function ($q) use ($currentUser) {
                $q->where('team_id', $currentUser->team_id);
            });
        }

        // Apply filters
        if (!empty($filters['employee_id'])) {
            $query->where('employee_id', $filters['employee_id']);
        }

        if (!empty($filters['year'])) {
            $query->whereYear('pay_period_start', $filters['year']);
        }

        if (!empty($filters['month'])) {
            $query->whereMonth('pay_period_start', $filters['month']);
        }

        // Sorting
        $sortBy = $filters['sortBy'] ?? 'pay_period_start';
        $sortOrder = $filters['sortOrder'] ?? 'desc';
        $query->orderBy($sortBy, $sortOrder);

        return $query->paginate($perPage);
    }

    /**
     * Upload a payslip.
     */
    public function uploadPayslip(int $employeeId, UploadedFile $file, array $data): Payslip
    {
        $currentUser = Auth::user();
        $tenantId = $currentUser->tenant_id ?? $currentUser->id;

        DB::beginTransaction();
        try {
            $employee = Employee::findOrFail($employeeId);

            // Generate payslip number
            $payslipNumber = $this->generatePayslipNumber($tenantId, $data['pay_period_start']);

            // Check if payslip for this period already exists
            $existing = Payslip::where('employee_id', $employeeId)
                ->where('pay_period_start', $data['pay_period_start'])
                ->where('pay_period_end', $data['pay_period_end'])
                ->first();

            if ($existing) {
                throw new \Exception(HrConstants::ERROR_PAYSLIP_ALREADY_EXISTS);
            }

            // Upload file
            $filename = $this->generateUniqueFilename($file, $employeeId);
            $filePath = "tenant_{$tenantId}/payslips/{$filename}";
            $storedPath = $file->storeAs("tenant_{$tenantId}/payslips", $filename, 'local');

            // Create document record
            $document = Document::create([
                'tenant_id' => $tenantId,
                'team_id' => $employee->team_id,
                'owner_id' => $employee->user_id,
                'title' => "Payslip - {$payslipNumber}",
                'description' => "Payslip for {$employee->full_name} - {$data['pay_period_start']} to {$data['pay_period_end']}",
                'file_path' => $storedPath,
                'file_type' => $file->getMimeType(),
                'file_size' => $file->getSize(),
                'visibility' => 'private',
                'created_by' => $currentUser->id,
            ]);

            // Create payslip record
            $payslip = Payslip::create([
                'tenant_id' => $tenantId,
                'employee_id' => $employeeId,
                'payslip_number' => $payslipNumber,
                'pay_period_start' => $data['pay_period_start'],
                'pay_period_end' => $data['pay_period_end'],
                'document_id' => $document->id,
                'uploaded_by' => $currentUser->id,
                'uploaded_at' => now(),
            ]);

            // Log audit
            $this->auditService->logPayslipUploaded($payslip->id, $employeeId);

            DB::commit();
            return $payslip->load(['employee', 'document', 'uploadedBy']);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to upload payslip', [
                'employee_id' => $employeeId,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Download payslip file.
     */
    public function downloadPayslip(Payslip $payslip): string
    {
        $currentUser = Auth::user();

        // Verify access
        if ($currentUser->hasRole('employee')) {
            $employee = $payslip->employee;
            if ($employee->user_id !== $currentUser->id) {
                throw new \Exception(HrConstants::ERROR_UNAUTHORIZED_ACCESS);
            }
        }

        $document = $payslip->document;
        if (!$document) {
            throw new \Exception(HrConstants::ERROR_DOCUMENT_NOT_FOUND);
        }

        // Log download
        $this->auditService->logPayslipDownloaded($payslip->id, $payslip->employee_id);

        return Storage::path($document->file_path);
    }

    /**
     * Delete payslip.
     */
    public function deletePayslip(Payslip $payslip): bool
    {
        DB::beginTransaction();
        try {
            $document = $payslip->document;

            // Delete file
            if ($document && Storage::exists($document->file_path)) {
                Storage::delete($document->file_path);
            }

            // Delete document record
            if ($document) {
                $document->delete();
            }

            // Delete payslip record
            $payslip->delete();

            // Log audit
            $this->auditService->log(
                HrConstants::AUDIT_PAYSLIP_DELETED,
                'payslip',
                $payslip->id,
                [],
                ['employee_id' => $payslip->employee_id]
            );

            DB::commit();
            return true;
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Generate payslip number: PAY-YYYY-MM-XXXX
     */
    protected function generatePayslipNumber(int $tenantId, string $payPeriodStart): string
    {
        $year = date('Y', strtotime($payPeriodStart));
        $month = date('m', strtotime($payPeriodStart));

        // Get last sequence for this tenant/year/month
        $lastPayslip = Payslip::where('tenant_id', $tenantId)
            ->whereYear('pay_period_start', $year)
            ->whereMonth('pay_period_start', $month)
            ->orderBy('payslip_number', 'desc')
            ->first();

        $sequence = $lastPayslip ? (int) substr($lastPayslip->payslip_number, -4) + 1 : 1;

        return sprintf('%s-%s-%s-%04d', HrConstants::PAYSLIP_PREFIX, $year, $month, $sequence);
    }

    /**
     * Generate unique filename for payslip.
     */
    protected function generateUniqueFilename(UploadedFile $file, int $employeeId): string
    {
        $extension = $file->getClientOriginalExtension();
        $timestamp = now()->format('YmdHis');
        return "payslip_{$employeeId}_{$timestamp}." . $extension;
    }
}

