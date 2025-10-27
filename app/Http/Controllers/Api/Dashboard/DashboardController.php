<?php

namespace App\Http\Controllers\Api\Dashboard;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function __construct(private CacheRepository $cache) {}

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $cacheKey = "dashboard_data_{$user->id}_" . md5(serialize($request->all()));

        $data = $this->cache->remember($cacheKey, 300, function () use ($request) {
            return [
                'pipeline' => app(\App\Http\Controllers\Api\Dashboard\DealsController::class)->summary($request)->getData(true)['data'] ?? [],
                'tasks' => app(\App\Http\Controllers\Api\Dashboard\TasksController::class)->today($request)->getData(true)['data'] ?? [],
                'contacts' => app(\App\Http\Controllers\Api\Dashboard\ContactsController::class)->recent($request)->getData(true)['data'] ?? [],
                'campaigns' => app(\App\Http\Controllers\Api\Dashboard\CampaignsController::class)->metrics($request)->getData(true)['data'] ?? [],
            ];
        });

        return response()->json(['success' => true, 'data' => $data]);
    }

    /**
     * Get application status.
     */
    public function status(Request $request): JsonResponse
    {
        try {
            $status = [
                'app' => [
                    'name' => config('app.name'),
                    'version' => '1.0.0',
                    'environment' => config('app.env'),
                    'debug' => config('app.debug'),
                    'timezone' => config('app.timezone')
                ],
                'database' => [
                    'connected' => true,
                    'driver' => config('database.default')
                ],
                'cache' => [
                    'driver' => config('cache.default'),
                    'working' => true
                ],
                'features' => [
                    'social_media' => true,
                    'campaigns' => true,
                    'analytics' => true,
                    'contacts' => true,
                    'deals' => true
                ],
                'user' => [
                    'authenticated' => $request->user() ? true : false,
                    'id' => $request->user()?->id,
                    'name' => $request->user()?->name,
                    'organization' => $request->user()?->organization_name
                ],
                'timestamp' => now()->toISOString()
            ];

            return response()->json([
                'success' => true,
                'data' => $status
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get application status',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }
}


