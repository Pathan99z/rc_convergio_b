<?php

namespace App\Models\Service;

use App\Models\Contact;
use App\Models\Service\Ticket;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SurveyResponse extends Model
{
    use HasFactory;

    protected $fillable = [
        'survey_id',
        'contact_id',
        'ticket_id',
        'respondent_email',
        'responses',
        'overall_score',
        'feedback',
        'ip_address',
        'user_agent',
    ];

    protected $casts = [
        'responses' => 'array',
        'overall_score' => 'decimal:2',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Get the survey that owns the response.
     */
    public function survey(): BelongsTo
    {
        return $this->belongsTo(Survey::class);
    }

    /**
     * Get the contact associated with the response.
     */
    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    /**
     * Get the ticket associated with the response.
     */
    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class);
    }

    /**
     * Scope a query to only include responses with scores.
     */
    public function scopeWithScores($query)
    {
        return $query->whereNotNull('overall_score');
    }

    /**
     * Scope a query to only include responses for a specific survey.
     */
    public function scopeForSurvey($query, int $surveyId)
    {
        return $query->where('survey_id', $surveyId);
    }
}