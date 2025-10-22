<?php

namespace App\Models\Cms;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Domain extends Model
{
    use HasFactory;

    protected $table = 'cms_domains';

    protected $fillable = [
        'name',
        'display_name',
        'ssl_status',
        'is_primary',
        'is_active',
        'settings',
        'verified_at',
    ];

    protected $casts = [
        'settings' => 'array',
        'is_primary' => 'boolean',
        'is_active' => 'boolean',
        'verified_at' => 'datetime',
    ];

    /**
     * Get all pages for this domain.
     */
    public function pages(): HasMany
    {
        return $this->hasMany(Page::class);
    }

    /**
     * Scope to get only active domains.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope to get primary domain.
     */
    public function scopePrimary($query)
    {
        return $query->where('is_primary', true);
    }

    /**
     * Check if domain has SSL enabled.
     */
    public function hasSsl(): bool
    {
        return $this->ssl_status === 'active';
    }

    /**
     * Get domain URL with protocol.
     */
    public function getUrlAttribute(): string
    {
        $protocol = $this->hasSsl() ? 'https' : 'http';
        return "{$protocol}://{$this->name}";
    }
}



