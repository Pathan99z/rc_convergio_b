<?php

namespace App\Models\Service;

use App\Models\Traits\HasTenantScope;
use App\Models\User;
use App\Models\Contact;
use App\Models\Service\Ticket;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Survey extends Model
{
    use HasFactory, HasTenantScope;

    protected $fillable = [
        'tenant_id',
        'name',
        'description',
        'type',
        'is_active',
        'auto_send',
        'settings',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'auto_send' => 'boolean',
        'settings' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::bootHasTenantScope();
    }

    /**
     * Get the tenant that owns the survey.
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(User::class, 'tenant_id');
    }

    /**
     * Get the questions for the survey.
     */
    public function questions(): HasMany
    {
        return $this->hasMany(SurveyQuestion::class)->orderBy('order');
    }

    /**
     * Get the responses for the survey.
     */
    public function responses(): HasMany
    {
        return $this->hasMany(SurveyResponse::class);
    }

    /**
     * Scope a query to only include active surveys.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope a query to only include surveys of a specific type.
     */
    public function scopeOfType($query, string $type)
    {
        return $query->where('type', $type);
    }

    /**
     * Get the average score for this survey.
     */
    public function getAverageScoreAttribute(): float
    {
        return $this->responses()->avg('overall_score') ?? 0.0;
    }

    /**
     * Get the response count for this survey.
     */
    public function getResponseCountAttribute(): int
    {
        return $this->responses()->count();
    }
}