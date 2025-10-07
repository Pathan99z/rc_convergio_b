<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\Traits\HasTenantScope;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class QuoteTemplate extends Model
{
    use HasFactory, SoftDeletes, HasTenantScope;

    protected $fillable = [
        'name',
        'description',
        'layout',
        'is_default',
        'tenant_id',
        'created_by',
    ];

    protected $casts = [
        'is_default' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::bootHasTenantScope();
        
        // Ensure only one default template per tenant
        static::creating(function ($template) {
            if ($template->is_default) {
                static::where('tenant_id', $template->tenant_id)
                    ->update(['is_default' => false]);
            }
        });
        
        static::updating(function ($template) {
            if ($template->is_default) {
                static::where('tenant_id', $template->tenant_id)
                    ->where('id', '!=', $template->id)
                    ->update(['is_default' => false]);
            }
        });
    }

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
     * Get the quotes that use this template.
     */
    public function quotes(): HasMany
    {
        return $this->hasMany(Quote::class, 'template_id');
    }

    /**
     * Scope a query to only include templates for a specific tenant.
     */
    public function scopeForTenant($query, $tenantId)
    {
        return $query->where('tenant_id', $tenantId);
    }

    /**
     * Scope a query to only include default templates.
     */
    public function scopeDefault($query)
    {
        return $query->where('is_default', true);
    }

    /**
     * Scope a query to filter by layout.
     */
    public function scopeByLayout($query, $layout)
    {
        return $query->where('layout', $layout);
    }

    /**
     * Get available layout options.
     */
    public static function getLayoutOptions(): array
    {
        return [
            'default' => 'Default',
            'classic' => 'Classic',
            'modern' => 'Modern',
            'minimal' => 'Minimal',
        ];
    }

    /**
     * Get the layout display name.
     */
    public function getLayoutDisplayAttribute(): string
    {
        return static::getLayoutOptions()[$this->layout] ?? ucfirst($this->layout);
    }
}
