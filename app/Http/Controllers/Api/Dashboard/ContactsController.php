<?php

namespace App\Http\Controllers\Api\Dashboard;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Database\DatabaseManager as DB;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ContactsController extends Controller
{
    public function __construct(private CacheRepository $cache, private DB $db) {}

    public function recent(Request $request): JsonResponse
    {
        $limit = (int) $request->query('limit', 5);
        $userId = $request->user()->id;
        $cacheKey = "contacts:recent:user:{$userId}:limit:{$limit}";

        $data = $this->cache->remember($cacheKey, 60, function () use ($limit) {
            if (! $this->db->connection()->getSchemaBuilder()->hasTable('contacts')) {
                return [];
            }

            $rows = $this->db->table('contacts')
                ->orderByDesc('updated_at')
                ->orderByDesc('created_at')
                ->limit($limit)
                ->get(['id', 'first_name', 'last_name', 'email', 'phone', 'created_at', 'updated_at']);

            return $rows->map(fn ($r) => [
                'id' => (int) $r->id,
                'first_name' => $r->first_name,
                'last_name' => $r->last_name,
                'email' => $r->email,
                'phone' => $r->phone,
                'created_at' => optional($r->created_at)?->toIso8601String(),
                'updated_at' => optional($r->updated_at)?->toIso8601String(),
            ])->toArray();
        });

        return response()->json(['success' => true, 'data' => $data]);
    }
}


