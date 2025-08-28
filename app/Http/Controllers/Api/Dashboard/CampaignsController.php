<?php

namespace App\Http\Controllers\Api\Dashboard;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Database\DatabaseManager as DB;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class CampaignsController extends Controller
{
    public function __construct(private CacheRepository $cache, private DB $db) {}

    public function metrics(Request $request): JsonResponse
    {
        $range = (string) $request->query('range', '14d');
        $userId = $request->user()->id;
        $cacheKey = "campaigns:metrics:user:{$userId}:range:{$range}";

        $data = $this->cache->remember($cacheKey, 60, function () use ($range) {
            if (! $this->db->connection()->getSchemaBuilder()->hasTable('campaigns')) {
                return [
                    'delivered' => 0,
                    'opens' => 0,
                    'clicks' => 0,
                    'bounces' => 0,
                    'range' => $range,
                ];
            }

            $now = Carbon::now();
            $since = match (true) {
                str_ends_with($range, 'd') => $now->copy()->subDays((int) rtrim($range, 'd')),
                str_ends_with($range, 'w') => $now->copy()->subWeeks((int) rtrim($range, 'w')),
                default => $now->copy()->subDays(14),
            };

            // Use the campaigns table which has metrics columns directly
            $q = $this->db->table('campaigns')->where('created_at', '>=', $since);
            $delivered = (clone $q)->sum('delivered_count');
            $opens = (clone $q)->sum('opened_count');
            $clicks = (clone $q)->sum('clicked_count');
            $bounces = (clone $q)->sum('bounced_count');

            return compact('delivered', 'opens', 'clicks', 'bounces') + ['range' => $range];
        });

        return response()->json(['success' => true, 'data' => $data]);
    }
}


