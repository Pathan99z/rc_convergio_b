<?php

namespace App\Services;

use App\Models\LeadScoringRule;
use App\Models\Contact;
use App\Services\LeadScoringService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;

class LeadScoringEnhancementService
{
    protected LeadScoringService $leadScoringService;

    public function __construct(LeadScoringService $leadScoringService)
    {
        $this->leadScoringService = $leadScoringService;
    }

    /**
     * Bulk recalculate lead scores.
     */
    public function bulkRecalculateScores(int $tenantId, ?array $contactIds = null, ?array $ruleIds = null): array
    {
        $results = [];
        $successCount = 0;
        $errorCount = 0;

        try {
            // If specific contacts are provided, recalculate for those
            if ($contactIds) {
                $contacts = Contact::where('tenant_id', $tenantId)
                    ->whereIn('id', $contactIds)
                    ->get();
            } else {
                // Recalculate for all contacts
                $contacts = Contact::where('tenant_id', $tenantId)->get();
            }

            // If specific rules are provided, use only those
            $rules = null;
            if ($ruleIds) {
                $rules = LeadScoringRule::where('tenant_id', $tenantId)
                    ->whereIn('id', $ruleIds)
                    ->where('is_active', true)
                    ->get();
            }

            foreach ($contacts as $contact) {
                try {
                    $oldScore = $contact->lead_score ?? 0;
                    $result = $this->leadScoringService->recalculateContactScore($contact->id, $tenantId);
                    $newScore = $result['lead_score'];
                    
                    // Refresh contact to get updated score
                    $contact->refresh();

                    $results[] = [
                        'contact_id' => $contact->id,
                        'contact_name' => $contact->name,
                        'old_score' => $oldScore,
                        'new_score' => $newScore,
                        'status' => 'success',
                        'message' => 'Score recalculated successfully'
                    ];
                    $successCount++;

                } catch (\Exception $e) {
                    $results[] = [
                        'contact_id' => $contact->id,
                        'contact_name' => $contact->name ?? 'Unknown',
                        'status' => 'error',
                        'message' => $e->getMessage()
                    ];
                    $errorCount++;
                }
            }

        } catch (\Exception $e) {
            throw new \Exception('Failed to bulk recalculate scores: ' . $e->getMessage());
        }

        return [
            'total_contacts' => count($contacts),
            'success_count' => $successCount,
            'error_count' => $errorCount,
            'results' => $results,
        ];
    }

    /**
     * Bulk activate lead scoring rules.
     */
    public function bulkActivateRules(int $tenantId, array $ruleIds): array
    {
        $results = [];
        $successCount = 0;
        $errorCount = 0;

        foreach ($ruleIds as $ruleId) {
            try {
                $rule = LeadScoringRule::where('tenant_id', $tenantId)->find($ruleId);
                
                if (!$rule) {
                    $results[] = [
                        'rule_id' => $ruleId,
                        'status' => 'error',
                        'message' => 'Rule not found'
                    ];
                    $errorCount++;
                    continue;
                }

                $rule->update(['is_active' => true]);
                
                $results[] = [
                    'rule_id' => $ruleId,
                    'rule_name' => $rule->name,
                    'status' => 'success',
                    'message' => 'Rule activated successfully'
                ];
                $successCount++;

            } catch (\Exception $e) {
                $results[] = [
                    'rule_id' => $ruleId,
                    'status' => 'error',
                    'message' => $e->getMessage()
                ];
                $errorCount++;
            }
        }

        return [
            'total_rules' => count($ruleIds),
            'success_count' => $successCount,
            'error_count' => $errorCount,
            'results' => $results,
        ];
    }

    /**
     * Bulk deactivate lead scoring rules.
     */
    public function bulkDeactivateRules(int $tenantId, array $ruleIds): array
    {
        $results = [];
        $successCount = 0;
        $errorCount = 0;

        foreach ($ruleIds as $ruleId) {
            try {
                $rule = LeadScoringRule::where('tenant_id', $tenantId)->find($ruleId);
                
                if (!$rule) {
                    $results[] = [
                        'rule_id' => $ruleId,
                        'status' => 'error',
                        'message' => 'Rule not found'
                    ];
                    $errorCount++;
                    continue;
                }

                $rule->update(['is_active' => false]);
                
                $results[] = [
                    'rule_id' => $ruleId,
                    'rule_name' => $rule->name,
                    'status' => 'success',
                    'message' => 'Rule deactivated successfully'
                ];
                $successCount++;

            } catch (\Exception $e) {
                $results[] = [
                    'rule_id' => $ruleId,
                    'status' => 'error',
                    'message' => $e->getMessage()
                ];
                $errorCount++;
            }
        }

        return [
            'total_rules' => count($ruleIds),
            'success_count' => $successCount,
            'error_count' => $errorCount,
            'results' => $results,
        ];
    }

