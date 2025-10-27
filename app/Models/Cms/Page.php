<?php

namespace App\Models\Cms;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Page extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'cms_pages';

    protected $fillable = [
        'title',
        'slug',
        'meta_title',
        'meta_description',
        'meta_keywords',
        'status',
        'json_content',
        'template_id',
        'domain_id',
        'language_id',
        'created_by',
        'updated_by',
        'published_at',
        'scheduled_at',
        'seo_data',
        'view_count',
        'settings',
    ];

    protected $casts = [
        'meta_keywords' => 'array',
        'json_content' => 'array',
        'seo_data' => 'array',
        'settings' => 'array',
        'published_at' => 'datetime',
        'scheduled_at' => 'datetime',
    ];

    /**
     * Get the template for this page.
     */
    public function template(): BelongsTo
    {
        return $this->belongsTo(Template::class);
    }

    /**
     * Get the domain for this page.
     */
    public function domain(): BelongsTo
    {
        return $this->belongsTo(Domain::class);
    }

    /**
     * Get the language for this page.
     */
    public function language(): BelongsTo
    {
        return $this->belongsTo(Language::class);
    }

    /**
     * Get the user who created this page.
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get the user who last updated this page.
     */
    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /**
     * Get personalization rules for this page.
     */
    public function personalizationRules(): HasMany
    {
        return $this->hasMany(PersonalizationRule::class);
    }

    /**
     * Get SEO logs for this page.
     */
    public function seoLogs(): HasMany
    {
        return $this->hasMany(SeoLog::class);
    }

    /**
     * Get A/B tests for this page.
     */
    public function abTests(): HasMany
    {
        return $this->hasMany(ABTest::class);
    }

    /**
     * Get page access rules.
     */
    public function accessRules(): HasMany
    {
        return $this->hasMany(PageAccess::class);
    }

    /**
     * Scope to get published pages.
     */
    public function scopePublished($query)
    {
        return $query->where('status', 'published')
                    ->where(function ($q) {
                        $q->whereNull('published_at')
                          ->orWhere('published_at', '<=', now());
                    });
    }

    /**
     * Scope to get pages by status.
     */
    public function scopeWithStatus($query, string $status)
    {
        return $query->where('status', $status);
    }

    /**
     * Scope to get pages for a specific domain.
     */
    public function scopeForDomain($query, int $domainId)
    {
        return $query->where('domain_id', $domainId);
    }

    /**
     * Scope to get pages for a specific language.
     */
    public function scopeForLanguage($query, int $languageId)
    {
        return $query->where('language_id', $languageId);
    }

    /**
     * Check if page is published.
     */
    public function isPublished(): bool
    {
        return $this->status === 'published' && 
               ($this->published_at === null || $this->published_at->isPast());
    }

    /**
     * Check if page is scheduled.
     */
    public function isScheduled(): bool
    {
        return $this->status === 'scheduled' && 
               $this->scheduled_at && 
               $this->scheduled_at->isFuture();
    }

    /**
     * Get page URL.
     */
    public function getUrlAttribute(): string
    {
        $domain = $this->domain ? $this->domain->url : config('app.url');
        $languagePrefix = $this->language && !$this->language->is_default ? '/' . $this->language->code : '';
        
        return "{$domain}{$languagePrefix}/{$this->slug}";
    }

    /**
     * Increment view count.
     */
    public function incrementViewCount(): void
    {
        $this->increment('view_count');
    }

    /**
     * Generate slug from title.
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($page) {
            if (empty($page->slug)) {
                $page->slug = Str::slug($page->title);
            }
        });

        static::updating(function ($page) {
            if ($page->isDirty('title') && empty($page->slug)) {
                $page->slug = Str::slug($page->title);
            }
        });
    }
}



