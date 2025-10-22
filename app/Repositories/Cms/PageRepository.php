<?php

namespace App\Repositories\Cms;

use App\Models\Cms\Page;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

class PageRepository
{
    /**
     * Get all pages with optional filters.
     */
    public function getPages(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $query = Page::with(['template', 'domain', 'language', 'creator']);

        // Apply filters
        if (isset($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (isset($filters['domain_id'])) {
            $query->where('domain_id', $filters['domain_id']);
        }

        if (isset($filters['language_id'])) {
            $query->where('language_id', $filters['language_id']);
        }

        if (isset($filters['template_id'])) {
            $query->where('template_id', $filters['template_id']);
        }

        if (isset($filters['search'])) {
            $search = $filters['search'];
            $query->where(function($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                  ->orWhere('meta_title', 'like', "%{$search}%")
                  ->orWhere('slug', 'like', "%{$search}%");
            });
        }

        // Apply sorting
        $sortBy = $filters['sort_by'] ?? 'updated_at';
        $sortOrder = $filters['sort_order'] ?? 'desc';
        
        if (in_array($sortBy, ['title', 'status', 'created_at', 'updated_at', 'published_at', 'view_count'])) {
            $query->orderBy($sortBy, $sortOrder);
        }

        return $query->paginate($perPage);
    }

    /**
     * Get published pages.
     */
    public function getPublishedPages(int $domainId = null, int $languageId = null): Collection
    {
        $query = Page::published()->with(['template', 'domain', 'language']);

        if ($domainId) {
            $query->where('domain_id', $domainId);
        }

        if ($languageId) {
            $query->where('language_id', $languageId);
        }

        return $query->orderBy('published_at', 'desc')->get();
    }

    /**
     * Find page by slug, domain, and language.
     */
    public function findBySlug(string $slug, int $domainId = null, int $languageId = null): ?Page
    {
        $query = Page::where('slug', $slug);

        if ($domainId) {
            $query->where('domain_id', $domainId);
        }

        if ($languageId) {
            $query->where('language_id', $languageId);
        }

        return $query->with(['template', 'domain', 'language', 'personalizationRules'])->first();
    }

    /**
     * Get pages scheduled for publishing.
     */
    public function getScheduledPages(): Collection
    {
        return Page::where('status', 'scheduled')
                  ->whereNotNull('scheduled_at')
                  ->where('scheduled_at', '<=', now())
                  ->get();
    }

    /**
     * Create a new page.
     */
    public function create(array $data): Page
    {
        return Page::create($data);
    }

    /**
     * Update a page.
     */
    public function update(Page $page, array $data): bool
    {
        return $page->update($data);
    }

    /**
     * Delete a page.
     */
    public function delete(Page $page): bool
    {
        return $page->delete();
    }

    /**
     * Get page statistics.
     */
    public function getStatistics(): array
    {
        return [
            'total_pages' => Page::count(),
            'published_pages' => Page::where('status', 'published')->count(),
            'draft_pages' => Page::where('status', 'draft')->count(),
            'scheduled_pages' => Page::where('status', 'scheduled')->count(),
            'archived_pages' => Page::where('status', 'archived')->count(),
            'total_views' => Page::sum('view_count'),
        ];
    }
}



