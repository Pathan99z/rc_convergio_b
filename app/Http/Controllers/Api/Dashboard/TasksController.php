<?php

namespace App\Http\Controllers\Api\Dashboard;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Database\DatabaseManager as DB;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class TasksController extends Controller
{
    public function __construct(private CacheRepository $cache, private DB $db) {}

    public function today(Request $request): JsonResponse
    {
        $userId = $request->user()->id;
        $cacheKey = "tasks:today:user:{$userId}";

        $data = $this->cache->remember($cacheKey, 60, function () use ($userId) {
            if (! $this->db->connection()->getSchemaBuilder()->hasTable('tasks')) {
                return [
                    'today' => 0,
                    'overdue' => 0,
                    'can_quick_complete' => true,
                ];
            }

            $today = Carbon::today();

            $todayCount = $this->db->table('tasks')
                ->where(function($query) use ($userId) {
                    $query->where('owner_id', $userId)
                          ->orWhere('assigned_to', $userId);
                })
                ->whereDate('due_date', $today)
                ->where('status', '!=', 'completed')
                ->count();

            $overdueCount = $this->db->table('tasks')
                ->where(function($query) use ($userId) {
                    $query->where('owner_id', $userId)
                          ->orWhere('assigned_to', $userId);
                })
                ->whereDate('due_date', '<', $today)
                ->where('status', '!=', 'completed')
                ->count();

            return [
                'today' => (int) $todayCount,
                'overdue' => (int) $overdueCount,
                'can_quick_complete' => true,
            ];
        });

        return response()->json(['success' => true, 'data' => $data]);
    }
}