    /**
     * Export lead scoring rules.
     */
    public function exportRules(int $tenantId, array $filters = []): array
    {
        $query = LeadScoringRule::where('tenant_id', $tenantId);

        // Apply filters
        if (isset($filters['is_active'])) {
            $query->where('is_active', $filters['is_active']);
        }

        if (isset($filters['date_from'])) {
            $query->where('created_at', '>=', $filters['date_from']);
        }

        if (isset($filters['date_to'])) {
            $query->where('created_at', '<=', $filters['date_to']);
        }

        $rules = $query->orderBy('priority', 'desc')->get();

        $format = $filters['format'] ?? 'json';
        $filename = 'lead_scoring_rules_export_' . now()->format('Y-m-d_H-i-s') . '.' . $format;

        // Generate export data
        $exportData = $rules->map(function ($rule) {
            return [
                'id' => $rule->id,
                'name' => $rule->name,
                'description' => $rule->description,
                'condition' => $rule->condition,
                'points' => $rule->points,
                'is_active' => $rule->is_active,
                'priority' => $rule->priority,
                'metadata' => $rule->metadata,
                'created_at' => $rule->created_at,
                'updated_at' => $rule->updated_at,
            ];
        });

        // Store file temporarily
        $filePath = 'exports/' . $filename;
        Storage::put($filePath, json_encode($exportData, JSON_PRETTY_PRINT));

        return [
            'filename' => $filename,
            'file_path' => $filePath,
            'download_url' => Storage::url($filePath),
            'total_rules' => $rules->count(),
            'format' => $format,
        ];
    }

    /**
     * Import lead scoring rules.
     */
    public function importRules(int $tenantId, UploadedFile $file, int $userId): array
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
                    $ruleData = [
                        'name' => $row['name'] ?? 'Imported Rule',
                        'description' => $row['description'] ?? null,
                        'condition' => $row['condition'] ?? [],
                        'points' => $row['points'] ?? 0,
                        'is_active' => $row['is_active'] ?? true,
                        'priority' => $row['priority'] ?? 0,
                        'metadata' => $row['metadata'] ?? null,
                        'tenant_id' => $tenantId,
                        'created_by' => $userId,
                    ];

                    LeadScoringRule::create($ruleData);
                    $successCount++;

                } catch (\Exception $e) {
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
     * Export contacts with lead scores.
     */
public function exportContactsWithScores(int $tenantId, array $filters = []): array
    {
        $query = Contact::where('tenant_id', $tenantId);

        // Apply filters
        if (isset($filters['min_score'])) {
            $query->where('lead_score', '>=', $filters['min_score']);
        }

        if (isset($filters['max_score'])) {
            $query->where('lead_score', '<=', $filters['max_score']);
        }

        if (isset($filters['date_from'])) {
            $query->where('created_at', '>=', $filters['date_from']);
        }

        if (isset($filters['date_to'])) {
            $query->where('created_at', '<=', $filters['date_to']);
        }

        $contacts = $query->orderBy('lead_score', 'desc')->get();

        $format = $filters['format'] ?? 'csv';
        $filename = 'contacts_with_scores_export_' . now()->format('Y-m-d_H-i-s') . '.' . $format;

        // Generate export data
        $exportData = $contacts->map(function ($contact) {
            return [
                'id' => $contact->id,
                'name' => $contact->name,
                'email' => $contact->email,
                'phone' => $contact->phone,
                'company' => $contact->company,
                'lead_score' => $contact->lead_score ?? 0,
                'lead_score_updated_at' => $contact->lead_score_updated_at,
                'created_at' => $contact->created_at,
                'updated_at' => $contact->updated_at,
            ];
        });

        // Store file with proper format
        $filePath = 'exports/' . $filename;
        
        if ($format === 'csv') {
            // Generate proper CSV content
            $csvContent = $this->generateCsvContent($exportData);
            Storage::disk('public')->put($filePath, $csvContent);
        } else {
            // For JSON format, keep the existing logic
            Storage::disk('public')->put($filePath, json_encode($exportData, JSON_PRETTY_PRINT));
        }

        return [
            'filename' => $filename,
            'file_path' => $filePath,
            'download_url' => Storage::url($filePath),
            'total_contacts' => $contacts->count(),
            'format' => $format,
        ];
    }

    /**
     * Generate CSV content from export data.
     */
    private function generateCsvContent($data): string
    {
        if ($data->isEmpty()) {
            return '';
        }

        // Get headers from first item
        $headers = array_keys($data->first());
        
        // Start output buffer
        $output = fopen('php://temp', 'r+');
        
        // Write headers
        fputcsv($output, $headers);
        
        // Write data rows
        foreach ($data as $row) {
            fputcsv($output, $row);
        }
        
        // Get content
        rewind($output);
        $content = stream_get_contents($output);
        fclose($output);
        
        return $content;
    }
}
