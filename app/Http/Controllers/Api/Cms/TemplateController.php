<?php

namespace App\Http\Controllers\Api\Cms;

use App\Http\Controllers\Controller;
use App\Http\Requests\Cms\StoreTemplateRequest;
use App\Http\Requests\Cms\UpdateTemplateRequest;
use App\Http\Resources\Cms\TemplateResource;
use App\Models\Cms\Template;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class TemplateController extends Controller
{
    /**
     * Display a listing of templates.
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $query = Template::with(['creator'])
                           ->when($request->filled('type'), fn($q) => $q->where('type', $request->type))
                           ->when($request->filled('is_system'), fn($q) => $q->where('is_system', $request->boolean('is_system')))
                           ->when($request->filled('search'), function($q) use ($request) {
                               $search = $request->search;
                               $q->where(function($query) use ($search) {
                                   $query->where('name', 'like', "%{$search}%")
                                         ->orWhere('description', 'like', "%{$search}%");
                               });
                           });

            if ($request->boolean('active_only', true)) {
                $query->active();
            }

            $sortBy = $request->get('sort_by', 'updated_at');
            $sortOrder = $request->get('sort_order', 'desc');
            
            if (in_array($sortBy, ['name', 'type', 'created_at', 'updated_at'])) {
                $query->orderBy($sortBy, $sortOrder);
            }

            $perPage = min($request->get('per_page', 20), 100);
            $templates = $query->paginate($perPage);

            return response()->json([
                'data' => TemplateResource::collection($templates->items()),
                'meta' => [
                    'current_page' => $templates->currentPage(),
                    'last_page' => $templates->lastPage(),
                    'per_page' => $templates->perPage(),
                    'total' => $templates->total(),
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to fetch templates', [
                'error' => $e->getMessage(),
                'user_id' => Auth::id()
            ]);

            return response()->json([
                'message' => 'Failed to fetch templates',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Store a newly created template.
     */
    public function store(StoreTemplateRequest $request): JsonResponse
    {
        try {
            $validatedData = $request->validated();
            $validatedData['created_by'] = Auth::id();
            $validatedData['updated_by'] = Auth::id();

            $template = Template::create($validatedData);

            return response()->json([
                'message' => 'Template created successfully',
                'data' => new TemplateResource($template->load(['creator']))
            ], 201);

        } catch (\Exception $e) {
            Log::error('Failed to create template', [
                'error' => $e->getMessage(),
                'user_id' => Auth::id(),
                'request_data' => $request->all()
            ]);

            return response()->json([
                'message' => 'Failed to create template',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Display the specified template.
     */
    public function show(int $id): JsonResponse
    {
        try {
            $template = Template::with(['creator', 'pages'])->findOrFail($id);

            return response()->json([
                'data' => new TemplateResource($template)
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Template not found',
                'error' => config('app.debug') ? $e->getMessage() : 'Template not found'
            ], 404);
        }
    }

    /**
     * Update the specified template.
     */
    public function update(UpdateTemplateRequest $request, int $id): JsonResponse
    {
        try {
            $template = Template::findOrFail($id);
            
            // Check if user can edit this template
            if ($template->is_system && !Auth::user()->hasRole('admin')) {
                return response()->json([
                    'message' => 'Cannot edit system templates'
                ], 403);
            }

            $validatedData = $request->validated();
            $validatedData['updated_by'] = Auth::id();

            $template->update($validatedData);

            return response()->json([
                'message' => 'Template updated successfully',
                'data' => new TemplateResource($template->fresh(['creator']))
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to update template', [
                'template_id' => $id,
                'error' => $e->getMessage(),
                'user_id' => Auth::id()
            ]);

            return response()->json([
                'message' => 'Failed to update template',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Remove the specified template.
     */
    public function destroy(int $id): JsonResponse
    {
        try {
            $template = Template::findOrFail($id);
            
            // Check if template is being used
            $pagesCount = $template->pages()->count();
            if ($pagesCount > 0) {
                return response()->json([
                    'success' => false,
                    'message' => "Cannot delete template. It is being used by {$pagesCount} page(s)."
                ], 422);
            }

            // Check if user can delete this template
            if ($template->is_system && !Auth::user()->hasRole('admin')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot delete system templates'
                ], 403);
            }

            $template->delete();

            return response()->json([
                'message' => 'Template deleted successfully'
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to delete template', [
                'template_id' => $id,
                'error' => $e->getMessage(),
                'user_id' => Auth::id()
            ]);

            return response()->json([
                'message' => 'Failed to delete template',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Get template types.
     */
    public function types(): JsonResponse
    {
        return response()->json([
            'data' => [
                ['value' => 'page', 'label' => 'Standard Page'],
                ['value' => 'landing', 'label' => 'Landing Page'],
                ['value' => 'blog', 'label' => 'Blog Post'],
                ['value' => 'email', 'label' => 'Email Template'],
                ['value' => 'popup', 'label' => 'Popup/Modal'],
            ]
        ]);
    }
}



