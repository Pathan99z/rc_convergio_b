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
            'pipeline' => app(DealsController::class)->summary($request)->getData(true)['data'] ?? [],
            'tasks' => app(TasksController::class)->today($request)->getData(true)['data'] ?? [],
            'contacts' => app(ContactsController::class)->recent($request)->getData(true)['data'] ?? [],
            'campaigns' => app(CampaignsController::class)->metrics($request)->getData(true)['data'] ?? [],
        ];

        return response()->json(['success' => true, 'data' => $data]);
    }
}


