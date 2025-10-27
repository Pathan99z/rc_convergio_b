<?php

namespace App\Http\Controllers\Api\Cms;

use App\Http\Controllers\Controller;
use App\Http\Requests\Cms\StorePageRequest;
use App\Http\Requests\Cms\UpdatePageRequest;
use App\Http\Resources\Cms\PageResource;
use App\Models\Cms\Page;
use App\Services\Cms\SeoAnalyzer;
use App\Events\Cms\PagePublished;
use App\Events\Cms\PageUnpublished;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class PageController extends Controller
{
    protected SeoAnalyzer $seoAnalyzer;

    public function __construct(SeoAnalyzer $seoAnalyzer)
    {
        $this->seoAnalyzer = $seoAnalyzer;
    }

    /**
     * Display a listing of pages.
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $query = Page::with(['template', 'domain', 'language', 'creator'])
                         ->when($request->filled('status'), fn($q) => $q->where('status', $request->status))
                         ->when($request->filled('domain_id'), fn($q) => $q->where('domain_id', $request->domain_id))
                         ->when($request->filled('language_id'), fn($q) => $q->where('language_id', $request->language_id))
                         ->when($request->filled('template_id'), fn($q) => $q->where('template_id', $request->template_id))
                         ->when($request->filled('search'), function($q) use ($request) {
                             $search = $request->search;
                             $q->where(function($query) use ($search) {
                                 $query->where('title', 'like', "%{$search}%")
                                       ->orWhere('meta_title', 'like', "%{$search}%")
                                       ->orWhere('slug', 'like', "%{$search}%");
                             });
                         });

            $sortBy = $request->get('sort_by', 'updated_at');
            $sortOrder = $request->get('sort_order', 'desc');
            
            if (in_array($sortBy, ['title', 'status', 'created_at', 'updated_at', 'published_at', 'view_count'])) {
                $query->orderBy($sortBy, $sortOrder);
            }

            $perPage = min($request->get('per_page', 15), 100);
            $pages = $query->paginate($perPage);

            return response()->json([
                'data' => PageResource::collection($pages->items()),
                'meta' => [
                    'current_page' => $pages->currentPage(),
                    'last_page' => $pages->lastPage(),
                    'per_page' => $pages->perPage(),
                    'total' => $pages->total(),
                    'from' => $pages->firstItem(),
                    'to' => $pages->lastItem(),
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to fetch pages', [
                'error' => $e->getMessage(),
                'user_id' => Auth::id()
            ]);

            return response()->json([
                'message' => 'Failed to fetch pages',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Store a newly created page.
     */
    public function store(StorePageRequest $request): JsonResponse
    {
        try {
            $validatedData = $request->validated();
            $validatedData['created_by'] = Auth::id();
            $validatedData['updated_by'] = Auth::id();

            $page = Page::create($validatedData);

            // Run SEO analysis if page is being published
            if ($page->status === 'published') {
                $this->runSeoAnalysis($page);
                event(new PagePublished($page));
            }

            return response()->json([
                'message' => 'Page created successfully',
                'data' => new PageResource($page->load(['template', 'domain', 'language', 'creator']))
            ], 201);

        } catch (\Exception $e) {
            Log::error('Failed to create page', [
                'error' => $e->getMessage(),
                'user_id' => Auth::id(),
                'request_data' => $request->all()
            ]);

            return response()->json([
                'message' => 'Failed to create page',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Display the specified page.
     */
    public function show(int $id): JsonResponse
    {
        try {
            $page = Page::with(['template', 'domain', 'language', 'creator', 'personalizationRules', 'seoLogs'])
                        ->findOrFail($id);

            return response()->json([
                'data' => new PageResource($page)
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Page not found',
                'error' => config('app.debug') ? $e->getMessage() : 'Page not found'
            ], 404);
        }
    }

    /**
     * Update the specified page.
     */
    public function update(UpdatePageRequest $request, int $id): JsonResponse
    {
        try {
            $page = Page::findOrFail($id);
            $validatedData = $request->validated();
            $validatedData['updated_by'] = Auth::id();

            $wasPublished = $page->status === 'published';
            $page->update($validatedData);

            // Handle status changes
            if (!$wasPublished && $page->status === 'published') {
                $page->update(['published_at' => now()]);
                $this->runSeoAnalysis($page);
                event(new PagePublished($page));
            } elseif ($wasPublished && $page->status !== 'published') {
                event(new PageUnpublished($page));
            }

            return response()->json([
                'message' => 'Page updated successfully',
                'data' => new PageResource($page->fresh(['template', 'domain', 'language', 'creator']))
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to update page', [
                'page_id' => $id,
                'error' => $e->getMessage(),
                'user_id' => Auth::id()
            ]);

            return response()->json([
                'message' => 'Failed to update page',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Remove the specified page.
     */
    public function destroy(int $id): JsonResponse
    {
        try {
            $page = Page::findOrFail($id);
            
            if ($page->status === 'published') {
                event(new PageUnpublished($page));
            }
            
            $page->delete();

            return response()->json([
                'message' => 'Page deleted successfully'
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to delete page', [
                'page_id' => $id,
                'error' => $e->getMessage(),
                'user_id' => Auth::id()
            ]);

            return response()->json([
                'message' => 'Failed to delete page',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Publish a page.
     */
    public function publish(int $id): JsonResponse
    {
        try {
            $page = Page::findOrFail($id);
            
            $page->update([
                'status' => 'published',
                'published_at' => now(),
                'updated_by' => Auth::id()
            ]);

            $this->runSeoAnalysis($page);
            event(new PagePublished($page));

            return response()->json([
                'message' => 'Page published successfully',
                'data' => new PageResource($page->fresh(['template', 'domain', 'language']))
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to publish page', [
                'page_id' => $id,
                'error' => $e->getMessage(),
                'user_id' => Auth::id()
            ]);

            return response()->json([
                'message' => 'Failed to publish page',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Unpublish a page.
     */
    public function unpublish(int $id): JsonResponse
    {
        try {
            $page = Page::findOrFail($id);
            
            $page->update([
                'status' => 'draft',
                'updated_by' => Auth::id()
            ]);

            event(new PageUnpublished($page));

            return response()->json([
                'message' => 'Page unpublished successfully',
                'data' => new PageResource($page->fresh(['template', 'domain', 'language']))
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to unpublish page', [
                'page_id' => $id,
                'error' => $e->getMessage(),
                'user_id' => Auth::id()
            ]);

            return response()->json([
                'message' => 'Failed to unpublish page',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Preview a page.
     */
    public function preview(int $id): JsonResponse
    {
        try {
            $page = Page::with(['template', 'domain', 'language', 'personalizationRules'])
                        ->findOrFail($id);

            // Increment view count for analytics
            $page->incrementViewCount();

            return response()->json([
                'data' => [
                    'page' => new PageResource($page),
                    'preview_url' => $page->url,
                    'personalization_context' => $this->getPersonalizationContext($request)
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Page not found',
                'error' => config('app.debug') ? $e->getMessage() : 'Page not found'
            ], 404);
        }
    }

    /**
     * Duplicate a page.
     */
    public function duplicate(int $id): JsonResponse
    {
        try {
            $originalPage = Page::findOrFail($id);
            
            $newPage = $originalPage->replicate();
            $newPage->title = $originalPage->title . ' (Copy)';
            $newPage->slug = $originalPage->slug . '-copy-' . time();
            $newPage->status = 'draft';
            $newPage->published_at = null;
            $newPage->scheduled_at = null;
            $newPage->view_count = 0;
            $newPage->created_by = Auth::id();
            $newPage->updated_by = Auth::id();
            $newPage->save();

            return response()->json([
                'message' => 'Page duplicated successfully',
                'data' => new PageResource($newPage->load(['template', 'domain', 'language']))
            ], 201);

        } catch (\Exception $e) {
            Log::error('Failed to duplicate page', [
                'page_id' => $id,
                'error' => $e->getMessage(),
                'user_id' => Auth::id()
            ]);

            return response()->json([
                'message' => 'Failed to duplicate page',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Run SEO analysis on a page.
     */
    protected function runSeoAnalysis(Page $page): void
    {
        try {
            $this->seoAnalyzer->analyzePage($page);
        } catch (\Exception $e) {
            Log::warning('SEO analysis failed', [
                'page_id' => $page->id,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Get personalization context from request.
     */
    protected function getPersonalizationContext(Request $request): array
    {
        return [
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'referrer' => $request->header('referer'),
            'country' => $request->header('cf-ipcountry'), // Cloudflare country header
            'device' => $this->detectDevice($request->userAgent()),
            'user_id' => Auth::id(),
            'timestamp' => now()->toIso8601String()
        ];
    }

    /**
     * Simple device detection.
     */
    protected function detectDevice(string $userAgent): string
    {
        if (preg_match('/mobile|android|iphone|ipad/i', $userAgent)) {
            return 'mobile';
        }
        if (preg_match('/tablet|ipad/i', $userAgent)) {
            return 'tablet';
        }
        return 'desktop';
    }
}


