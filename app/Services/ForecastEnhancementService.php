<?php

namespace App\Services;

use App\Models\Deal;
use App\Models\Stage;
use App\Services\ForecastService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;

class ForecastEnhancementService
{
    protected ForecastService $forecastService;

    public function __construct(ForecastService $forecastService)
    {
        $this->forecastService = $forecastService;
    }

    /**
     * Export forecast data.
     */
    public function exportForecast(int $tenantId, array $filters = []): array
    {
        $format = $filters['format'] ?? 'json';
        $timeframe = $filters['timeframe'] ?? 'monthly';
        $includeTrends = $filters['include_trends'] ?? false;
        $includePipelineBreakdown = $filters['include_pipeline_breakdown'] ?? false;

        // Generate forecast data
        $forecast = $this->forecastService->generateForecast($tenantId, $timeframe);

        // Add trends if requested
        if ($includeTrends) {
            $forecast['trends'] = $this->forecastService->getForecastTrends($tenantId, 6);
        }

        // Add pipeline breakdown if requested
        if ($includePipelineBreakdown) {
            $forecast['pipeline_breakdown'] = $this->forecastService->getForecastByPipeline($tenantId, $timeframe);
        }

        // Add deal details
        $deals = Deal::where('tenant_id', $tenantId)
            ->with(['stage', 'contact', 'company'])
            ->when(isset($filters['date_from']), function ($query) use ($filters) {
                return $query->where('created_at', '>=', $filters['date_from']);
            })
            ->when(isset($filters['date_to']), function ($query) use ($filters) {
                return $query->where('created_at', '<=', $filters['date_to']);
            })
            ->get();

        $exportData = [
            'forecast_summary' => $forecast,
            'deals' => $deals->map(function ($deal) {
                $probability = $deal->probability ?? ($deal->stage->probability ?? 50);
                return [
                    'id' => $deal->id,
                    'title' => $deal->title,
                    'value' => $deal->value,
                    'currency' => $deal->currency,
                    'status' => $deal->status,
                    'probability' => $probability,
                    'expected_close_date' => $deal->expected_close_date,
                    'stage_name' => $deal->stage->name ?? 'Unknown',
                    'contact_name' => $deal->contact->name ?? 'Unknown',
                    'company_name' => $deal->company->name ?? 'Unknown',
                    'created_at' => $deal->created_at,
                    'updated_at' => $deal->updated_at,
                ];
            }),
            'export_metadata' => [
                'exported_at' => now(),
                'timeframe' => $timeframe,
                'total_deals' => $deals->count(),
                'total_value' => $deals->sum('value'),
                'weighted_value' => $deals->sum(function ($deal) {
                    $probability = $deal->probability ?? ($deal->stage->probability ?? 50);
                    return $deal->value * ($probability / 100);
                }),
            ]
        ];

        $filename = 'forecast_export_' . now()->format('Y-m-d_H-i-s') . '.' . $format;
        $filePath = 'exports/' . $filename;
        Storage::put($filePath, json_encode($exportData, JSON_PRETTY_PRINT));

        return [
            'filename' => $filename,
            'file_path' => $filePath,
            'download_url' => Storage::url($filePath),
            'format' => $format,
            'total_deals' => $deals->count(),
            'forecast_data' => $forecast,
        ];
    }

    /**
     * Import forecast data.
     */
    public function importForecast(int $tenantId, UploadedFile $file): array
    {
        $results = [];
        $successCount = 0;
        $errorCount = 0;

        try {
            // Read file content
            $content = file_get_contents($file->getPathname());
            $data = json_decode($content, true);

            if (!$data || !isset($data['deals'])) {
                throw new \Exception('Invalid file format. Expected deals data.');
            }

            foreach ($data['deals'] as $row) {
                try {
                    DB::beginTransaction();

                    // Find or create stage
                    $stage = Stage::where('tenant_id', $tenantId)
                        ->where('name', $row['stage_name'] ?? 'Imported Stage')
                        ->first();

                    if (!$stage) {
                        $stage = Stage::create([
                            'name' => $row['stage_name'] ?? 'Imported Stage',
                            'pipeline_id' => 1, // Default pipeline
                            'tenant_id' => $tenantId,
                        ]);
                    }

                    $dealData = [
                        'title' => $row['title'] ?? 'Imported Deal',
                        'description' => $row['description'] ?? null,
                        'value' => $row['value'] ?? 0,
                        'currency' => $row['currency'] ?? 'USD',
                        'status' => $row['status'] ?? 'open',
                        'probability' => $row['probability'] ?? 50,
                        'expected_close_date' => $row['expected_close_date'] ?? null,
                        'pipeline_id' => $stage->pipeline_id,
                        'stage_id' => $stage->id,
                        'owner_id' => 1, // Default owner
                        'tenant_id' => $tenantId,
                    ];

                    Deal::create($dealData);
                    $successCount++;

                    DB::commit();

                } catch (\Exception $e) {
                    DB::rollBack();
                    $errorCount++;
                    $results[] = [
                        'row' => $row,
                        'error' => $e->getMessage()
                    ];
                }
            }

        } catch (\Exception $e) {
            throw new \Exception('Failed to process import file: ' . $e->getMessage());
        }

        return [
            'total_rows' => count($data['deals']),
            'success_count' => $successCount,
            'error_count' => $errorCount,
            'errors' => $results,
        ];
    }

