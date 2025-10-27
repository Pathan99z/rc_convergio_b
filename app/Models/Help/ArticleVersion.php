<?php

namespace App\Models\Help;

use App\Models\Traits\HasTenantScope;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ArticleVersion extends Model
{
    use HasFactory, HasTenantScope;

    protected $table = 'help_article_versions';

    protected $fillable = [
        'tenant_id',
        'article_id',
        'version_number',
        'title',
        'summary',
        'content',
        'status',
        'published_at',
        'created_by',
        'change_reason',
    ];

    protected $casts = [
        'published_at' => 'datetime',
    ];

    /**
     * Get the article that owns this version.
     */
    public function article(): BelongsTo
    {
        return $this->belongsTo(Article::class, 'article_id');
    }

    /**
     * Get the user who created this version.
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Scope to get the latest version of an article.
     */
    public function scopeLatest($query)
    {
        return $query->orderBy('version_number', 'desc');
    }

    /**
     * Scope to get versions for a specific article.
     */
    public function scopeForArticle($query, int $articleId)
    {
        return $query->where('article_id', $articleId);
    }
}
