<?php

namespace App\Services;

use App\Models\Journey;
use App\Models\JourneyStep;
use App\Models\JourneyExecution;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;

class JourneysEnhancementService
{
    /**
     * Bulk delete journeys.
     */
    public function bulkDeleteJourneys(int $tenantId, array $journeyIds): array
    {
        $results = [];
        $successCount = 0;
        $errorCount = 0;

        foreach ($journeyIds as $journeyId) {
            try {
                $journey = Journey::where('tenant_id', $tenantId)->find($journeyId);
                
                if (!$journey) {
                    $results[] = [
                        'journey_id' => $journeyId,
                        'status' => 'error',
                        'message' => 'Journey not found'
                    ];
                    $errorCount++;
                    continue;
                }

                // Check if journey has active executions
                $activeExecutions = JourneyExecution::where('journey_id', $journeyId)
                    ->whereIn('status', ['running', 'paused'])
                    ->count();

                if ($activeExecutions > 0) {
                    $results[] = [
                        'journey_id' => $journeyId,
                        'journey_name' => $journey->name,
                        'status' => 'error',
                        'message' => 'Cannot delete journey with active executions'
                    ];
                    $errorCount++;
                    continue;
                }

                // Delete journey steps first
                JourneyStep::where('journey_id', $journeyId)->delete();
                
                // Delete journey executions
                JourneyExecution::where('journey_id', $journeyId)->delete();
                
                // Delete the journey
                $journey->delete();
                
                $results[] = [
                    'journey_id' => $journeyId,
                    'journey_name' => $journey->name,
                    'status' => 'success',
                    'message' => 'Journey deleted successfully'
                ];
                $successCount++;

            } catch (\Exception $e) {
                $results[] = [
                    'journey_id' => $journeyId,
                    'status' => 'error',
                    'message' => $e->getMessage()
                ];
                $errorCount++;
            }
        }

        return [
            'total_journeys' => count($journeyIds),
            'success_count' => $successCount,
            'error_count' => $errorCount,
            'results' => $results,
        ];
    }

    /**
     * Bulk activate journeys.
     */
    public function bulkActivateJourneys(int $tenantId, array $journeyIds): array
    {
        $results = [];
        $successCount = 0;
        $errorCount = 0;

        foreach ($journeyIds as $journeyId) {
            try {
                $journey = Journey::where('tenant_id', $tenantId)->find($journeyId);
                
                if (!$journey) {
                    $results[] = [
                        'journey_id' => $journeyId,
                        'status' => 'error',
                        'message' => 'Journey not found'
                    ];
                    $errorCount++;
                    continue;
                }

                $journey->update([
                    'status' => 'active',
                    'is_active' => true,
                ]);
                
                $results[] = [
                    'journey_id' => $journeyId,
                    'journey_name' => $journey->name,
                    'status' => 'success',
                    'message' => 'Journey activated successfully'
                ];
                $successCount++;

            } catch (\Exception $e) {
                $results[] = [
                    'journey_id' => $journeyId,
                    'status' => 'error',
                    'message' => $e->getMessage()
                ];
                $errorCount++;
            }
        }

        return [
            'total_journeys' => count($journeyIds),
            'success_count' => $successCount,
            'error_count' => $errorCount,
            'results' => $results,
        ];
    }

    /**
     * Bulk pause journeys.
     */
    public function bulkPauseJourneys(int $tenantId, array $journeyIds): array
    {
        $results = [];
        $successCount = 0;
        $errorCount = 0;

        foreach ($journeyIds as $journeyId) {
            try {
                $journey = Journey::where('tenant_id', $tenantId)->find($journeyId);
                
                if (!$journey) {
                    $results[] = [
                        'journey_id' => $journeyId,
                        'status' => 'error',
                        'message' => 'Journey not found'
                    ];
                    $errorCount++;
                    continue;
                }

                $journey->update([
                    'status' => 'paused',
                    'is_active' => false,
                ]);

                // Pause all running executions
                JourneyExecution::where('journey_id', $journeyId)
                    ->where('status', 'running')
                    ->update(['status' => 'paused']);
                
                $results[] = [
                    'journey_id' => $journeyId,
                    'journey_name' => $journey->name,
                    'status' => 'success',
                    'message' => 'Journey paused successfully'
                ];
                $successCount++;

            } catch (\Exception $e) {
                $results[] = [
                    'journey_id' => $journeyId,
                    'status' => 'error',
                    'message' => $e->getMessage()
                ];
                $errorCount++;
            }
        }

        return [
            'total_journeys' => count($journeyIds),
            'success_count' => $successCount,
            'error_count' => $errorCount,
            'results' => $results,
        ];
    }

