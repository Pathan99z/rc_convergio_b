<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AdAccount;
use App\Services\AdAccountsEnhancementService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class AdAccountsEnhancementController extends Controller
{
    protected AdAccountsEnhancementService $enhancementService;

    public function __construct(AdAccountsEnhancementService $enhancementService)
    {
        $this->enhancementService = $enhancementService;
    }

    /**
     * Bulk delete ad accounts.
     */
    public function bulkDelete(Request $request): JsonResponse
    {
        $user = Auth::user();
        $tenantId = $user->tenant_id;

        $validated = $request->validate([
            'account_ids' => 'required|array|min:1|max:20',
            'account_ids.*' => 'integer|exists:ad_accounts,id',
        ]);

        try {
            $result = $this->enhancementService->bulkDeleteAccounts($tenantId, $validated['account_ids']);

            return response()->json([
                'success' => true,
                'message' => 'Bulk delete operation completed',
                'data' => $result
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to bulk delete ad accounts',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Bulk activate ad accounts.
     */
    public function bulkActivate(Request $request): JsonResponse
    {
        $user = Auth::user();
        $tenantId = $user->tenant_id;

        $validated = $request->validate([
            'account_ids' => 'required|array|min:1|max:20',
            'account_ids.*' => 'integer|exists:ad_accounts,id',
        ]);

        try {
            $result = $this->enhancementService->bulkActivateAccounts($tenantId, $validated['account_ids']);

            return response()->json([
                'success' => true,
                'message' => 'Bulk activate operation completed',
                'data' => $result
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to bulk activate ad accounts',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Bulk deactivate ad accounts.
     */
    public function bulkDeactivate(Request $request): JsonResponse
    {
        $user = Auth::user();
        $tenantId = $user->tenant_id;

        $validated = $request->validate([
            'account_ids' => 'required|array|min:1|max:20',
            'account_ids.*' => 'integer|exists:ad_accounts,id',
        ]);

        try {
            $result = $this->enhancementService->bulkDeactivateAccounts($tenantId, $validated['account_ids']);

            return response()->json([
                'success' => true,
                'message' => 'Bulk deactivate operation completed',
                'data' => $result
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to bulk deactivate ad accounts',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Export ad accounts.
     */
    public function export(Request $request): JsonResponse
    {
        $user = Auth::user();
        $tenantId = $user->tenant_id;

        $validated = $request->validate([
            'format' => ['sometimes', Rule::in(['csv', 'excel', 'json'])],
            'provider' => ['sometimes', Rule::in(array_keys(AdAccount::getAvailableProviders()))],
            'is_active' => 'sometimes|boolean',
            'date_from' => 'sometimes|date',
            'date_to' => 'sometimes|date|after_or_equal:date_from',
        ]);

        try {
            $result = $this->enhancementService->exportAccounts($tenantId, $validated);

            return response()->json([
                'success' => true,
                'message' => 'Ad accounts exported successfully',
                'data' => $result
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to export ad accounts',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Import ad accounts.
     */
    public function import(Request $request): JsonResponse
    {
        $user = Auth::user();
        $tenantId = $user->tenant_id;

        $validated = $request->validate([
            'file' => 'required|file|mimes:csv,xlsx,xls,json|max:10240', // 10MB max
        ]);

        try {
            $result = $this->enhancementService->importAccounts($tenantId, $validated['file']);

            return response()->json([
                'success' => true,
                'message' => 'Ad accounts imported successfully',
                'data' => $result
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to import ad accounts',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Export single ad account.
     */
    public function exportSingle(Request $request, int $id): JsonResponse
    {
        $user = Auth::user();
        $tenantId = $user->tenant_id;

        try {
            $result = $this->enhancementService->exportSingleAccount($tenantId, $id);

            return response()->json([
                'success' => true,
                'message' => 'Ad account exported successfully',
                'data' => $result
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to export ad account',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}

