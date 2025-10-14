<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\Traits\HasTenantScope;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;

class Document extends Model
{
    use HasFactory, SoftDeletes, HasTenantScope;

    protected $fillable = [
        'tenant_id',
        'team_id',
        'owner_id',
        'related_type',
        'related_id',
        'title',
        'description',
        'file_path',
        'file_type',
        'file_size',
        'visibility',
        'view_count',
        'last_viewed_at',
        'metadata',
        'created_by',
    ];

    protected $casts = [
        'metadata' => 'array',
        'last_viewed_at' => 'datetime',
        'view_count' => 'integer',
        'file_size' => 'integer',
    ];

    protected static function booted(): void
    {
        static::bootHasTenantScope();
    }

    /**
     * Get the tenant that owns the document.
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(User::class, 'tenant_id');
    }

    /**
     * Get the team that owns the document.
     */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class, 'team_id');
    }

    /**
     * Get the owner of the document.
     */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    /**
     * Get the user who created the document.
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get the related model (deal, quote, contact, etc.) - DEPRECATED, use relationships() instead.
     */
    public function related(): MorphTo
    {
        return $this->morphTo('related', 'related_type', 'related_id');
    }

    /**
     * Get all relationships for this document (many-to-many).
     */
    public function relationships()
    {
        return $this->hasMany(DocumentRelationship::class);
    }

    /**
     * Get all contacts linked to this document.
     */
    public function contacts()
    {
        return $this->relationships()->where('related_type', 'App\\Models\\Contact');
    }

    /**
     * Get all deals linked to this document.
     */
    public function deals()
    {
        return $this->relationships()->where('related_type', 'App\\Models\\Deal');
    }

    /**
     * Get all companies linked to this document.
     */
    public function companies()
    {
        return $this->relationships()->where('related_type', 'App\\Models\\Company');
    }

    /**
     * Get all quotes linked to this document.
     */
    public function quotes()
    {
        return $this->relationships()->where('related_type', 'App\\Models\\Quote');
    }

    /**
     * Get the public URL for the document.
     * Returns a signed URL if the document is private.
     */
    public function getPublicUrlAttribute(): string
    {
        if ($this->visibility === 'private') {
            return URL::signedRoute('documents.download', ['document' => $this->id], now()->addHours(1));
        }

        return Storage::url($this->file_path);
    }

    /**
     * Get the file extension from the file path.
     */
    public function getFileExtensionAttribute(): string
    {
        return pathinfo($this->file_path, PATHINFO_EXTENSION);
    }

    /**
     * Get the human readable file size.
     */
    public function getHumanFileSizeAttribute(): string
    {
        if (!$this->file_size) {
            return 'Unknown';
        }

        $bytes = $this->file_size;
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        
        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }
        
        return round($bytes, 2) . ' ' . $units[$i];
    }

    /**
     * Check if the document is an image.
     */
    public function isImage(): bool
    {
        $imageTypes = ['jpg', 'jpeg', 'png', 'gif', 'bmp', 'webp', 'svg'];
        return in_array(strtolower($this->file_extension), $imageTypes);
    }

    /**
     * Check if the document is a PDF.
     */
    public function isPdf(): bool
    {
        return strtolower($this->file_extension) === 'pdf';
    }

    /**
     * Scope a query to only include documents for a specific tenant.
     */
    public function scopeForTenant($query, $tenantId)
    {
        return $query->where('tenant_id', $tenantId);
    }

    /**
     * Scope a query to only include documents for a specific team.
     */
    public function scopeForTeam($query, $teamId)
    {
        return $query->where('team_id', $teamId);
    }

    /**
     * Scope a query to only include documents with specific visibility.
     */
    public function scopeWithVisibility($query, $visibility)
    {
        return $query->where('visibility', $visibility);
    }

    /**
     * Scope a query to only include documents related to a specific model.
     */
    public function scopeRelatedTo($query, $relatedType, $relatedId)
    {
        return $query->where('related_type', $relatedType)
                    ->where('related_id', $relatedId);
    }

    /**
     * Scope a query to search documents by title.
     */
    public function scopeSearch($query, $search)
    {
        return $query->where('title', 'like', "%{$search}%");
    }

    /**
     * Scope a query to filter by file type.
     */
    public function scopeByFileType($query, $fileType)
    {
        return $query->where('file_type', $fileType);
    }

    /**
     * Increment the view count and update last viewed timestamp.
     */
    public function recordView(): void
    {
        $this->increment('view_count');
        $this->update(['last_viewed_at' => now()]);
    }
}
