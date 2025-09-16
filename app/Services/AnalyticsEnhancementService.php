<?php

namespace App\Services;

use App\Models\AnalyticsReport;
use App\Services\AnalyticsService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;

class AnalyticsEnhancementService
{
    protected AnalyticsService $analyticsService;

    public function __construct(AnalyticsService $analyticsService)
    {
        $this->analyticsService = $analyticsService;
    }

    /**
     * Export analytics data.
     */
    public function exportAnalytics(int $tenantId, array $filters = []): array
    {
        $format = $filters['format'] ?? 'json';
        $modules = $filters['modules'] ?? ['contacts', 'deals', 'campaigns'];
        $period = $filters['period'] ?? 'month';

        $exportData = [];

        foreach ($modules as $module) {
            try {
                $moduleData = $this->analyticsService->getModuleAnalytics($tenantId, $module, ['period' => $period]);
                $exportData[$module] = $moduleData;
            } catch (\Exception $e) {
                Log::warning("Failed to export analytics for module: {$module}", [
                    'tenant_id' => $tenantId,
                    'error' => $e->getMessage()
                ]);
                $exportData[$module] = ['error' => $e->getMessage()];
            }
        }

        // Add summary data
        $exportData['summary'] = [
            'exported_at' => now(),
            'period' => $period,
            'modules' => $modules,
            'tenant_id' => $tenantId,
        ];

        $filename = 'analytics_export_' . now()->format('Y-m-d_H-i-s') . '.' . $format;
        $filePath = 'exports/' . $filename;
        Storage::put($filePath, json_encode($exportData, JSON_PRETTY_PRINT));

        return [
            'filename' => $filename,
            'file_path' => $filePath,
            'download_url' => Storage::url($filePath),
            'format' => $format,
            'modules' => $modules,
            'total_modules' => count($modules),
        ];
    }

    /**
     * Generate analytics reports.
     */
    public function generateReports(int $tenantId, array $filters = []): array
    {
        $type = $filters['type'] ?? 'summary';
        $modules = $filters['modules'] ?? ['contacts', 'deals', 'campaigns'];
        $period = $filters['period'] ?? 'month';

        $reports = [];

        switch ($type) {
            case 'summary':
                $reports = $this->generateSummaryReport($tenantId, $modules, $period);
                break;
            case 'detailed':
                $reports = $this->generateDetailedReport($tenantId, $modules, $period);
                break;
            case 'comparison':
                $reports = $this->generateComparisonReport($tenantId, $modules, $period);
                break;
            case 'trends':
                $reports = $this->generateTrendsReport($tenantId, $modules);
                break;
        }

        return [
            'report_type' => $type,
            'period' => $period,
            'modules' => $modules,
            'generated_at' => now(),
            'data' => $reports,
        ];
    }

    /**
     * Schedule a report.
     */
    public function scheduleReport(int $tenantId, array $reportData, int $userId): array
    {
        $report = AnalyticsReport::create([
            'name' => $reportData['name'],
            'description' => $reportData['description'],
            'type' => $reportData['type'],
            'config' => $reportData['config'],
            'schedule' => $reportData['schedule'] ?? null,
            'format' => $reportData['format'],
            'status' => 'active',
            'tenant_id' => $tenantId,
            'created_by' => $userId,
        ]);

        // Calculate next run time if scheduled
        if ($report->type === 'scheduled') {
            $report->calculateNextRun();
        }

        return [
            'report_id' => $report->id,
            'name' => $report->name,
            'type' => $report->type,
            'status' => $report->status,
            'next_run_at' => $report->next_run_at,
            'created_at' => $report->created_at,
        ];
    }

