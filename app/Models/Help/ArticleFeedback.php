<?php

namespace App\Models\Help;

use App\Models\Traits\HasTenantScope;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ArticleFeedback extends Model
{
    use HasFactory, HasTenantScope;

    /**
     * The table associated with the model.
     */
    protected $table = 'help_article_feedback';

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'tenant_id',
        'article_id',
        'contact_email',
        'contact_name',
        'feedback',
        'user_id',
    ];

    /**
     * The attributes that should be cast.
     */
    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Get the article that owns the feedback.
     */
    public function article(): BelongsTo
    {
        return $this->belongsTo(Article::class);
    }

    /**
     * Get the user who submitted the feedback (if authenticated).
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Scope to get helpful feedback.
     */
    public function scopeHelpful($query)
    {
        return $query->where('feedback', 'helpful');
    }

    /**
     * Scope to get not helpful feedback.
     */
    public function scopeNotHelpful($query)
    {
        return $query->where('feedback', 'not_helpful');
    }
}
