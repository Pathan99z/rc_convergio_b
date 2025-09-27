<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ReportJob;
use App\Jobs\ProcessReportJob;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class AsyncReportController extends Controller
{
    /**
     * Create a new report job (async).
     */
    public function createReport(Request $request): JsonResponse
    {
        $user = Auth::user();
        $tenantId = $user->tenant_id;

        try {
            // Handle both old format (report_type/format/parameters) and new frontend format (type/filters)
            $isOldFormat = $request->has('report_type') && $request->has('format');
            $isNewFormat = $request->has('type') || $request->has('filters');

            if ($isOldFormat) {
                // Old format validation
                $request->validate([
                    'report_type' => 'required|in:analytics,intent_summary,contact_intent,company_intent',
                    'format' => 'required|in:pdf,csv,json',
                    'parameters' => 'nullable|array',
                    'parameters.date_from' => 'nullable|date',
                    'parameters.date_to' => 'nullable|date',
                    'parameters.min_score' => 'nullable|integer|min:0|max:100',
                    'parameters.contact_id' => 'nullable|integer',
                    'parameters.company_id' => 'nullable|integer',
                ]);

                $reportType = $request->report_type;
                $format = $request->format;
                $parameters = $request->parameters ?? [];
            } else {
                // New frontend format validation
                $request->validate([
                    'type' => 'required|in:summary,analytics,contact_intent,company_intent',
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
                $reportType = $request->type === 'summary' ? 'intent_summary' : $request->type;
                $format = 'pdf'; // Default to PDF for professional reports
                
                // For client demos, if PDF is requested for intent_summary, force CSV (professional format)
                if ($reportType === 'intent_summary' && $format === 'pdf') {
                    $format = 'csv'; // Generate CSV instead of PDF for professional client demos
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

            // Create report job record
            $jobId = 'report_' . Str::random(16);
            $reportJob = ReportJob::create([
                'job_id' => $jobId,
                'tenant_id' => $tenantId,
                'report_type' => $reportType,
                'format' => $format,
                'status' => 'pending',
                'parameters' => $parameters,
                'expires_at' => now()->addDays(30),
            ]);

            // Dispatch job
            ProcessReportJob::dispatch($reportJob);

            Log::info('Report job created', [
                'job_id' => $jobId,
                'report_type' => $reportType,
                'format' => $format,
                'tenant_id' => $tenantId,
                'format_used' => $isOldFormat ? 'old' : 'new'
            ]);

            return response()->json([
                'data' => [
                    'job_id' => $jobId,
                    'status' => 'pending',
                    'report_type' => $reportType,
                    'format' => $format,
                    'created_at' => $reportJob->created_at->toISOString(),
                    'expires_at' => $reportJob->expires_at->toISOString(),
                ],
                'message' => 'Report job created successfully'
            ], 202);

        } catch (\Exception $e) {
            Log::error('Failed to create report job', [
                'tenant_id' => $tenantId,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'message' => 'Failed to create report job',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get report job status.
     */
    public function getReportStatus(string $jobId): JsonResponse
    {
        $user = Auth::user();
        $tenantId = $user->tenant_id;

        try {
            $reportJob = ReportJob::where('job_id', $jobId)
                ->where('tenant_id', $tenantId)
                ->first();

            if (!$reportJob) {
                return response()->json([
                    'message' => 'Report job not found'
                ], 404);
            }

            $response = [
                'job_id' => $reportJob->job_id,
                'status' => $reportJob->status,
                'report_type' => $reportJob->report_type,
                'format' => $reportJob->format,
                'created_at' => $reportJob->created_at->toISOString(),
                'expires_at' => $reportJob->expires_at->toISOString(),
            ];

            if ($reportJob->isCompleted()) {
                // Generate download URL if not already set
                if (!$reportJob->download_url) {
                    try {
                        $reportJob->download_url = $reportJob->generateDownloadUrl();
                        $reportJob->save();
                    } catch (\Exception $e) {
                        Log::warning('Failed to generate download URL for completed report', [
                            'job_id' => $jobId,
                            'error' => $e->getMessage()
                        ]);
                    }
                }
                
                $response['download_url'] = $reportJob->download_url;
                $response['filename'] = $reportJob->filename;
                $response['file_size'] = $reportJob->file_size;
                $response['completed_at'] = $reportJob->completed_at->toISOString();
            } elseif ($reportJob->isFailed()) {
                $response['error_message'] = $reportJob->error_message;
                $response['completed_at'] = $reportJob->completed_at->toISOString();
            } elseif ($reportJob->isProcessing()) {
                $response['started_at'] = $reportJob->started_at->toISOString();
            }

            return response()->json([
                'data' => $response,
                'message' => 'Report status retrieved successfully'
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to get report status', [
                'job_id' => $jobId,
                'tenant_id' => $tenantId,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'message' => 'Failed to get report status',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * List report jobs for tenant.
     */
    public function listReports(Request $request): JsonResponse
    {
        $user = Auth::user();
        $tenantId = $user->tenant_id;

        try {
            $query = ReportJob::forTenant($tenantId)->notExpired();

            if ($request->has('status')) {
                $query->byStatus($request->status);
            }

            if ($request->has('report_type')) {
                $query->byReportType($request->report_type);
            }

            if ($request->has('format')) {
                $query->byFormat($request->format);
            }

            $reports = $query->orderBy('created_at', 'desc')
                ->paginate($request->get('per_page', 20));

            $reports->getCollection()->transform(function ($report) {
                return [
                    'job_id' => $report->job_id,
                    'status' => $report->status,
                    'report_type' => $report->report_type,
                    'format' => $report->format,
                    'created_at' => $report->created_at->toISOString(),
                    'expires_at' => $report->expires_at->toISOString(),
                    'download_url' => $report->download_url,
                    'filename' => $report->filename,
                    'file_size' => $report->file_size,
                ];
            });

            return response()->json([
                'data' => $reports,
                'message' => 'Report jobs retrieved successfully'
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to list reports', [
                'tenant_id' => $tenantId,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'message' => 'Failed to list report jobs',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Download report file.
     */
    public function downloadReport(string $jobId): \Symfony\Component\HttpFoundation\BinaryFileResponse
    {
        try {
            // Find report job by job_id only (no tenant validation needed since job_id is unique and secure)
            $reportJob = ReportJob::where('job_id', $jobId)->first();

            if (!$reportJob) {
                abort(404, 'Report job not found');
            }

            // Security check: ensure job is completed and not expired
            if (!$reportJob->isCompleted()) {
                abort(400, 'Report job is not completed');
            }

            if ($reportJob->isExpired()) {
                abort(410, 'Report job has expired');
            }

            if (!$reportJob->file_path || !file_exists($reportJob->file_path)) {
                abort(404, 'Report file not found');
            }

            $contentType = match($reportJob->format) {
                'pdf' => 'application/pdf',
                'csv' => 'text/csv',
                'json' => 'application/json',
                default => 'application/octet-stream'
            };

            // Log the download for auditing
            Log::info('Report file downloaded', [
                'job_id' => $jobId,
                'tenant_id' => $reportJob->tenant_id,
                'filename' => $reportJob->filename,
                'file_size' => $reportJob->file_size,
                'ip_address' => request()->ip(),
                'user_agent' => request()->userAgent()
            ]);

            return response()->download(
                $reportJob->file_path,
                $reportJob->filename,
                [
                    'Content-Type' => $contentType,
                    'Content-Disposition' => 'attachment; filename="' . $reportJob->filename . '"'
                ]
            );

        } catch (\Exception $e) {
            Log::error('Failed to download report', [
                'job_id' => $jobId,
                'error' => $e->getMessage()
            ]);

            abort(500, 'Failed to download report file');
        }
    }
}
