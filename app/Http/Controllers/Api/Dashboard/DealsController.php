<?php

namespace App\Http\Controllers\Api\Dashboard;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Database\DatabaseManager as DB;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class DealsController extends Controller
{
    public function __construct(private CacheRepository $cache, private DB $db) {}

    public function summary(Request $request): JsonResponse
    {
        $range = (string) $request->query('range', '7d');
        $userId = $request->user()->id;
        $cacheKey = "deals:summary:user:{$userId}:range:{$range}";

        $data = $this->cache->remember($cacheKey, 60, function () use ($range, $userId) {
            // Guard if deals table doesn't exist (for clean bootstrapping)
            if (! $this->db->connection()->getSchemaBuilder()->hasTable('deals')) {
                return [
                    'open_value' => 0,
                    'won_today' => 0,
                    'lost_today' => 0,
                    'won_week' => 0,
                    'lost_week' => 0,
                    'range' => $range,
                ];
            }

            // Get tenant_id from user
            $user = $this->db->table('users')->where('id', $userId)->first();
            $tenantId = $user->organization_name === 'Globex LLC' ? 4 : 1;

            $now = Carbon::now();
            $startOfWeek = $now->copy()->startOfWeek();
            $endOfWeek = $now->copy()->endOfWeek();

            // Use correct column names from deals table schema with tenant filtering
            $openValue = $this->db->table('deals')->where('tenant_id', $tenantId)->where('status', 'open')->sum('value');
            $wonToday = $this->db->table('deals')->where('tenant_id', $tenantId)->where('status', 'won')->whereDate('closed_date', $now->toDateString())->count();
            $lostToday = $this->db->table('deals')->where('tenant_id', $tenantId)->where('status', 'lost')->whereDate('closed_date', $now->toDateString())->count();
            $wonWeek = $this->db->table('deals')->where('tenant_id', $tenantId)->where('status', 'won')->whereBetween('closed_date', [$startOfWeek, $endOfWeek])->count();
            $lostWeek = $this->db->table('deals')->where('tenant_id', $tenantId)->where('status', 'lost')->whereBetween('closed_date', [$startOfWeek, $endOfWeek])->count();

            return [
                'open_value' => (float) $openValue,
                'won_today' => (int) $wonToday,
                'lost_today' => (int) $lostToday,
                'won_week' => (int) $wonWeek,
                'lost_week' => (int) $lostWeek,
                'range' => $range,
            ];
        });

        return response()->json(['success' => true, 'data' => $data]);
    }
}


