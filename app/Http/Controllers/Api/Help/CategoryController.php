<?php

namespace App\Http\Controllers\Api\Help;

use App\Http\Controllers\Controller;
use App\Http\Requests\Help\StoreCategoryRequest;
use App\Http\Requests\Help\UpdateCategoryRequest;
use App\Http\Resources\Help\CategoryResource;
use App\Models\Help\Category;
use App\Services\Help\ArticleService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class CategoryController extends Controller
{
    public function __construct(
        private ArticleService $articleService
    ) {}

    /**
     * Display a listing of categories (public).
     */
    public function publicIndex(Request $request): AnonymousResourceCollection
    {
        $tenantId = $this->getTenantIdFromRequest($request);
        
        $categories = Category::forTenant($tenantId)
            ->withArticleCount()
            ->orderBy('name')
            ->get();

        return CategoryResource::collection($categories);
    }

    /**
     * Display a listing of categories (admin).
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Category::class);

        $tenantId = $request->user()->tenant_id ?? $request->user()->id;
        
        $categories = Category::forTenant($tenantId)
            ->withArticleCount()
            ->with('creator')
            ->orderBy('name')
            ->get();

        return CategoryResource::collection($categories);
    }

    /**
     * Store a newly created category.
     */
    public function store(StoreCategoryRequest $request): JsonResponse
    {
        $data = $request->validated();
        $tenantId = $request->user()->tenant_id ?? $request->user()->id;
        $createdBy = $request->user()->id;

        $category = Category::create([
            'tenant_id' => $tenantId,
            'name' => $data['name'],
            'slug' => $data['slug'] ?? \Illuminate\Support\Str::slug($data['name']),
            'description' => $data['description'] ?? null,
            'created_by' => $createdBy,
        ]);

        return response()->json([
            'success' => true,
            'data' => new CategoryResource($category->load('creator')),
            'message' => 'Category created successfully'
        ], 201);
    }

    /**
     * Display the specified category.
     */
    public function show(Request $request, int $id): JsonResponse
    {
        $tenantId = $request->user()->tenant_id ?? $request->user()->id;
        $category = Category::where('id', $id)
            ->where('tenant_id', $tenantId)
            ->firstOrFail();

        if (!$request->user()->can('view', $category)) {
            abort(403, 'This action is unauthorized.');
        }

        return response()->json([
            'success' => true,
            'data' => new CategoryResource($category->load(['creator', 'articles']))
        ]);
    }

    /**
     * Update the specified category.
     */
    public function update(UpdateCategoryRequest $request, int $id): JsonResponse
    {
        $data = $request->validated();
        $tenantId = $request->user()->tenant_id ?? $request->user()->id;
        $category = Category::where('id', $id)
            ->where('tenant_id', $tenantId)
            ->firstOrFail();

        // Authorization is handled in UpdateCategoryRequest
        $category->update([
            'name' => $data['name'],
            'slug' => $data['slug'] ?? $category->slug,
            'description' => $data['description'] ?? $category->description,
        ]);

        return response()->json([
            'success' => true,
            'data' => new CategoryResource($category->load('creator')),
            'message' => 'Category updated successfully'
        ]);
    }

    /**
     * Remove the specified category.
     */
    public function destroy(Request $request, int $id): JsonResponse
    {
        $tenantId = $request->user()->tenant_id ?? $request->user()->id;
        $category = Category::where('id', $id)
            ->where('tenant_id', $tenantId)
            ->firstOrFail();

        if (!$request->user()->can('delete', $category)) {
            abort(403, 'This action is unauthorized.');
        }

        // Check if category has articles
        if ($category->articles()->count() > 0) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot delete category with existing articles'
            ], 422);
        }

        $category->delete();

        return response()->json([
            'success' => true,
            'message' => 'Category deleted successfully'
        ]);
    }

    /**
     * Get tenant ID from request (for public endpoints).
     */
    private function getTenantIdFromRequest(Request $request): int
    {
        // Try to get tenant from authenticated user first
        if ($request->user()) {
            return $request->user()->tenant_id ?? $request->user()->id;
        }

        // For public endpoints, get tenant from query parameter
        $tenantId = $request->query('tenant');
        
        if (!$tenantId || !is_numeric($tenantId)) {
            abort(400, 'Tenant ID is required for public access');
        }

        return (int) $tenantId;
    }
}
