<?php

namespace App\Http\Controllers\Api\Commerce;

use App\Http\Controllers\Controller;
use App\Models\Commerce\CommerceOrder;
use App\Services\Commerce\OrderService;
use App\Services\TeamAccessService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class CommerceOrderController extends Controller
{
    public function __construct(
        private OrderService $orderService,
        private TeamAccessService $teamAccessService
    ) {}

    /**
     * Display a listing of orders.
     */
    public function index(Request $request): JsonResponse
    {
        // Get tenant_id from header or use user's organization as fallback
        $tenantId = optional($request->user())->tenant_id ?? $request->user()->id;
        if ($tenantId === 0) {
            // Use organization_name to determine tenant_id
            $user = $request->user();
            if ($user->organization_name === 'Globex LLC') {
                $tenantId = 4; // chitti's organization
            } else {
                $tenantId = 1; // default tenant
            }
        }

        $query = CommerceOrder::where('tenant_id', $tenantId);

        // Apply team filtering if team access is enabled
        $this->teamAccessService->applyTeamFilter($query);

        // Search filter
        if ($search = $request->query('search')) {
            $query->search($search);
        }

        // Status filter
        if ($status = $request->query('status')) {
            $query->withStatus($status);
        }

        // Date range filter
        if ($dateFrom = $request->query('date_from')) {
            $query->where('created_at', '>=', $dateFrom);
        }

        if ($dateTo = $request->query('date_to')) {
            $query->where('created_at', '<=', $dateTo);
        }

        // Pagination
        $perPage = $request->query('per_page', 15);
        $userId = $request->user()->id;
        
        // Create cache key with tenant and user isolation
        $cacheKey = "commerce_orders_list_{$tenantId}_{$userId}_" . md5(serialize($request->all()));
        
        // Cache orders list for 5 minutes (300 seconds)
        $orders = Cache::remember($cacheKey, 300, function () use ($query, $perPage) {
            return $query->with(['items', 'deal', 'quote', 'contact', 'owner'])
                ->orderBy('created_at', 'desc')
                ->paginate($perPage);
        });

        return response()->json([
            'success' => true,
            'message' => 'Orders retrieved successfully',
            'data' => $orders->items(),
            'meta' => [
                'current_page' => $orders->currentPage(),
                'last_page' => $orders->lastPage(),
                'per_page' => $orders->perPage(),
                'total' => $orders->total(),
                'from' => $orders->firstItem(),
                'to' => $orders->lastItem(),
            ],
        ]);
    }

    /**
     * Display the specified order.
     */
    public function show(Request $request, int $id): JsonResponse
    {
        // Get tenant_id from header or use user's organization as fallback
        $tenantId = optional($request->user())->tenant_id ?? $request->user()->id;
        if ($tenantId === 0) {
            // Use organization_name to determine tenant_id
            $user = $request->user();
            if ($user->organization_name === 'Globex LLC') {
                $tenantId = 4; // chitti's organization
            } else {
                $tenantId = 1; // default tenant
            }
        }

        $userId = $request->user()->id;
        
        // Create cache key with tenant, user, and order ID isolation
        $cacheKey = "commerce_order_show_{$tenantId}_{$userId}_{$id}";
        
        // Cache order detail for 15 minutes (900 seconds)
        $order = Cache::remember($cacheKey, 900, function () use ($tenantId, $id) {
            return CommerceOrder::where('tenant_id', $tenantId)
                ->with(['items.product', 'deal', 'quote', 'contact', 'owner', 'team'])
                ->findOrFail($id);
        });

        return response()->json([
            'success' => true,
            'message' => 'Order retrieved successfully',
            'data' => $order,
        ]);
    }

    /**
     * Store a newly created order.
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'deal_id' => 'nullable|exists:deals,id',
            'quote_id' => 'nullable|exists:quotes,id',
            'contact_id' => 'nullable|exists:contacts,id',
            'total_amount' => 'required|numeric|min:0',
            'currency' => 'string|max:3',
            'status' => 'string|in:pending,paid,failed,refunded',
            'payment_method' => 'nullable|string|max:255',
            'payment_reference' => 'nullable|string|max:255',
            'items' => 'nullable|array',
            'items.*.product_id' => 'nullable|exists:products,id',
            'items.*.name' => 'required|string|max:255',
            'items.*.quantity' => 'required|integer|min:1',
            'items.*.unit_price' => 'required|numeric|min:0',
            'items.*.discount' => 'nullable|numeric|min:0',
            'items.*.tax_rate' => 'nullable|numeric|min:0|max:100',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        // Get tenant_id from header or use user's organization as fallback
        $tenantId = optional($request->user())->tenant_id ?? $request->user()->id;
        if ($tenantId === 0) {
            // Use organization_name to determine tenant_id
            $user = $request->user();
            if ($user->organization_name === 'Globex LLC') {
                $tenantId = 4; // chitti's organization
            } else {
                $tenantId = 1; // default tenant
            }
        }

        $data = $request->validated();
        $data['tenant_id'] = $tenantId;
        $data['team_id'] = $request->user()->team_id;
        $data['owner_id'] = $request->user()->id;

        $order = $this->orderService->createOrder($data);

        // Clear cache after creating order
        $this->clearCommerceOrdersCache($tenantId, $request->user()->id);

        return response()->json([
            'success' => true,
            'message' => 'Order created successfully',
            'data' => $order,
        ], 201);
    }

    /**
     * Update the specified order.
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'status' => 'string|in:pending,paid,failed,refunded',
            'payment_method' => 'nullable|string|max:255',
            'payment_reference' => 'nullable|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        // Get tenant_id from header or use user's organization as fallback
        $tenantId = optional($request->user())->tenant_id ?? $request->user()->id;
        if ($tenantId === 0) {
            // Use organization_name to determine tenant_id
            $user = $request->user();
            if ($user->organization_name === 'Globex LLC') {
                $tenantId = 4; // chitti's organization
            } else {
                $tenantId = 1; // default tenant
            }
        }

        $order = CommerceOrder::where('tenant_id', $tenantId)->findOrFail($id);

        $data = $request->validated();
        $order = $this->orderService->updateOrderStatus($order, $data['status'] ?? $order->status, $data);

        // Clear cache after updating order
        $this->clearCommerceOrdersCache($tenantId, $request->user()->id);
        Cache::forget("commerce_order_show_{$tenantId}_{$request->user()->id}_{$id}");

        return response()->json([
            'success' => true,
            'message' => 'Order updated successfully',
            'data' => $order,
        ]);
    }

    /**
     * Get order statistics.
     */
    public function stats(Request $request): JsonResponse
    {
        // Get tenant_id from header or use user's organization as fallback
        $tenantId = optional($request->user())->tenant_id ?? $request->user()->id;
        if ($tenantId === 0) {
            // Use organization_name to determine tenant_id
            $user = $request->user();
            if ($user->organization_name === 'Globex LLC') {
                $tenantId = 4; // chitti's organization
            } else {
                $tenantId = 1; // default tenant
            }
        }

        $query = CommerceOrder::where('tenant_id', $tenantId);

        // Apply team filtering if team access is enabled
        $this->teamAccessService->applyTeamFilter($query);

        $userId = $request->user()->id;
        
        // Create cache key for order stats
        $cacheKey = "commerce_orders_stats_{$tenantId}_{$userId}";
        
        // Cache order stats for 5 minutes (300 seconds)
        $stats = Cache::remember($cacheKey, 300, function () use ($query) {
            return [
                'total_orders' => $query->count(),
                'pending_orders' => $query->clone()->withStatus('pending')->count(),
                'paid_orders' => $query->clone()->withStatus('paid')->count(),
                'failed_orders' => $query->clone()->withStatus('failed')->count(),
                'refunded_orders' => $query->clone()->withStatus('refunded')->count(),
                'total_revenue' => $query->clone()->withStatus('paid')->sum('total_amount'),
            ];
        });

        return response()->json([
            'success' => true,
            'message' => 'Order statistics retrieved successfully',
            'data' => $stats,
        ]);
    }

    /**
     * Delete an order.
     */
    public function destroy(Request $request, int $id): JsonResponse
    {
        // Get tenant_id from header or use user's organization as fallback
        $tenantId = optional($request->user())->tenant_id ?? $request->user()->id;
        if ($tenantId === 0) {
            // Use organization_name to determine tenant_id
            $user = $request->user();
            if ($user->organization_name === 'Globex LLC') {
                $tenantId = 4; // chitti's organization
            } else {
                $tenantId = 1; // default tenant
            }
        }

        $order = CommerceOrder::where('tenant_id', $tenantId)->findOrFail($id);

        // Apply team filtering if team access is enabled
        $this->teamAccessService->applyTeamFilter(CommerceOrder::where('tenant_id', $tenantId))->findOrFail($id);

        // Check if order can be deleted (e.g., not paid orders)
        if ($order->status === 'paid') {
            return response()->json([
                'success' => false,
                'message' => 'Cannot delete paid orders. Please refund the order first.',
            ], 422);
        }

        $userId = $request->user()->id;
        $orderId = $order->id;

        $this->orderService->deleteOrder($order);

        // Clear cache after deleting order
        $this->clearCommerceOrdersCache($tenantId, $userId);
        Cache::forget("commerce_order_show_{$tenantId}_{$userId}_{$orderId}");

        return response()->json([
            'success' => true,
            'message' => 'Order deleted successfully',
        ]);
    }

    /**
     * Clear commerce orders cache for a specific tenant and user.
     * This method prevents code duplication and ensures consistent cache invalidation.
     *
     * @param int $tenantId
     * @param int $userId
     * @return void
     */
    private function clearCommerceOrdersCache(int $tenantId, int $userId): void
    {
        try {
            // Clear common cache patterns for orders list
            $commonParams = [
                '',
                md5(serialize(['status' => 'pending', 'per_page' => 15])),
                md5(serialize(['status' => 'paid', 'per_page' => 15])),
            ];

            foreach ($commonParams as $params) {
                Cache::forget("commerce_orders_list_{$tenantId}_{$userId}_{$params}");
            }

            // Clear stats cache
            Cache::forget("commerce_orders_stats_{$tenantId}_{$userId}");

            Log::info('Commerce orders cache cleared', [
                'tenant_id' => $tenantId,
                'user_id' => $userId,
                'cleared_keys' => count($commonParams) + 1
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to clear commerce orders cache', [
                'tenant_id' => $tenantId,
                'user_id' => $userId,
                'error' => $e->getMessage()
            ]);
        }
    }
}
