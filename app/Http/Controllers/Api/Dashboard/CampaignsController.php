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
            if (! $this->db->connection()->getSchemaBuilder()->hasTable('campaign_events')) {
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

            $q = $this->db->table('campaign_events')->where('created_at', '>=', $since);
            $delivered = (clone $q)->where('type', 'delivered')->count();
            $opens = (clone $q)->where('type', 'open')->count();
            $clicks = (clone $q)->where('type', 'click')->count();
            $bounces = (clone $q)->where('type', 'bounce')->count();

            return compact('delivered', 'opens', 'clicks', 'bounces') + ['range' => $range];
        });

        return response()->json(['success' => true, 'data' => $data]);
    }
}


