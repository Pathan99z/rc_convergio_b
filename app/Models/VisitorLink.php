<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class VisitorLink extends Model
{
    use HasFactory;

    protected $fillable = [
        'visitor_id',
        'contact_id',
        'linked_at',
    ];

    protected $casts = [
        'linked_at' => 'datetime',
    ];

    /**
     * Get the visitor that owns this link.
     */
    public function visitor(): BelongsTo
    {
        return $this->belongsTo(Visitor::class);
    }

    /**
     * Get the contact that is linked.
     */
    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    /**
     * Scope a query to only include links for a specific visitor.
     */
    public function scopeForVisitor($query, $visitorId)
    {
        return $query->where('visitor_id', $visitorId);
    }

    /**
     * Scope a query to only include links for a specific contact.
     */
    public function scopeForContact($query, $contactId)
    {
        return $query->where('contact_id', $contactId);
    }

    /**
     * Scope a query to only include links created within a date range.
     */
    public function scopeLinkedBetween($query, $startDate, $endDate)
    {
        return $query->whereBetween('linked_at', [$startDate, $endDate]);
    }

    /**
     * Get the age of the link in days.
     */
    public function getAgeInDaysAttribute(): int
    {
        return $this->linked_at->diffInDays(now());
    }

    /**
     * Check if the link is recent (within specified days).
     */
    public function isRecent(int $days = 7): bool
    {
        return $this->linked_at->isAfter(now()->subDays($days));
    }

    /**
     * Get link statistics for a tenant.
     */
    public static function getStatsForTenant(int $tenantId): array
    {
        // Get links through visitor relationship
        $totalLinks = self::whereHas('visitor', function($query) use ($tenantId) {
            $query->where('tenant_id', $tenantId);
        })->count();

        $recentLinks = self::whereHas('visitor', function($query) use ($tenantId) {
            $query->where('tenant_id', $tenantId);
        })->where('linked_at', '>=', now()->subDays(7))->count();

        $uniqueContacts = self::whereHas('visitor', function($query) use ($tenantId) {
            $query->where('tenant_id', $tenantId);
        })->distinct('contact_id')->count();

        $uniqueVisitors = self::whereHas('visitor', function($query) use ($tenantId) {
            $query->where('tenant_id', $tenantId);
        })->distinct('visitor_id')->count();

        return [
            'total_links' => $totalLinks,
            'recent_links' => $recentLinks,
            'unique_contacts' => $uniqueContacts,
            'unique_visitors' => $uniqueVisitors,
            'average_links_per_contact' => $uniqueContacts > 0 ? round($totalLinks / $uniqueContacts, 2) : 0,
        ];
    }

    /**
     * Get the most linked contacts for a tenant.
     */
    public static function getTopLinkedContacts(int $tenantId, int $limit = 10): array
    {
        return self::whereHas('visitor', function($query) use ($tenantId) {
            $query->where('tenant_id', $tenantId);
        })
        ->selectRaw('contact_id, COUNT(*) as link_count, MAX(linked_at) as last_linked')
        ->groupBy('contact_id')
        ->orderBy('link_count', 'desc')
        ->limit($limit)
        ->with('contact:id,first_name,last_name,email')
        ->get()
        ->toArray();
    }

    /**
     * Clean up old links (older than specified days).
     */
    public static function cleanupOldLinks(int $daysOld = 365): int
    {
        return self::where('linked_at', '<', now()->subDays($daysOld))
            ->delete();
    }
}
