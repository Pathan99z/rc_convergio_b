<?php

namespace App\Models\Cms;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class SeoLog extends Model
{
    use HasFactory;

    protected $table = 'cms_seo_logs';

    protected $fillable = [
        'page_id',
        'analysis_results',
        'seo_score',
        'issues_found',
        'recommendations',
        'keywords_analysis',
        'analyzed_at',
        'analyzer_version',
    ];

    protected $casts = [
        'analysis_results' => 'array',
        'issues_found' => 'array',
        'recommendations' => 'array',
        'keywords_analysis' => 'array',
        'analyzed_at' => 'datetime',
    ];

    /**
     * Get the page this SEO log belongs to.
     */
    public function page(): BelongsTo
    {
        return $this->belongsTo(Page::class);
    }

    /**
     * Scope to get logs for a specific page.
     */
    public function scopeForPage($query, int $pageId)
    {
        return $query->where('page_id', $pageId);
    }

    /**
     * Scope to get recent logs.
     */
    public function scopeRecent($query, int $days = 30)
    {
        return $query->where('analyzed_at', '>=', now()->subDays($days));
    }

    /**
     * Get SEO grade based on score.
     */
    public function getSeoGradeAttribute(): string
    {
        if ($this->seo_score >= 90) return 'A+';
        if ($this->seo_score >= 80) return 'A';
        if ($this->seo_score >= 70) return 'B';
        if ($this->seo_score >= 60) return 'C';
        if ($this->seo_score >= 50) return 'D';
        return 'F';
    }

    /**
     * Get critical issues count.
     */
    public function getCriticalIssuesCountAttribute(): int
    {
        return collect($this->issues_found)->where('severity', 'critical')->count();
    }
}



