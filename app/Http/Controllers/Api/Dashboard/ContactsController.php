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

        $tenantId = $userId; // tenant equals authenticated user id in this app
        $data = $this->cache->remember($cacheKey, 60, function () use ($limit, $tenantId) {
            if (! $this->db->connection()->getSchemaBuilder()->hasTable('contacts')) {
                return [];
            }

            $rows = $this->db->table('contacts')
                ->where('tenant_id', $tenantId)
                ->where('owner_id', $tenantId)
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
                // Ensure proper ISO strings; fallback to DB raw if Carbon casting not applied through query builder
                'created_at' => $r->created_at ? (is_string($r->created_at) ? $r->created_at : $r->created_at->toIso8601String()) : null,
                'updated_at' => $r->updated_at ? (is_string($r->updated_at) ? $r->updated_at : $r->updated_at->toIso8601String()) : null,
            ])->toArray();
        });

        return response()->json(['success' => true, 'data' => $data]);
    }
}


