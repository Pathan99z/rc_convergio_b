<?php

namespace App\Repositories\Cms;

use App\Models\Cms\Template;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

class TemplateRepository
{
    /**
     * Get all templates with optional filters.
     */
    public function getTemplates(array $filters = [], int $perPage = 20): LengthAwarePaginator
    {
        $query = Template::with(['creator']);

        // Apply filters
        if (isset($filters['type'])) {
            $query->where('type', $filters['type']);
        }

        if (isset($filters['is_system'])) {
            $query->where('is_system', $filters['is_system']);
        }

        if (isset($filters['search'])) {
            $search = $filters['search'];
            $query->where(function($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%");
            });
        }

        if ($filters['active_only'] ?? true) {
            $query->active();
        }

        // Apply sorting
        $sortBy = $filters['sort_by'] ?? 'updated_at';
        $sortOrder = $filters['sort_order'] ?? 'desc';
        
        if (in_array($sortBy, ['name', 'type', 'created_at', 'updated_at'])) {
            $query->orderBy($sortBy, $sortOrder);
        }

        return $query->paginate($perPage);
    }

    /**
     * Get active templates by type.
     */
    public function getByType(string $type): Collection
    {
        return Template::active()
                      ->where('type', $type)
                      ->orderBy('name')
                      ->get();
    }

    /**
     * Get system templates.
     */
    public function getSystemTemplates(): Collection
    {
        return Template::system()->active()->orderBy('name')->get();
    }

    /**
     * Get user templates.
     */
    public function getUserTemplates(int $userId = null): Collection
    {
        $query = Template::user()->active();

        if ($userId) {
            $query->where('created_by', $userId);
        }

        return $query->orderBy('name')->get();
    }

    /**
     * Create a new template.
     */
    public function create(array $data): Template
    {
        return Template::create($data);
    }

    /**
     * Update a template.
     */
    public function update(Template $template, array $data): bool
    {
        return $template->update($data);
    }

    /**
     * Delete a template.
     */
    public function delete(Template $template): bool
    {
        return $template->delete();
    }

    /**
     * Check if template is being used.
     */
    public function isBeingUsed(Template $template): bool
    {
        return $template->pages()->count() > 0;
    }

    /**
     * Get template usage statistics.
     */
    public function getUsageStatistics(Template $template): array
    {
        return [
            'total_pages' => $template->pages()->count(),
            'published_pages' => $template->pages()->where('status', 'published')->count(),
            'draft_pages' => $template->pages()->where('status', 'draft')->count(),
            'last_used' => $template->pages()->latest('updated_at')->first()?->updated_at,
        ];
    }
}



