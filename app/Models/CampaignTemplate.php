<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\Traits\HasTenantScope;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CampaignTemplate extends Model
{
    use HasFactory, SoftDeletes, HasTenantScope;

    protected static function booted(): void
    {
        static::bootHasTenantScope();
    }

    protected $fillable = [
        'name',
        'description',
        'subject',
        'content',
        'type',
        'settings',
        'variables',
        'category',
        'is_active',
        'is_public',
        'usage_count',
        'tenant_id',
        'created_by',
    ];

    protected $casts = [
        'settings' => 'array',
        'variables' => 'array',
        'is_active' => 'boolean',
        'is_public' => 'boolean',
    ];

    /**
     * Get the tenant that owns the template.
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(User::class, 'tenant_id');
    }

    /**
     * Get the user who created the template.
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Scope a query to only include templates for a specific tenant.
     */
    public function scopeForTenant($query, $tenantId)
    {
        return $query->where('tenant_id', $tenantId);
    }

    /**
     * Scope a query to only include active templates.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope a query to only include public templates.
     */
    public function scopePublic($query)
    {
        return $query->where('is_public', true);
    }

    /**
     * Scope a query to filter by category.
     */
    public function scopeByCategory($query, $category)
    {
        return $query->where('category', $category);
    }

    /**
     * Scope a query to filter by type.
     */
    public function scopeByType($query, $type)
    {
        return $query->where('type', $type);
    }

    /**
     * Get available template categories.
     */
    public static function getAvailableCategories(): array
    {
        return [
            'newsletter' => 'Newsletter',
            'promotional' => 'Promotional',
            'transactional' => 'Transactional',
            'welcome' => 'Welcome',
            'follow_up' => 'Follow Up',
            'announcement' => 'Announcement',
            'event' => 'Event',
            'survey' => 'Survey',
        ];
    }

    /**
     * Get available template types.
     */
    public static function getAvailableTypes(): array
    {
        return [
            'email' => 'Email Template',
            'sms' => 'SMS Template',
        ];
    }

    /**
     * Increment usage count.
     */
    public function incrementUsage(): void
    {
        $this->increment('usage_count');
    }

    /**
     * Check if template is active.
     */
    public function isActive(): bool
    {
        return $this->is_active;
    }

    /**
     * Check if template is public.
     */
    public function isPublic(): bool
    {
        return $this->is_public;
    }
}

