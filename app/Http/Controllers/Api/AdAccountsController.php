<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AdAccount;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class AdAccountsController extends Controller
{
    /**
     * Get all ad accounts for the authenticated user's tenant.
     */
    public function index(Request $request): JsonResponse
    {
        $user = Auth::user();
        $tenantId = $user->tenant_id;

        $adAccounts = AdAccount::where('tenant_id', $tenantId)
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'data' => $adAccounts,
            'message' => 'Ad accounts retrieved successfully'
        ]);
    }

    /**
     * Connect a new ad account.
     */
    public function store(Request $request): JsonResponse
    {
        $user = Auth::user();
        $tenantId = $user->tenant_id;

        $validated = $request->validate([
            'provider' => [
                'required',
                'string',
                Rule::in(array_keys(AdAccount::getAvailableProviders()))
            ],
            'account_name' => 'required|string|max:255',
            'account_id' => 'nullable|string|max:255',
            'credentials' => 'required|array',
            'settings' => 'nullable|array',
        ]);

        try {
            DB::beginTransaction();

            $adAccount = AdAccount::create([
                'provider' => $validated['provider'],
                'account_name' => $validated['account_name'],
                'account_id' => $validated['account_id'],
                'credentials' => $validated['credentials'],
                'settings' => $validated['settings'] ?? [],
                'tenant_id' => $tenantId,
            ]);

            DB::commit();

            return response()->json([
                'data' => $adAccount,
                'message' => 'Ad account connected successfully'
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Failed to connect ad account',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update an ad account.
     */
    public function update(Request $request, $id): JsonResponse
    {
        $user = Auth::user();
        $tenantId = $user->tenant_id;

        $adAccount = AdAccount::where('id', $id)
            ->where('tenant_id', $tenantId)
            ->firstOrFail();

        $validated = $request->validate([
            'account_name' => 'sometimes|string|max:255',
            'credentials' => 'sometimes|array',
            'settings' => 'sometimes|array',
            'is_active' => 'sometimes|boolean',
        ]);

        try {
            $adAccount->update($validated);

            return response()->json([
                'data' => $adAccount,
                'message' => 'Ad account updated successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to update ad account',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete an ad account.
     */
    public function destroy($id): JsonResponse
    {
        $user = Auth::user();
        $tenantId = $user->tenant_id;

        $adAccount = AdAccount::where('id', $id)
            ->where('tenant_id', $tenantId)
            ->firstOrFail();

        try {
            $adAccount->delete();

            return response()->json([
                'message' => 'Ad account deleted successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to delete ad account',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get available ad providers.
     */
    public function providers(): JsonResponse
    {
        return response()->json([
            'data' => AdAccount::getAvailableProviders(),
            'message' => 'Ad providers retrieved successfully'
        ]);
    }
}
