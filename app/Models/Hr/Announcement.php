<?php

namespace App\Models\Hr;

use App\Models\Traits\HasTenantScope;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Announcement extends Model
{
    use HasFactory, SoftDeletes, HasTenantScope;

    protected $table = 'hr_announcements';

    protected $fillable = [
        'tenant_id',
        'title',
        'category',
        'message',
        'attachment_url',
        'target_audience_type',
        'target_departments',
        'target_employee_ids',
        'is_mandatory',
        'priority',
        'status',
        'scheduled_publish_at',
        'published_at',
        'published_by',
        'created_by',
    ];

    protected $casts = [
        'target_departments' => 'array',
        'target_employee_ids' => 'array',
        'is_mandatory' => 'boolean',
        'scheduled_publish_at' => 'datetime',
        'published_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::bootHasTenantScope();
    }

    /**
     * Get the tenant that owns the announcement.
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(User::class, 'tenant_id');
    }

    /**
     * Get the user who created the announcement.
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get the user who published the announcement.
     */
    public function publishedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'published_by');
    }

    /**
     * Get all views for this announcement.
     */
    public function views(): HasMany
    {
        return $this->hasMany(AnnouncementView::class, 'announcement_id');
    }

    /**
     * Get all acknowledgments for this announcement.
     */
    public function acknowledgments(): HasMany
    {
        return $this->hasMany(AnnouncementAcknowledgment::class, 'announcement_id');
    }

    /**
     * Get all likes for this announcement.
     */
    public function likes(): HasMany
    {
        return $this->hasMany(AnnouncementLike::class, 'announcement_id');
    }

    /**
     * Get all comments for this announcement.
     */
    public function comments(): HasMany
    {
        return $this->hasMany(AnnouncementComment::class, 'announcement_id');
    }

    /**
     * Scope a query to only include published announcements.
     */
    public function scopePublished($query)
    {
        return $query->where('status', 'published');
    }

    /**
     * Scope a query to only include draft announcements.
     */
    public function scopeDraft($query)
    {
        return $query->where('status', 'draft');
    }

    /**
     * Scope a query to only include archived announcements.
     */
    public function scopeArchived($query)
    {
        return $query->where('status', 'archived');
    }

    /**
     * Scope a query to only include mandatory announcements.
     */
    public function scopeMandatory($query)
    {
        return $query->where('is_mandatory', true);
    }

    /**
     * Check if announcement is viewed by employee.
     */
    public function isViewedBy(int $employeeId): bool
    {
        return $this->views()->where('employee_id', $employeeId)->exists();
    }

    /**
     * Check if announcement is acknowledged by employee.
     */
    public function isAcknowledgedBy(int $employeeId): bool
    {
        return $this->acknowledgments()->where('employee_id', $employeeId)->exists();
    }

    /**
     * Check if announcement is liked by employee.
     */
    public function isLikedBy(int $employeeId): bool
    {
        return $this->likes()->where('employee_id', $employeeId)->exists();
    }

    /**
     * Get the attachment URL (converts storage path to API URL if needed).
     */
    public function getAttachmentUrlAttribute($value)
    {
        if (!$value) {
            return null;
        }
        
        // If it's already a full URL (API route), return it
        if (str_starts_with($value, 'http')) {
            return $value;
        }
        
        // If it's a storage path (e.g., "announcements/1/filename.png"), convert to API URL
        if (str_starts_with($value, 'announcements/')) {
            return url("/api/hr/announcements/{$this->id}/attachment");
        }
        
        // If it's already an API URL path, make it full URL
        if (str_starts_with($value, '/api/')) {
            return url($value);
        }
        
        // Otherwise return as-is (for backward compatibility)
        return $value;
    }
}