    /**
     * Export journeys.
     */
    public function exportJourneys(int $tenantId, array $filters = []): array
    {
        $query = Journey::where('tenant_id', $tenantId)
            ->with(['steps' => function ($query) {
                $query->orderBy('order_no');
            }]);

        // Apply filters
        if (isset($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (isset($filters['date_from'])) {
            $query->where('created_at', '>=', $filters['date_from']);
        }

        if (isset($filters['date_to'])) {
            $query->where('created_at', '<=', $filters['date_to']);
        }

        $journeys = $query->orderBy('created_at', 'desc')->get();

        $format = $filters['format'] ?? 'json';
        $filename = 'journeys_export_' . now()->format('Y-m-d_H-i-s') . '.' . $format;

        // Generate export data
        $exportData = $journeys->map(function ($journey) {
            return [
                'id' => $journey->id,
                'name' => $journey->name,
                'description' => $journey->description,
                'status' => $journey->status,
                'settings' => $journey->settings,
                'is_active' => $journey->is_active,
                'created_at' => $journey->created_at,
                'updated_at' => $journey->updated_at,
                'steps' => $journey->steps->map(function ($step) {
                    return [
                        'id' => $step->id,
                        'step_type' => $step->step_type,
                        'config' => $step->config,
                        'conditions' => $step->conditions,
                        'order_no' => $step->order_no,
                    ];
                }),
            ];
        });

        // Store file temporarily
        $filePath = 'exports/' . $filename;
        Storage::put($filePath, json_encode($exportData, JSON_PRETTY_PRINT));

        return [
            'filename' => $filename,
            'file_path' => $filePath,
            'download_url' => Storage::url($filePath),
            'total_journeys' => $journeys->count(),
            'format' => $format,
        ];
    }

    /**
     * Import journeys.
     */
    public function importJourneys(int $tenantId, UploadedFile $file, int $userId): array
    {
        $results = [];
        $successCount = 0;
        $errorCount = 0;

        try {
            // Read file content
            $content = file_get_contents($file->getPathname());
            $data = json_decode($content, true);

            if (!$data) {
                throw new \Exception('Invalid file format');
            }

            foreach ($data as $row) {
                try {
                    DB::beginTransaction();

                    $journeyData = [
                        'name' => $row['name'] ?? 'Imported Journey',
                        'description' => $row['description'] ?? null,
                        'status' => $row['status'] ?? 'draft',
                        'settings' => $row['settings'] ?? null,
                        'is_active' => $row['is_active'] ?? false,
                        'tenant_id' => $tenantId,
                        'created_by' => $userId,
                    ];

                    $journey = Journey::create($journeyData);

                    // Import steps if they exist
                    if (isset($row['steps']) && is_array($row['steps'])) {
                        foreach ($row['steps'] as $stepData) {
                            JourneyStep::create([
                                'journey_id' => $journey->id,
                                'step_type' => $stepData['step_type'] ?? 'wait',
                                'config' => $stepData['config'] ?? [],
                                'conditions' => $stepData['conditions'] ?? [],
                                'order_no' => $stepData['order_no'] ?? 1,
                            ]);
                        }
                    }

                    DB::commit();
                    $successCount++;

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
            'total_rows' => count($data),
            'success_count' => $successCount,
            'error_count' => $errorCount,
            'errors' => $results,
        ];
    }

    /**
     * Export single journey.
     */
    public function exportSingleJourney(int $tenantId, int $journeyId): array
    {
        $journey = Journey::where('tenant_id', $tenantId)
            ->with(['steps' => function ($query) {
                $query->orderBy('order_no');
            }])
            ->findOrFail($journeyId);
        
        $filename = 'journey_' . $journey->id . '_export_' . now()->format('Y-m-d_H-i-s') . '.json';

        $exportData = [
            'id' => $journey->id,
            'name' => $journey->name,
            'description' => $journey->description,
            'status' => $journey->status,
            'settings' => $journey->settings,
            'is_active' => $journey->is_active,
            'created_at' => $journey->created_at,
            'updated_at' => $journey->updated_at,
            'steps' => $journey->steps->map(function ($step) {
                return [
                    'id' => $step->id,
                    'step_type' => $step->step_type,
                    'config' => $step->config,
                    'conditions' => $step->conditions,
                    'order_no' => $step->order_no,
                ];
            }),
            'executions' => JourneyExecution::where('journey_id', $journey->id)
                ->with('contact')
                ->get()
                ->map(function ($execution) {
                    return [
                        'id' => $execution->id,
                        'contact_id' => $execution->contact_id,
                        'contact_name' => $execution->contact->name ?? 'Unknown',
                        'status' => $execution->status,
                        'current_step' => $execution->current_step,
                        'started_at' => $execution->started_at,
                        'completed_at' => $execution->completed_at,
                    ];
                })
        ];

        $filePath = 'exports/' . $filename;
        Storage::put($filePath, json_encode($exportData, JSON_PRETTY_PRINT));

        return [
            'filename' => $filename,
            'file_path' => $filePath,
            'download_url' => Storage::url($filePath),
            'journey_name' => $journey->name,
            'total_steps' => $journey->steps->count(),
            'total_executions' => JourneyExecution::where('journey_id', $journey->id)->count(),
        ];
    }

    /**
     * Pause single journey.
     */
    public function pauseJourney(int $tenantId, int $journeyId): array
    {
        $journey = Journey::where('tenant_id', $tenantId)->findOrFail($journeyId);
        
        $journey->update([
            'status' => 'paused',
            'is_active' => false,
        ]);

        // Pause all running executions
        $pausedExecutions = JourneyExecution::where('journey_id', $journeyId)
            ->where('status', 'running')
            ->update(['status' => 'paused']);

        return [
            'journey_id' => $journey->id,
            'status' => 'paused',
            'paused_executions' => $pausedExecutions,
            'message' => 'Journey paused successfully',
        ];
    }

    /**
     * Resume single journey.
     */
    public function resumeJourney(int $tenantId, int $journeyId): array
    {
        $journey = Journey::where('tenant_id', $tenantId)->findOrFail($journeyId);
        
        $journey->update([
            'status' => 'active',
            'is_active' => true,
        ]);

        // Resume all paused executions
        $resumedExecutions = JourneyExecution::where('journey_id', $journeyId)
            ->where('status', 'paused')
            ->update(['status' => 'running']);

        return [
            'journey_id' => $journey->id,
            'status' => 'active',
            'resumed_executions' => $resumedExecutions,
            'message' => 'Journey resumed successfully',
        ];
    }
}

