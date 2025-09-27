<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ExportJob;
use App\Jobs\ProcessExportJob;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class AsyncExportController extends Controller
{
    /**
     * Create a new export job (async).
     */
    public function createExport(Request $request): JsonResponse
    {
        $user = Auth::user();
        $tenantId = $user->tenant_id;

        try {
            // Handle both old format (type/parameters) and new frontend format (format/filters)
            $isOldFormat = $request->has('type') && $request->has('parameters');
            $isNewFormat = $request->has('format') && $request->has('filters');

            if ($isOldFormat) {
                // Old format validation
                $request->validate([
                    'type' => 'required|in:csv,json',
                    'parameters' => 'nullable|array',
                    'parameters.date_from' => 'nullable|date',
                    'parameters.date_to' => 'nullable|date',
                    'parameters.min_score' => 'nullable|integer|min:0|max:100',
                    'parameters.action' => 'nullable|string',
                    'parameters.contact_id' => 'nullable|integer',
                    'parameters.company_id' => 'nullable|integer',
                ]);

                $exportType = $request->type;
                $parameters = $request->parameters ?? [];
            } else {
                // New frontend format validation
                $request->validate([
                    'format' => 'nullable|in:csv,json,excel',
                    'filters' => 'nullable|array',
                    'date_from' => 'nullable|string',
                    'date_to' => 'nullable|string',
                    'filters.min_score' => 'nullable|string',
                    'filters.max_score' => 'nullable|string',
                    'filters.action' => 'nullable|string',
                    'filters.contact_id' => 'nullable|string',
                    'filters.company_id' => 'nullable|string',
                    'filters.intent_level' => 'nullable|string',
                    'filters.page_url' => 'nullable|string',
                    'filters.campaign_id' => 'nullable|string',
                ]);

                // Convert frontend format to backend format
                // Force Excel format for professional client demos (even when JSON is requested)
                $requestedFormat = $request->format ?? 'excel';
                
                // For client demos, always use Excel format for professionalism
                if ($requestedFormat === 'json') {
                    $exportType = 'csv'; // Generate CSV instead of JSON for professional use
                } else {
                    $exportType = $requestedFormat === 'excel' ? 'csv' : $requestedFormat;
                }
                $filters = $request->filters ?? [];
                
                $parameters = [
                    'date_from' => $request->date_from ?: null,
                    'date_to' => $request->date_to ?: null,
                    'min_score' => !empty($filters['min_score']) ? (int)$filters['min_score'] : null,
                    'max_score' => !empty($filters['max_score']) ? (int)$filters['max_score'] : null,
                    'action' => !empty($filters['action']) ? $filters['action'] : null,
                    'contact_id' => !empty($filters['contact_id']) ? (int)$filters['contact_id'] : null,
                    'company_id' => !empty($filters['company_id']) ? (int)$filters['company_id'] : null,
                    'intent_level' => !empty($filters['intent_level']) ? $filters['intent_level'] : null,
                    'page_url' => !empty($filters['page_url']) ? $filters['page_url'] : null,
                    'campaign_id' => !empty($filters['campaign_id']) ? (int)$filters['campaign_id'] : null,
                ];

                // Remove null/empty values
                $parameters = array_filter($parameters, function($value) {
                    return $value !== null && $value !== '';
                });
            }

            // Create export job record
            $jobId = 'export_' . Str::random(16);
            $exportJob = ExportJob::create([
                'job_id' => $jobId,
                'tenant_id' => $tenantId,
                'type' => $exportType,
                'status' => 'pending',
                'parameters' => $parameters,
                'expires_at' => now()->addDays(7),
            ]);

            // Dispatch job
            ProcessExportJob::dispatch($exportJob);

            Log::info('Export job created', [
                'job_id' => $jobId,
                'type' => $exportType,
                'tenant_id' => $tenantId,
                'format_used' => $isOldFormat ? 'old' : 'new'
            ]);

            return response()->json([
                'data' => [
                    'job_id' => $jobId,
                    'status' => 'pending',
                    'type' => $exportType,
                    'created_at' => $exportJob->created_at->toISOString(),
                    'expires_at' => $exportJob->expires_at->toISOString(),
                    'download_url' => null, // Will be available when completed
                    'progress' => 0,
                ],
                'message' => 'Export job created successfully'
            ], 202);

        } catch (\Exception $e) {
            Log::error('Failed to create export job', [
                'tenant_id' => $tenantId,
                'error' => $e->getMessage(),
                'request_data' => $request->all()
            ]);

            return response()->json([
                'message' => 'Failed to create export job',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get export job status.
     */
    public function getExportStatus(string $jobId): JsonResponse
    {
        $user = Auth::user();
        $tenantId = $user->tenant_id;

        try {
            $exportJob = ExportJob::where('job_id', $jobId)
                ->where('tenant_id', $tenantId)
                ->first();

            if (!$exportJob) {
                return response()->json([
                    'message' => 'Export job not found'
                ], 404);
            }

            $response = [
                'job_id' => $exportJob->job_id,
                'status' => $exportJob->status,
                'type' => $exportJob->type,
                'created_at' => $exportJob->created_at->toISOString(),
                'expires_at' => $exportJob->expires_at->toISOString(),
            ];

            if ($exportJob->isCompleted()) {
                $response['download_url'] = $exportJob->download_url;
                $response['filename'] = $exportJob->filename;
                $response['file_size'] = $exportJob->file_size;
                $response['total_records'] = $exportJob->total_records;
                $response['completed_at'] = $exportJob->completed_at->toISOString();
            } elseif ($exportJob->isFailed()) {
                $response['error_message'] = $exportJob->error_message;
                $response['completed_at'] = $exportJob->completed_at->toISOString();
            } elseif ($exportJob->isProcessing()) {
                $response['progress_percentage'] = $exportJob->getProgressPercentage();
                $response['processed_records'] = $exportJob->processed_records;
                $response['total_records'] = $exportJob->total_records;
                $response['started_at'] = $exportJob->started_at->toISOString();
            }

            return response()->json([
                'data' => $response,
                'message' => 'Export status retrieved successfully'
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to get export status', [
                'job_id' => $jobId,
                'tenant_id' => $tenantId,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'message' => 'Failed to get export status',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * List export jobs for tenant.
     */
    public function listExports(Request $request): JsonResponse
    {
        $user = Auth::user();
        $tenantId = $user->tenant_id;

        try {
            $query = ExportJob::forTenant($tenantId)->notExpired();

            if ($request->has('status')) {
                $query->byStatus($request->status);
            }

            if ($request->has('type')) {
                $query->byType($request->type);
            }

            $exports = $query->orderBy('created_at', 'desc')
                ->paginate($request->get('per_page', 20));

            $exports->getCollection()->transform(function ($export) {
                return [
                    'job_id' => $export->job_id,
                    'status' => $export->status,
                    'type' => $export->type,
                    'created_at' => $export->created_at->toISOString(),
                    'expires_at' => $export->expires_at->toISOString(),
                    'download_url' => $export->download_url,
                    'filename' => $export->filename,
                    'file_size' => $export->file_size,
                    'total_records' => $export->total_records,
                ];
            });

            return response()->json([
                'data' => $exports,
                'message' => 'Export jobs retrieved successfully'
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to list exports', [
                'tenant_id' => $tenantId,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'message' => 'Failed to list export jobs',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Download export file (public endpoint with security validation).
     */
    public function downloadExport(string $jobId): \Symfony\Component\HttpFoundation\BinaryFileResponse
    {
        try {
            // Find export job by job_id only (no tenant validation needed since job_id is unique and secure)
            $exportJob = ExportJob::where('job_id', $jobId)->first();

            if (!$exportJob) {
                abort(404, 'Export job not found');
            }

            // Security check: ensure job is completed and not expired
            if (!$exportJob->isCompleted()) {
                abort(400, 'Export job is not completed');
            }

            if ($exportJob->isExpired()) {
                abort(410, 'Export job has expired');
            }

            if (!$exportJob->file_path || !file_exists($exportJob->file_path)) {
                abort(404, 'Export file not found');
            }

            // Log the download for auditing
            Log::info('Export file downloaded', [
                'job_id' => $jobId,
                'tenant_id' => $exportJob->tenant_id,
                'filename' => $exportJob->filename,
                'file_size' => $exportJob->file_size,
                'ip_address' => request()->ip(),
                'user_agent' => request()->userAgent()
            ]);

            return response()->download(
                $exportJob->file_path,
                $exportJob->filename,
                [
                    'Content-Type' => $exportJob->type === 'csv' ? 'text/csv' : 'application/json',
                    'Content-Disposition' => 'attachment; filename="' . $exportJob->filename . '"'
                ]
            );

        } catch (\Exception $e) {
            Log::error('Failed to download export', [
                'job_id' => $jobId,
                'error' => $e->getMessage()
            ]);

            abort(500, 'Failed to download export file');
        }
    }
}
