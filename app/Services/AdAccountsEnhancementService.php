<?php

namespace App\Services;

use App\Models\AdAccount;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;

class AdAccountsEnhancementService
{
    /**
     * Bulk delete ad accounts.
     */
    public function bulkDeleteAccounts(int $tenantId, array $accountIds): array
    {
        $results = [];
        $successCount = 0;
        $errorCount = 0;

        foreach ($accountIds as $accountId) {
            try {
                $account = AdAccount::where('tenant_id', $tenantId)->find($accountId);
                
                if (!$account) {
                    $results[] = [
                        'account_id' => $accountId,
                        'status' => 'error',
                        'message' => 'Ad account not found'
                    ];
                    $errorCount++;
                    continue;
                }

                $account->delete();
                
                $results[] = [
                    'account_id' => $accountId,
                    'account_name' => $account->account_name,
                    'status' => 'success',
                    'message' => 'Ad account deleted successfully'
                ];
                $successCount++;

            } catch (\Exception $e) {
                $results[] = [
                    'account_id' => $accountId,
                    'status' => 'error',
                    'message' => $e->getMessage()
                ];
                $errorCount++;
            }
        }

        return [
            'total_accounts' => count($accountIds),
            'success_count' => $successCount,
            'error_count' => $errorCount,
            'results' => $results,
        ];
    }

    /**
     * Bulk activate ad accounts.
     */
    public function bulkActivateAccounts(int $tenantId, array $accountIds): array
    {
        $results = [];
        $successCount = 0;
        $errorCount = 0;

        foreach ($accountIds as $accountId) {
            try {
                $account = AdAccount::where('tenant_id', $tenantId)->find($accountId);
                
                if (!$account) {
                    $results[] = [
                        'account_id' => $accountId,
                        'status' => 'error',
                        'message' => 'Ad account not found'
                    ];
                    $errorCount++;
                    continue;
                }

                $account->update(['is_active' => true]);
                
                $results[] = [
                    'account_id' => $accountId,
                    'account_name' => $account->account_name,
                    'status' => 'success',
                    'message' => 'Ad account activated successfully'
                ];
                $successCount++;

            } catch (\Exception $e) {
                $results[] = [
                    'account_id' => $accountId,
                    'status' => 'error',
                    'message' => $e->getMessage()
                ];
                $errorCount++;
            }
        }

        return [
            'total_accounts' => count($accountIds),
            'success_count' => $successCount,
            'error_count' => $errorCount,
            'results' => $results,
        ];
    }

    /**
     * Bulk deactivate ad accounts.
     */
    public function bulkDeactivateAccounts(int $tenantId, array $accountIds): array
    {
        $results = [];
        $successCount = 0;
        $errorCount = 0;

        foreach ($accountIds as $accountId) {
            try {
                $account = AdAccount::where('tenant_id', $tenantId)->find($accountId);
                
                if (!$account) {
                    $results[] = [
                        'account_id' => $accountId,
                        'status' => 'error',
                        'message' => 'Ad account not found'
                    ];
                    $errorCount++;
                    continue;
                }

                $account->update(['is_active' => false]);
                
                $results[] = [
                    'account_id' => $accountId,
                    'account_name' => $account->account_name,
                    'status' => 'success',
                    'message' => 'Ad account deactivated successfully'
                ];
                $successCount++;

            } catch (\Exception $e) {
                $results[] = [
                    'account_id' => $accountId,
                    'status' => 'error',
                    'message' => $e->getMessage()
                ];
                $errorCount++;
            }
        }

        return [
            'total_accounts' => count($accountIds),
            'success_count' => $successCount,
            'error_count' => $errorCount,
            'results' => $results,
        ];
    }

    /**
     * Export ad accounts.
     */
    public function exportAccounts(int $tenantId, array $filters = []): array
    {
        $query = AdAccount::where('tenant_id', $tenantId);

        // Apply filters
        if (isset($filters['provider'])) {
            $query->where('provider', $filters['provider']);
        }

        if (isset($filters['is_active'])) {
            $query->where('is_active', $filters['is_active']);
        }

        if (isset($filters['date_from'])) {
            $query->where('created_at', '>=', $filters['date_from']);
        }

        if (isset($filters['date_to'])) {
            $query->where('created_at', '<=', $filters['date_to']);
        }

        $accounts = $query->orderBy('created_at', 'desc')->get();

        $format = $filters['format'] ?? 'json';
        $filename = 'ad_accounts_export_' . now()->format('Y-m-d_H-i-s') . '.' . $format;

        // Generate export data (exclude sensitive credentials)
        $exportData = $accounts->map(function ($account) {
            return [
                'id' => $account->id,
                'provider' => $account->provider,
                'account_name' => $account->account_name,
                'account_id' => $account->account_id,
                'settings' => $account->settings,
                'is_active' => $account->is_active,
                'created_at' => $account->created_at,
                'updated_at' => $account->updated_at,
                // Note: credentials are excluded for security
            ];
        });

        // Store file temporarily
        $filePath = 'exports/' . $filename;
        Storage::put($filePath, json_encode($exportData, JSON_PRETTY_PRINT));

        return [
            'filename' => $filename,
            'file_path' => $filePath,
            'download_url' => Storage::url($filePath),
            'total_accounts' => $accounts->count(),
            'format' => $format,
        ];
    }

    /**
     * Import ad accounts.
     */
    public function importAccounts(int $tenantId, UploadedFile $file): array
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
                    $accountData = [
                        'provider' => $row['provider'] ?? 'facebook',
                        'account_name' => $row['account_name'] ?? 'Imported Account',
                        'account_id' => $row['account_id'] ?? null,
                        'credentials' => $row['credentials'] ?? [], // Will need to be updated manually
                        'settings' => $row['settings'] ?? null,
                        'is_active' => $row['is_active'] ?? false,
                        'tenant_id' => $tenantId,
                    ];

                    AdAccount::create($accountData);
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
     * Export single ad account.
     */
    public function exportSingleAccount(int $tenantId, int $accountId): array
    {
        $account = AdAccount::where('tenant_id', $tenantId)->findOrFail($accountId);
        
        $filename = 'ad_account_' . $account->id . '_export_' . now()->format('Y-m-d_H-i-s') . '.json';

        $exportData = [
            'id' => $account->id,
            'provider' => $account->provider,
            'account_name' => $account->account_name,
            'account_id' => $account->account_id,
            'settings' => $account->settings,
            'is_active' => $account->is_active,
            'created_at' => $account->created_at,
            'updated_at' => $account->updated_at,
            // Note: credentials are excluded for security
        ];

        $filePath = 'exports/' . $filename;
        Storage::put($filePath, json_encode($exportData, JSON_PRETTY_PRINT));

        return [
            'filename' => $filename,
            'file_path' => $filePath,
            'download_url' => Storage::url($filePath),
            'account_name' => $account->account_name,
            'provider' => $account->provider,
        ];
    }
}

