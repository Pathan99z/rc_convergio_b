<?php

namespace App\Models\Cms;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class PageAccess extends Model
{
    use HasFactory;

    protected $table = 'cms_page_access';

    protected $fillable = [
        'page_id',
        'access_type',
        'access_rules',
        'require_login',
        'allowed_roles',
        'allowed_users',
        'access_from',
        'access_until',
        'is_active',
    ];

    protected $casts = [
        'access_rules' => 'array',
        'allowed_roles' => 'array',
        'allowed_users' => 'array',
        'require_login' => 'boolean',
        'is_active' => 'boolean',
        'access_from' => 'datetime',
        'access_until' => 'datetime',
    ];

    /**
     * Get the page this access rule belongs to.
     */
    public function page(): BelongsTo
    {
        return $this->belongsTo(Page::class);
    }

    /**
     * Scope to get active access rules.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Check if user has access based on this rule.
     */
    public function userHasAccess($user = null): bool
    {
        // Check if rule is active
        if (!$this->is_active) {
            return false;
        }

        // Check time-based access
        if ($this->access_from && now()->isBefore($this->access_from)) {
            return false;
        }

        if ($this->access_until && now()->isAfter($this->access_until)) {
            return false;
        }

        // Check access type
        switch ($this->access_type) {
            case 'public':
                return true;

            case 'members':
                return $user !== null;

            case 'role':
                if (!$user || !$this->allowed_roles) {
                    return false;
                }
                return $user->hasAnyRole($this->allowed_roles);

            case 'custom':
                if (!$user || !$this->allowed_users) {
                    return false;
                }
                return in_array($user->id, $this->allowed_users);

            default:
                return false;
        }
    }
}



