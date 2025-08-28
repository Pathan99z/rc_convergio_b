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

        $data = [
            'pipeline' => app(\App\Http\Controllers\Api\Dashboard\DealsController::class)->summary($request)->getData(true)['data'] ?? [],
            'tasks' => app(\App\Http\Controllers\Api\Dashboard\TasksController::class)->today($request)->getData(true)['data'] ?? [],
            'contacts' => app(\App\Http\Controllers\Api\Dashboard\ContactsController::class)->recent($request)->getData(true)['data'] ?? [],
            'campaigns' => app(\App\Http\Controllers\Api\Dashboard\CampaignsController::class)->metrics($request)->getData(true)['data'] ?? [],
        ];

        return response()->json(['success' => true, 'data' => $data]);
    }
}


