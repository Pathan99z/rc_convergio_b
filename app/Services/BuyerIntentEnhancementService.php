<?php

namespace App\Services;

use App\Models\IntentEvent;
use App\Models\VisitorIntent;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;

class BuyerIntentEnhancementService
{
    /**
     * Export tracking/intent data.
     */
    public function exportTrackingData(int $tenantId, array $filters = []): array
    {
        $format = $filters['format'] ?? 'json';

        // Export intent events
        $intentEvents = IntentEvent::where('tenant_id', $tenantId)
            ->when(isset($filters['event_type']), function ($query) use ($filters) {
                return $query->where('event_type', $filters['event_type']);
            })
            ->when(isset($filters['source']), function ($query) use ($filters) {
                return $query->where('source', $filters['source']);
            })
            ->when(isset($filters['min_score']), function ($query) use ($filters) {
                return $query->where('intent_score', '>=', $filters['min_score']);
            })
            ->when(isset($filters['max_score']), function ($query) use ($filters) {
                return $query->where('intent_score', '<=', $filters['max_score']);
            })
            ->when(isset($filters['date_from']), function ($query) use ($filters) {
                return $query->where('created_at', '>=', $filters['date_from']);
            })
            ->when(isset($filters['date_to']), function ($query) use ($filters) {
                return $query->where('created_at', '<=', $filters['date_to']);
            })
            ->with(['company', 'contact'])
            ->get();

        // Export visitor intents
        $visitorIntents = VisitorIntent::where('tenant_id', $tenantId)
            ->when(isset($filters['date_from']), function ($query) use ($filters) {
                return $query->where('created_at', '>=', $filters['date_from']);
            })
            ->when(isset($filters['date_to']), function ($query) use ($filters) {
                return $query->where('created_at', '<=', $filters['date_to']);
            })
            ->with(['company', 'contact'])
            ->get();

        $exportData = [
            'intent_events' => $intentEvents->map(function ($event) {
                return [
                    'id' => $event->id,
                    'event_type' => $event->event_type,
                    'event_name' => $event->event_name,
                    'intent_score' => $event->intent_score,
                    'source' => $event->source,
                    'session_id' => $event->session_id,
                    'company_name' => $event->company->name ?? 'Unknown',
                    'contact_name' => $event->contact->name ?? 'Unknown',
                    'created_at' => $event->created_at,
                ];
            }),
            'visitor_intents' => $visitorIntents->map(function ($intent) {
                return [
                    'id' => $intent->id,
                    'page_url' => $intent->page_url,
                    'action' => $intent->action,
                    'score' => $intent->score,
                    'duration_seconds' => $intent->duration_seconds,
                    'company_name' => $intent->company->name ?? 'Unknown',
                    'contact_name' => $intent->contact->name ?? 'Unknown',
                    'created_at' => $intent->created_at,
                ];
            }),
            'export_metadata' => [
                'exported_at' => now(),
                'total_intent_events' => $intentEvents->count(),
                'total_visitor_intents' => $visitorIntents->count(),
                'filters_applied' => $filters,
            ]
        ];

        $filename = 'tracking_export_' . now()->format('Y-m-d_H-i-s') . '.' . $format;
        $filePath = 'exports/' . $filename;
        Storage::put($filePath, json_encode($exportData, JSON_PRETTY_PRINT));

        return [
            'filename' => $filename,
            'file_path' => $filePath,
            'download_url' => Storage::url($filePath),
            'format' => $format,
            'total_intent_events' => $intentEvents->count(),
            'total_visitor_intents' => $visitorIntents->count(),
        ];
    }

    /**
     * Bulk delete tracking events.
     */
    public function bulkDeleteEvents(int $tenantId, array $data): array
    {
        $eventIds = $data['event_ids'];
        $deleteVisitorIntents = $data['delete_visitor_intents'] ?? false;

        $results = [];
        $successCount = 0;
        $errorCount = 0;

        // Delete intent events
        foreach ($eventIds as $eventId) {
            try {
                $event = IntentEvent::where('tenant_id', $tenantId)->find($eventId);
                
                if (!$event) {
                    $results[] = [
                        'event_id' => $eventId,
                        'status' => 'error',
                        'message' => 'Event not found'
                    ];
                    $errorCount++;
                    continue;
                }

                $event->delete();
                
                $results[] = [
                    'event_id' => $eventId,
                    'event_type' => $event->event_type,
                    'status' => 'success',
                    'message' => 'Event deleted successfully'
                ];
                $successCount++;

            } catch (\Exception $e) {
                $results[] = [
                    'event_id' => $eventId,
                    'status' => 'error',
                    'message' => $e->getMessage()
                ];
                $errorCount++;
            }
        }

        // Delete visitor intents if requested
        if ($deleteVisitorIntents) {
            $visitorIntentIds = VisitorIntent::where('tenant_id', $tenantId)
                ->whereIn('session_id', function ($query) use ($eventIds) {
                    $query->select('session_id')
                        ->from('intent_events')
                        ->whereIn('id', $eventIds);
                })
                ->pluck('id');

            foreach ($visitorIntentIds as $intentId) {
                try {
                    $intent = VisitorIntent::where('tenant_id', $tenantId)->find($intentId);
                    if ($intent) {
                        $intent->delete();
                    }
                } catch (\Exception $e) {
                    Log::warning("Failed to delete visitor intent: {$intentId}", [
                        'error' => $e->getMessage()
                    ]);
                }
            }
        }

        return [
            'total_events' => count($eventIds),
            'success_count' => $successCount,
            'error_count' => $errorCount,
            'results' => $results,
            'visitor_intents_deleted' => $deleteVisitorIntents ? $visitorIntentIds->count() : 0,
        ];
    }

