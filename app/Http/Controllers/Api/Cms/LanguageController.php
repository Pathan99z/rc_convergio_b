<?php

namespace App\Http\Controllers\Api\Cms;

use App\Http\Controllers\Controller;
use App\Http\Resources\Cms\LanguageResource;
use App\Models\Cms\Language;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class LanguageController extends Controller
{
    /**
     * Display a listing of languages.
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $query = Language::query();

            if ($request->boolean('active_only', true)) {
                $query->active();
            }

            $languages = $query->orderBy('is_default', 'desc')
                              ->orderBy('name')
                              ->get();

            return response()->json([
                'success' => true,
                'data' => LanguageResource::collection($languages)
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to fetch languages', [
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch languages',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Store a newly created language.
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'code' => 'required|string|max:10|unique:cms_languages,code',
            'name' => 'required|string|max:255',
            'native_name' => 'nullable|string|max:255',
            'is_default' => 'nullable|boolean',
            'flag_icon' => 'nullable|string|max:100',
            'settings' => 'nullable|array'
        ]);

        try {
            // If setting as default, unset other default languages
            if ($request->boolean('is_default')) {
                Language::where('is_default', true)->update(['is_default' => false]);
            }

            $language = Language::create($request->validated());

            return response()->json([
                'success' => true,
                'message' => 'Language created successfully',
                'data' => new LanguageResource($language)
            ], 201);

        } catch (\Exception $e) {
            Log::error('Failed to create language', [
                'error' => $e->getMessage(),
                'request_data' => $request->all()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to create language',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Display the specified language.
     */
    public function show(int $id): JsonResponse
    {
        try {
            $language = Language::withCount('pages')->findOrFail($id);

            return response()->json([
                'success' => true,
                'data' => new LanguageResource($language)
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Language not found',
                'error' => config('app.debug') ? $e->getMessage() : 'Language not found'
            ], 404);
        }
    }

    /**
     * Update the specified language.
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'code' => 'sometimes|string|max:10|unique:cms_languages,code,' . $id,
            'name' => 'sometimes|string|max:255',
            'native_name' => 'nullable|string|max:255',
            'is_default' => 'sometimes|boolean',
            'is_active' => 'sometimes|boolean',
            'flag_icon' => 'nullable|string|max:100',
            'settings' => 'nullable|array'
        ]);

        try {
            $language = Language::findOrFail($id);

            // If setting as default, unset other default languages
            if ($request->boolean('is_default') && !$language->is_default) {
                Language::where('is_default', true)->update(['is_default' => false]);
            }

            $language->update($request->validated());

            return response()->json([
                'success' => true,
                'message' => 'Language updated successfully',
                'data' => new LanguageResource($language->fresh())
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to update language', [
                'language_id' => $id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to update language',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Remove the specified language.
     */
    public function destroy(int $id): JsonResponse
    {
        try {
            $language = Language::findOrFail($id);

            // Check if language has pages
            $pagesCount = $language->pages()->count();
            if ($pagesCount > 0) {
                return response()->json([
                    'success' => false,
                    'message' => "Cannot delete language. It has {$pagesCount} page(s)."
                ], 422);
            }

            // Don't allow deleting default language
            if ($language->is_default) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot delete default language.'
                ], 422);
            }

            $language->delete();

            return response()->json([
                'success' => true,
                'message' => 'Language deleted successfully'
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to delete language', [
                'language_id' => $id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to delete language',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }
}



