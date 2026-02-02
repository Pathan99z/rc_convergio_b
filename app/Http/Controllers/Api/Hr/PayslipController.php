<?php

namespace App\Http\Controllers\Api\Hr;

use App\Constants\HrConstants;
use App\Http\Controllers\Controller;
use App\Http\Resources\Hr\PayslipResource;
use App\Models\Hr\Payslip;
use App\Services\Hr\PayslipService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\Facades\Validator;

class PayslipController extends Controller
{
    public function __construct(
        private PayslipService $payslipService
    ) {}

    /**
     * List payslips.
     */
    public function index(Request $request): JsonResponse
    {
        $filters = [
            'employee_id' => $request->query('employee_id'),
            'year' => $request->query('year'),
            'month' => $request->query('month'),
            'sortBy' => $request->query('sortBy', 'pay_period_start'),
            'sortOrder' => $request->query('sortOrder', 'desc'),
        ];

        $perPage = min((int) $request->query('per_page', 15), 100);
        $payslips = $this->payslipService->getPayslips($filters, $perPage);

        return response()->json([
            'success' => true,
            'data' => PayslipResource::collection($payslips->items()),
            'meta' => [
                'current_page' => $payslips->currentPage(),
                'last_page' => $payslips->lastPage(),
                'per_page' => $payslips->perPage(),
                'total' => $payslips->total(),
            ],
        ]);
    }

    /**
     * Upload payslip (HR Admin only).
     */
    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', Payslip::class);

        $validator = Validator::make($request->all(), [
            'employee_id' => 'required|exists:hr_employees,id',
            'pay_period_start' => 'required|date',
            'pay_period_end' => 'required|date|after_or_equal:pay_period_start',
            'file' => 'required|file|mimes:pdf|max:10240', // 10MB max
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
            $payslip = $this->payslipService->uploadPayslip(
                $data['employee_id'],
                $request->file('file'),
                $data
            );

            return response()->json([
                'success' => true,
                'data' => new PayslipResource($payslip),
                'message' => HrConstants::SUCCESS_PAYSLIP_UPLOADED,
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Get payslip details.
     */
    public function show(int $id): JsonResponse
    {
        $payslip = Payslip::with(['employee', 'document', 'uploadedBy'])->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => new PayslipResource($payslip),
        ]);
    }

    /**
     * Download payslip file.
     */
    public function download(int $id)
    {
        $payslip = Payslip::findOrFail($id);

        try {
            $filePath = $this->payslipService->downloadPayslip($payslip);
            $fileName = "payslip_{$payslip->payslip_number}.pdf";

            return Response::download($filePath, $fileName, [
                'Content-Type' => 'application/pdf',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Delete payslip (HR Admin only).
     */
    public function destroy(int $id): JsonResponse
    {
        $payslip = Payslip::findOrFail($id);
        $this->authorize('delete', $payslip);

        try {
            $this->payslipService->deletePayslip($payslip);

            return response()->json([
                'success' => true,
                'message' => 'Payslip deleted successfully',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Get employee payslips.
     */
    public function employeePayslips(int $employeeId, Request $request): JsonResponse
    {
        $filters = [
            'employee_id' => $employeeId,
            'year' => $request->query('year'),
            'month' => $request->query('month'),
        ];

        $perPage = min((int) $request->query('per_page', 15), 100);
        $payslips = $this->payslipService->getPayslips($filters, $perPage);

        return response()->json([
            'success' => true,
            'data' => PayslipResource::collection($payslips->items()),
            'meta' => [
                'current_page' => $payslips->currentPage(),
                'last_page' => $payslips->lastPage(),
                'per_page' => $payslips->perPage(),
                'total' => $payslips->total(),
            ],
        ]);
    }
}

