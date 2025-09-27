<?php

namespace App\Jobs;

use App\Models\ReportJob;
use App\Models\IntentEvent;
use App\Services\AnalyticsRollupService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessReportJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $reportJob;

    public function __construct(ReportJob $reportJob)
    {
        $this->reportJob = $reportJob;
    }

    public function handle(): void
    {
        try {
            $this->reportJob->markAsStarted();

            Log::info('Starting report job', [
                'job_id' => $this->reportJob->job_id,
                'report_type' => $this->reportJob->report_type,
                'format' => $this->reportJob->format,
                'tenant_id' => $this->reportJob->tenant_id
            ]);

            $filename = $this->generateFilename();
            $filePath = $this->getFilePath($filename);

            $this->generateReport($filePath);
            $this->reportJob->markAsCompleted($filePath);

            Log::info('Report job completed successfully', [
                'job_id' => $this->reportJob->job_id,
                'file_path' => $filePath
            ]);

        } catch (\Exception $e) {
            Log::error('Report job failed', [
                'job_id' => $this->reportJob->job_id,
                'error' => $e->getMessage()
            ]);

            $this->reportJob->markAsFailed($e->getMessage());
        }
    }

    private function generateReport(string $filePath): void
    {
        $reportType = $this->reportJob->report_type;
        $format = $this->reportJob->format;

        switch ($reportType) {
            case 'analytics':
                $this->generateAnalyticsReport($filePath, $format);
                break;
            case 'intent_summary':
                $this->generateIntentSummaryReport($filePath, $format);
                break;
            default:
                throw new \Exception("Unsupported report type: {$reportType}");
        }
    }

    private function generateAnalyticsReport(string $filePath, string $format): void
    {
        $rollupService = new AnalyticsRollupService();
        $analyticsData = $rollupService->getAnalyticsData($this->reportJob->tenant_id, $this->reportJob->parameters);

        if ($format === 'json') {
            file_put_contents($filePath, json_encode($analyticsData, JSON_PRETTY_PRINT));
        } else {
            throw new \Exception("Unsupported format for analytics report: {$format}");
        }
    }

    private function generateIntentSummaryReport(string $filePath, string $format): void
    {
        $parameters = $this->reportJob->parameters;
        $tenantId = $this->reportJob->tenant_id;

        $query = IntentEvent::where('tenant_id', $tenantId);
        
        if (isset($parameters['date_from'])) {
            $query->where('created_at', '>=', $parameters['date_from']);
        }
        
        if (isset($parameters['date_to'])) {
            $query->where('created_at', '<=', $parameters['date_to']);
        }

        $events = $query->get();

        $summaryData = [
            'total_events' => $events->count(),
            'average_score' => $events->avg('intent_score'),
            'high_intent_events' => $events->where('intent_score', '>=', 60)->count(),
            'action_breakdown' => $events->groupBy('event_name')->map->count(),
            'score_distribution' => [
                'very_high' => $events->where('intent_score', '>=', 80)->count(),
                'high' => $events->where('intent_score', '>=', 60)->where('intent_score', '<', 80)->count(),
                'medium' => $events->where('intent_score', '>=', 40)->where('intent_score', '<', 60)->count(),
                'low' => $events->where('intent_score', '>=', 20)->where('intent_score', '<', 40)->count(),
                'very_low' => $events->where('intent_score', '<', 20)->count(),
            ],
        ];

        if ($format === 'json') {
            $result = file_put_contents($filePath, json_encode($summaryData, JSON_PRETTY_PRINT));
            if ($result === false) {
                throw new \Exception("Failed to write JSON file to: {$filePath}");
            }
        } elseif ($format === 'csv') {
            // Generate professional CSV format for client demos
            $this->generateCSVReport($filePath, $summaryData, $events);
        } else {
            throw new \Exception("Unsupported format for intent summary report: {$format}");
        }

        // Verify file was created successfully
        if (!file_exists($filePath)) {
            throw new \Exception("Report file was not created at: {$filePath}");
        }
    }

    private function generateCSVReport(string $filePath, array $summaryData, $events): void
    {
        $handle = fopen($filePath, 'w');
        if (!$handle) {
            throw new \Exception("Failed to create CSV file at: {$filePath}");
        }

        try {
            // Write CSV header with professional business-friendly column names
            $headers = [
                'Report Type', 'Total Events', 'Average Score', 'High Intent Events',
                'Very High Intent', 'High Intent', 'Medium Intent', 'Low Intent', 'Very Low Intent',
                'Generated At', 'Generated By'
            ];
            fputcsv($handle, $headers);

            // Write summary data
            $row = [
                'Intent Summary Report',
                $summaryData['total_events'],
                round($summaryData['average_score'], 2),
                $summaryData['high_intent_events'],
                $summaryData['score_distribution']['very_high'],
                $summaryData['score_distribution']['high'],
                $summaryData['score_distribution']['medium'],
                $summaryData['score_distribution']['low'],
                $summaryData['score_distribution']['very_low'],
                now()->format('M d, Y h:i A'),
                'RC Convergio CRM'
            ];
            fputcsv($handle, $row);

            // Add action breakdown section
            fputcsv($handle, []); // Empty row
            fputcsv($handle, ['Action Breakdown']);
            fputcsv($handle, ['Action Type', 'Event Count']);
            
            foreach ($summaryData['action_breakdown'] as $action => $count) {
                fputcsv($handle, [$this->formatActionName($action), $count]);
            }

        } finally {
            fclose($handle);
        }
    }

    private function formatActionName(string $action): string
    {
        $actionMap = [
            'page_view' => 'Page View',
            'form_submit' => 'Form Submission',
            'download' => 'File Download',
            'pricing_view' => 'Pricing Page View',
            'contact_view' => 'Contact Page View',
            'demo_request' => 'Demo Request',
            'trial_signup' => 'Trial Signup',
        ];

        return $actionMap[$action] ?? ucfirst(str_replace('_', ' ', $action));
    }

    private function generateFilename(): string
    {
        $timestamp = now()->format('Y-m-d_H-i-s');
        $reportType = $this->reportJob->report_type;
        $format = $this->reportJob->format;
        $tenantId = $this->reportJob->tenant_id;
        
        return "intent_report_{$reportType}_tenant_{$tenantId}_{$timestamp}.{$format}";
    }

    private function getFilePath(string $filename): string
    {
        $directory = 'reports';
        $fullPath = storage_path("app/{$directory}/{$filename}");
        
        $dir = dirname($fullPath);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        
        return $fullPath;
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('Report job failed permanently', [
            'job_id' => $this->reportJob->job_id,
            'error' => $exception->getMessage()
        ]);

        $this->reportJob->markAsFailed($exception->getMessage());
    }
}