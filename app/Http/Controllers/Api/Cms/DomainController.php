<?php

namespace App\Http\Controllers\Api\Cms;

use App\Http\Controllers\Controller;
use App\Http\Resources\Cms\DomainResource;
use App\Models\Cms\Domain;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class DomainController extends Controller
{
    /**
     * Display a listing of domains.
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $query = Domain::query();

            if ($request->boolean('active_only', true)) {
                $query->active();
            }

            $domains = $query->orderBy('is_primary', 'desc')
                            ->orderBy('name')
                            ->get();

            return response()->json([
                'success' => true,
                'data' => DomainResource::collection($domains)
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to fetch domains', [
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch domains',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Store a newly created domain.
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'name' => 'required|string|max:255|unique:cms_domains,name',
            'display_name' => 'nullable|string|max:255',
            'ssl_status' => 'nullable|in:none,pending,active,failed',
            'is_primary' => 'nullable|boolean',
            'settings' => 'nullable|array'
        ]);

        try {
            // If setting as primary, unset other primary domains
            if ($request->boolean('is_primary')) {
                Domain::where('is_primary', true)->update(['is_primary' => false]);
            }

            $domain = Domain::create($request->validated());

            return response()->json([
                'success' => true,
                'message' => 'Domain created successfully',
                'data' => new DomainResource($domain)
            ], 201);

        } catch (\Exception $e) {
            Log::error('Failed to create domain', [
                'error' => $e->getMessage(),
                'request_data' => $request->all()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to create domain',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Display the specified domain.
     */
    public function show(int $id): JsonResponse
    {
        try {
            $domain = Domain::withCount('pages')->findOrFail($id);

            return response()->json([
                'success' => true,
                'data' => new DomainResource($domain)
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Domain not found',
                'error' => config('app.debug') ? $e->getMessage() : 'Domain not found'
            ], 404);
        }
    }

    /**
     * Update the specified domain.
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'name' => 'sometimes|string|max:255|unique:cms_domains,name,' . $id,
            'display_name' => 'nullable|string|max:255',
            'ssl_status' => 'sometimes|in:none,pending,active,failed',
            'is_primary' => 'sometimes|boolean',
            'is_active' => 'sometimes|boolean',
            'settings' => 'nullable|array'
        ]);

        try {
            $domain = Domain::findOrFail($id);

            // If setting as primary, unset other primary domains
            if ($request->boolean('is_primary') && !$domain->is_primary) {
                Domain::where('is_primary', true)->update(['is_primary' => false]);
            }

            $domain->update($request->validated());

            return response()->json([
                'success' => true,
                'message' => 'Domain updated successfully',
                'data' => new DomainResource($domain->fresh())
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to update domain', [
                'domain_id' => $id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to update domain',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Remove the specified domain.
     */
    public function destroy(int $id): JsonResponse
    {
        try {
            $domain = Domain::findOrFail($id);

            // Check if domain has pages
            $pagesCount = $domain->pages()->count();
            if ($pagesCount > 0) {
                return response()->json([
                    'success' => false,
                    'message' => "Cannot delete domain. It has {$pagesCount} page(s)."
                ], 422);
            }

            // Don't allow deleting primary domain
            if ($domain->is_primary) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot delete primary domain.'
                ], 422);
            }

            $domain->delete();

            return response()->json([
                'success' => true,
                'message' => 'Domain deleted successfully'
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to delete domain', [
                'domain_id' => $id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to delete domain',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }
}