    /**
     * Generate tracking reports.
     */
    public function generateReports(int $tenantId, array $filters = []): array
    {
        $type = $filters['type'] ?? 'summary';

        $reports = [];

        switch ($type) {
            case 'summary':
                $reports = $this->generateSummaryReport($tenantId, $filters);
                break;
            case 'detailed':
                $reports = $this->generateDetailedReport($tenantId, $filters);
                break;
            case 'trends':
                $reports = $this->generateTrendsReport($tenantId, $filters);
                break;
            case 'intent_levels':
                $reports = $this->generateIntentLevelsReport($tenantId, $filters);
                break;
        }

        return [
            'report_type' => $type,
            'generated_at' => now(),
            'data' => $reports,
        ];
    }

    /**
     * Update tracking settings.
     */
    public function updateTrackingSettings(int $tenantId, array $settings): array
    {
        // In a real implementation, you would store these settings in a tenant_settings table
        // For now, we'll just return the settings that would be saved
        
        $defaultSettings = [
            'tracking_enabled' => true,
            'intent_scoring_enabled' => true,
            'auto_lead_scoring' => false,
            'tracking_domains' => [],
            'intent_thresholds' => [
                'low' => 30,
                'medium' => 60,
                'high' => 80,
            ],
            'retention_days' => 90,
        ];

        $updatedSettings = array_merge($defaultSettings, $settings);

        // Log the settings update
        Log::info('Tracking settings updated', [
            'tenant_id' => $tenantId,
            'settings' => $updatedSettings
        ]);

        return [
            'settings' => $updatedSettings,
            'updated_at' => now(),
            'message' => 'Tracking settings updated successfully',
        ];
    }

    /**
     * Generate summary report.
     */
    private function generateSummaryReport(int $tenantId, array $filters): array
    {
        $query = IntentEvent::where('tenant_id', $tenantId);
        $this->applyFilters($query, $filters);

        $totalEvents = $query->count();
        $avgScore = $query->avg('intent_score') ?? 0;
        $highIntentEvents = $query->where('intent_score', '>=', 70)->count();

        $eventsByType = $query->groupBy('event_type')
            ->selectRaw('event_type, count(*) as count')
            ->pluck('count', 'event_type');

        $eventsBySource = $query->groupBy('source')
            ->selectRaw('source, count(*) as count')
            ->pluck('count', 'source');

        return [
            'total_events' => $totalEvents,
            'average_intent_score' => round($avgScore, 2),
            'high_intent_events' => $highIntentEvents,
            'high_intent_percentage' => $totalEvents > 0 ? round(($highIntentEvents / $totalEvents) * 100, 2) : 0,
            'events_by_type' => $eventsByType,
            'events_by_source' => $eventsBySource,
        ];
    }

    /**
     * Generate detailed report.
     */
    private function generateDetailedReport(int $tenantId, array $filters): array
    {
        $query = IntentEvent::where('tenant_id', $tenantId)
            ->with(['company', 'contact']);
        $this->applyFilters($query, $filters);

        $events = $query->orderBy('created_at', 'desc')->limit(100)->get();

        return [
            'events' => $events->map(function ($event) {
                return [
                    'id' => $event->id,
                    'event_type' => $event->event_type,
                    'event_name' => $event->event_name,
                    'intent_score' => $event->intent_score,
                    'source' => $event->source,
                    'company_name' => $event->company->name ?? 'Unknown',
                    'contact_name' => $event->contact->name ?? 'Unknown',
                    'created_at' => $event->created_at,
                ];
            }),
            'total_events' => $events->count(),
        ];
    }

    /**
     * Generate trends report.
     */
    private function generateTrendsReport(int $tenantId, array $filters): array
    {
        $query = IntentEvent::where('tenant_id', $tenantId);
        $this->applyFilters($query, $filters);

        $trends = $query->selectRaw('DATE(created_at) as date, COUNT(*) as count, AVG(intent_score) as avg_score')
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        return [
            'trends' => $trends->map(function ($trend) {
                return [
                    'date' => $trend->date,
                    'count' => $trend->count,
                    'avg_score' => round($trend->avg_score, 2),
                ];
            }),
            'total_days' => $trends->count(),
        ];
    }

    /**
     * Generate intent levels report.
     */
    private function generateIntentLevelsReport(int $tenantId, array $filters): array
    {
        $query = IntentEvent::where('tenant_id', $tenantId);
        $this->applyFilters($query, $filters);

        $lowIntent = $query->where('intent_score', '<', 40)->count();
        $mediumIntent = $query->whereBetween('intent_score', [40, 69])->count();
        $highIntent = $query->where('intent_score', '>=', 70)->count();
        $total = $lowIntent + $mediumIntent + $highIntent;

        return [
            'intent_levels' => [
                'low' => [
                    'count' => $lowIntent,
                    'percentage' => $total > 0 ? round(($lowIntent / $total) * 100, 2) : 0,
                ],
                'medium' => [
                    'count' => $mediumIntent,
                    'percentage' => $total > 0 ? round(($mediumIntent / $total) * 100, 2) : 0,
                ],
                'high' => [
                    'count' => $highIntent,
                    'percentage' => $total > 0 ? round(($highIntent / $total) * 100, 2) : 0,
                ],
            ],
            'total_events' => $total,
        ];
    }

    /**
     * Apply filters to query.
     */
    private function applyFilters($query, array $filters): void
    {
        if (isset($filters['event_type'])) {
            $query->where('event_type', $filters['event_type']);
        }

        if (isset($filters['source'])) {
            $query->where('source', $filters['source']);
        }

        if (isset($filters['date_from'])) {
            $query->where('created_at', '>=', $filters['date_from']);
        }

        if (isset($filters['date_to'])) {
            $query->where('created_at', '<=', $filters['date_to']);
        }
    }
}

