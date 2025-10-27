<?php

namespace App\Models\Cms;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Language extends Model
{
    use HasFactory;

    protected $table = 'cms_languages';

    protected $fillable = [
        'code',
        'name',
        'native_name',
        'is_default',
        'is_active',
        'flag_icon',
        'settings',
    ];

    protected $casts = [
        'settings' => 'array',
        'is_default' => 'boolean',
        'is_active' => 'boolean',
    ];

    /**
     * Get all pages for this language.
     */
    public function pages(): HasMany
    {
        return $this->hasMany(Page::class);
    }

    /**
     * Scope to get only active languages.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope to get default language.
     */
    public function scopeDefault($query)
    {
        return $query->where('is_default', true);
    }

    /**
     * Get display name with native name.
     */
    public function getDisplayNameAttribute(): string
    {
        return $this->native_name ? "{$this->name} ({$this->native_name})" : $this->name;
    }
}