    /**
     * Get scheduled reports.
     */
    public function getScheduledReports(int $tenantId, array $filters = []): array
    {
        $query = AnalyticsReport::where('tenant_id', $tenantId);

        if (isset($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (isset($filters['type'])) {
            $query->where('type', $filters['type']);
        }

        $reports = $query->orderBy('created_at', 'desc')->get();

        return [
            'reports' => $reports->map(function ($report) {
                return [
                    'id' => $report->id,
                    'name' => $report->name,
                    'description' => $report->description,
                    'type' => $report->type,
                    'format' => $report->format,
                    'status' => $report->status,
                    'last_run_at' => $report->last_run_at,
                    'next_run_at' => $report->next_run_at,
                    'run_count' => $report->run_count,
                    'error_message' => $report->error_message,
                    'created_at' => $report->created_at,
                ];
            }),
            'total_reports' => $reports->count(),
        ];
    }

    /**
     * Delete scheduled report.
     */
    public function deleteScheduledReport(int $tenantId, int $reportId): array
    {
        $report = AnalyticsReport::where('tenant_id', $tenantId)
            ->findOrFail($reportId);

        $reportName = $report->name;
        $report->delete();

        return [
            'report_id' => $reportId,
            'name' => $reportName,
            'deleted_at' => now(),
            'message' => 'Report deleted successfully',
        ];
    }

    /**
     * Generate summary report.
     */
    private function generateSummaryReport(int $tenantId, array $modules, string $period): array
    {
        $summary = [];

        foreach ($modules as $module) {
            try {
                $data = $this->analyticsService->getModuleAnalytics($tenantId, $module, ['period' => $period]);
                $summary[$module] = [
                    'total' => is_array($data) ? ($data['total'] ?? 0) : 0,
                    'this_period' => is_array($data) ? ($data['this_period'] ?? 0) : 0,
                    'growth' => is_array($data) ? ($data['growth'] ?? 0) : 0,
                ];
            } catch (\Exception $e) {
                $summary[$module] = ['error' => $e->getMessage()];
            }
        }

        return [
            'summary' => $summary,
            'overall_growth' => array_sum(array_column($summary, 'growth')) / count($summary),
        ];
    }

    /**
     * Generate detailed report.
     */
    private function generateDetailedReport(int $tenantId, array $modules, string $period): array
    {
        $detailed = [];

        foreach ($modules as $module) {
            try {
                $data = $this->analyticsService->getModuleAnalytics($tenantId, $module, ['period' => $period]);
                $detailed[$module] = $data;
            } catch (\Exception $e) {
                $detailed[$module] = ['error' => $e->getMessage()];
            }
        }

        return $detailed;
    }

    /**
     * Generate comparison report.
     */
    private function generateComparisonReport(int $tenantId, array $modules, string $period): array
    {
        $comparison = [];

        foreach ($modules as $module) {
            try {
                $currentData = $this->analyticsService->getModuleAnalytics($tenantId, $module, ['period' => $period]);
                $previousData = $this->analyticsService->getModuleAnalytics($tenantId, $module, ['period' => $this->getPreviousPeriod($period)]);
                
                // Ensure data is array
                $currentData = is_array($currentData) ? $currentData : [];
                $previousData = is_array($previousData) ? $previousData : [];
                
                $comparison[$module] = [
                    'current' => $currentData,
                    'previous' => $previousData,
                    'change' => $this->calculateChange($currentData, $previousData),
                ];
            } catch (\Exception $e) {
                $comparison[$module] = ['error' => $e->getMessage()];
            }
        }

        return $comparison;
    }

    /**
     * Generate trends report.
     */
    private function generateTrendsReport(int $tenantId, array $modules): array
    {
        $trends = [];

        foreach ($modules as $module) {
            try {
                $trendData = [];
                $periods = ['week', 'month', 'quarter', 'year'];
                
                foreach ($periods as $period) {
                    $data = $this->analyticsService->getModuleAnalytics($tenantId, $module, ['period' => $period]);
                    $trendData[$period] = is_array($data) ? ($data['total'] ?? 0) : 0;
                }
                
                $trends[$module] = $trendData;
            } catch (\Exception $e) {
                $trends[$module] = ['error' => $e->getMessage()];
            }
        }

        return $trends;
    }

    /**
     * Get previous period for comparison.
     */
    private function getPreviousPeriod(string $period): string
    {
        return match ($period) {
            'week' => 'month',
            'month' => 'quarter',
            'quarter' => 'year',
            'year' => 'year',
            default => 'month',
        };
    }

    /**
     * Calculate change between two data sets.
     */
    private function calculateChange(array $current, array $previous): array
    {
        $change = [];
        
        foreach ($current as $key => $value) {
            if (isset($previous[$key]) && is_numeric($value) && is_numeric($previous[$key])) {
                $change[$key] = $previous[$key] > 0 
                    ? (($value - $previous[$key]) / $previous[$key]) * 100 
                    : 0;
            }
        }
        
        return $change;
    }
}