    /**
     * Generate forecast reports.
     */
    public function generateReports(int $tenantId, array $filters = []): array
    {
        $type = $filters['type'] ?? 'summary';
        $timeframe = $filters['timeframe'] ?? 'monthly';

        $reports = [];

        switch ($type) {
            case 'summary':
                $reports = $this->generateSummaryReport($tenantId, $timeframe);
                break;
            case 'detailed':
                $reports = $this->generateDetailedReport($tenantId, $timeframe);
                break;
            case 'trends':
                $reports = $this->generateTrendsReport($tenantId);
                break;
            case 'accuracy':
                $reports = $this->generateAccuracyReport($tenantId);
                break;
        }

        return [
            'report_type' => $type,
            'timeframe' => $timeframe,
            'generated_at' => now(),
            'data' => $reports,
        ];
    }

    /**
     * Generate summary report.
     */
    private function generateSummaryReport(int $tenantId, string $timeframe): array
    {
        $forecast = $this->forecastService->generateForecast($tenantId, $timeframe);
        
        $deals = Deal::where('tenant_id', $tenantId)->get();
        
        return [
            'total_deals' => $deals->count(),
            'total_value' => $deals->sum('value'),
            'weighted_value' => $deals->sum(function ($deal) {
                return $deal->value * ($deal->probability / 100);
            }),
            'forecast' => $forecast,
            'deals_by_status' => $deals->groupBy('status')->map->count(),
            'deals_by_stage' => $deals->groupBy('stage_id')->map->count(),
        ];
    }

    /**
     * Generate detailed report.
     */
    private function generateDetailedReport(int $tenantId, string $timeframe): array
    {
        $deals = Deal::where('tenant_id', $tenantId)
            ->with(['stage', 'contact', 'company'])
            ->get();

        return [
            'deals' => $deals->map(function ($deal) {
                return [
                    'id' => $deal->id,
                    'title' => $deal->title,
                    'value' => $deal->value,
                    'probability' => $deal->probability,
                    'weighted_value' => $deal->value * ($deal->probability / 100),
                    'stage' => $deal->stage->name ?? 'Unknown',
                    'contact' => $deal->contact->name ?? 'Unknown',
                    'company' => $deal->company->name ?? 'Unknown',
                    'expected_close_date' => $deal->expected_close_date,
                ];
            }),
            'summary' => [
                'total_deals' => $deals->count(),
                'total_value' => $deals->sum('value'),
                'weighted_value' => $deals->sum(function ($deal) {
                    return $deal->value * ($deal->probability / 100);
                }),
            ]
        ];
    }

    /**
     * Generate trends report.
     */
    private function generateTrendsReport(int $tenantId): array
    {
        $trends = $this->forecastService->getForecastTrends($tenantId, 12);
        
        return [
            'trends' => $trends,
            'analysis' => [
                'trending_up' => $trends['trend'] === 'up',
                'trending_down' => $trends['trend'] === 'down',
                'trend_stable' => $trends['trend'] === 'stable',
                'growth_rate' => $trends['growth_rate'] ?? 0,
            ]
        ];
    }

    /**
     * Generate accuracy report.
     */
    private function generateAccuracyReport(int $tenantId): array
    {
        $accuracy = $this->forecastService->getForecastAccuracy($tenantId);
        
        return [
            'accuracy_metrics' => $accuracy,
            'performance' => [
                'excellent' => $accuracy['accuracy'] >= 90,
                'good' => $accuracy['accuracy'] >= 80,
                'fair' => $accuracy['accuracy'] >= 70,
                'poor' => $accuracy['accuracy'] < 70,
            ]
        ];
    }

    /**
     * Get forecast data for export.
     */
    public function getForecastData(int $tenantId, array $filters = []): array
    {
        $timeframe = $filters['timeframe'] ?? 'monthly';
        $includeTrends = $filters['include_trends'] ?? false;
        $includePipelineBreakdown = $filters['include_pipeline_breakdown'] ?? false;

        // Generate forecast data
        $forecast = $this->forecastService->generateForecast($tenantId, $timeframe);

        // Add trends if requested
        if ($includeTrends) {
            $forecast['trends'] = $this->forecastService->getForecastTrends($tenantId, 6);
        }

        // Add pipeline breakdown if requested
        if ($includePipelineBreakdown) {
            $forecast['pipeline_breakdown'] = $this->forecastService->getForecastByPipeline($tenantId, $timeframe);
        }

        return $forecast;
    }
}

