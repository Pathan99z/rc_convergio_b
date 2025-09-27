<?php

namespace App\Services;

use App\Models\Visitor;
use App\Models\VisitorSession;
use App\Models\IntentEvent;
use App\Models\Contact;
use App\Models\Company;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Carbon\Carbon;

class VisitorTrackingService
{
    /**
     * Find or create visitor with proper deduplication.
     */
    public function findOrCreateVisitor(array $trackingData, int $tenantId): array
    {
        try {
            $sessionId = $trackingData['session_id'] ?? null;
            $rcVid = $trackingData['rc_vid'] ?? null;
            $ipAddress = request()->ip();
            $userAgent = request()->userAgent();
            
            // Step 1: Try to find existing visitor by session_id (most reliable)
            if ($sessionId) {
                $visitorSession = VisitorSession::where('session_id', $sessionId)
                    ->where('tenant_id', $tenantId)
                    ->first();
                
                if ($visitorSession) {
                    $visitor = $visitorSession->visitor;
                    $this->updateVisitorLastSeen($visitor);
                    return [
                        'visitor' => $visitor,
                        'session' => $visitorSession,
                        'is_new' => false,
                        'method' => 'session_match'
                    ];
                }
            }
            
            // Step 2: Try to find visitor by IP + User Agent (same device) - more reliable than rc_vid
            $visitor = $this->findVisitorByDevice($ipAddress, $userAgent, $tenantId);
            if ($visitor) {
                $this->createOrUpdateSession($visitor, $sessionId, $tenantId);
                $this->updateVisitorLastSeen($visitor);
                return [
                    'visitor' => $visitor,
                    'session' => $visitor->sessions()->where('session_id', $sessionId)->first(),
                    'is_new' => false,
                    'method' => 'device_match'
                ];
            }
            
            // Step 3: Try to find visitor by rc_vid (returning visitor)
            if ($rcVid) {
                $existingEvent = IntentEvent::where('tenant_id', $tenantId)
                    ->whereJsonContains('event_data->rc_vid', $rcVid)
                    ->orderBy('created_at', 'desc')
                    ->first();
                
                if ($existingEvent) {
                    $visitor = $this->getVisitorFromEvent($existingEvent);
                    if ($visitor) {
                        $this->createOrUpdateSession($visitor, $sessionId, $tenantId);
                        $this->updateVisitorLastSeen($visitor);
                        return [
                            'visitor' => $visitor,
                            'session' => $visitor->sessions()->where('session_id', $sessionId)->first(),
                            'is_new' => false,
                            'method' => 'rc_vid_match'
                        ];
                    }
                }
            }
            
            // Step 4: Create new visitor
            $visitor = $this->createNewVisitor($tenantId);
            $session = $this->createOrUpdateSession($visitor, $sessionId, $tenantId);
            
            return [
                'visitor' => $visitor,
                'session' => $session,
                'is_new' => true,
                'method' => 'new_visitor'
            ];
            
        } catch (\Exception $e) {
            Log::error('Visitor tracking failed', [
                'tenant_id' => $tenantId,
                'tracking_data' => $trackingData,
                'error' => $e->getMessage()
            ]);
            
            // Fallback: create new visitor
            $visitor = $this->createNewVisitor($tenantId);
            $session = $this->createOrUpdateSession($visitor, $sessionId, $tenantId);
            
            return [
                'visitor' => $visitor,
                'session' => $session,
                'is_new' => true,
                'method' => 'fallback'
            ];
        }
    }
    
    /**
     * Calculate cumulative intent score for visitor.
     */
    public function calculateCumulativeScore(Visitor $visitor, int $newScore, string $action): int
    {
        try {
            // Get all events for this visitor in the last 30 days using session_id
            $sessions = $visitor->sessions()->pluck('session_id');
            
            if ($sessions->isEmpty()) {
                return $newScore; // No previous sessions, return new score
            }
            
            $recentEvents = IntentEvent::where('tenant_id', $visitor->tenant_id)
                ->whereIn('session_id', $sessions)
                ->where('created_at', '>=', now()->subDays(30))
                ->get();
            
            // Calculate base cumulative score (sum of all previous scores + new score)
            $cumulativeScore = $recentEvents->sum('intent_score') + $newScore;
            
            // Apply return visitor bonus
            $visitCount = $recentEvents->count();
            if ($visitCount > 0) {
                $returnVisitorBonus = min($visitCount * 3, 20); // Max 20 points bonus
                $cumulativeScore += $returnVisitorBonus;
            }
            
            // Apply engagement multiplier
            $engagementMultiplier = $this->calculateEngagementMultiplier($visitor);
            $cumulativeScore = (int) ($cumulativeScore * $engagementMultiplier);
            
            // Cap at 100
            return min($cumulativeScore, 100);
            
        } catch (\Exception $e) {
            Log::error('Cumulative score calculation failed', [
                'visitor_id' => $visitor->id,
                'new_score' => $newScore,
                'error' => $e->getMessage()
            ]);
            
            return $newScore; // Fallback to new score
        }
    }
    
    /**
     * Update visitor's last seen timestamp and session info.
     */
    private function updateVisitorLastSeen(Visitor $visitor): void
    {
        $visitor->update([
            'last_seen_at' => now(),
        ]);
    }
    
    /**
     * Create or update session for visitor.
     */
    public function createOrUpdateSession(Visitor $visitor, ?string $sessionId, int $tenantId): VisitorSession
    {
        if (!$sessionId) {
            $sessionId = Str::uuid();
        }
        
        try {
            $session = VisitorSession::where('session_id', $sessionId)
                ->where('tenant_id', $tenantId)
                ->first();
            
            if ($session) {
                // Update existing session
                $session->update([
                    'visitor_id' => $visitor->id,
                    'started_at' => $session->started_at ?? now(),
                ]);
            } else {
                // Create new session
                $session = VisitorSession::create([
                    'session_id' => $sessionId,
                    'visitor_id' => $visitor->id,
                    'started_at' => now(),
                    'tenant_id' => $tenantId,
                ]);
            }
            
            return $session;
        } catch (\Exception $e) {
            Log::error('Failed to create/update session', [
                'visitor_id' => $visitor->id,
                'session_id' => $sessionId,
                'tenant_id' => $tenantId,
                'error' => $e->getMessage()
            ]);
            
            // Fallback: create a basic session
            return VisitorSession::create([
                'session_id' => $sessionId,
                'visitor_id' => $visitor->id,
                'started_at' => now(),
                'tenant_id' => $tenantId,
            ]);
        }
    }
    
    /**
     * Find visitor by device fingerprint (IP + User Agent).
     */
    private function findVisitorByDevice(string $ipAddress, string $userAgent, int $tenantId): ?Visitor
    {
        try {
            // Look for recent events with same IP and similar user agent
            $matchingEvents = IntentEvent::where('tenant_id', $tenantId)
                ->where('ip_address', $ipAddress)
                ->where('user_agent', 'LIKE', '%' . substr($userAgent, 0, 50) . '%')
                ->where('created_at', '>=', now()->subDays(7)) // Within last 7 days
                ->orderBy('created_at', 'desc')
                ->get();
            
            if ($matchingEvents->isEmpty()) {
                return null;
            }
            
            // Get the most recent event's session
            $mostRecentEvent = $matchingEvents->first();
            $sessionId = $mostRecentEvent->session_id;
            
            if (!$sessionId) {
                return null;
            }
            
            // Find the visitor through the session
            $visitorSession = VisitorSession::where('session_id', $sessionId)
                ->where('tenant_id', $tenantId)
                ->first();
            
            if ($visitorSession) {
                return $visitorSession->visitor;
            }
            
            return null;
            
        } catch (\Exception $e) {
            Log::error('Device matching failed', [
                'ip_address' => $ipAddress,
                'user_agent' => substr($userAgent, 0, 100),
                'tenant_id' => $tenantId,
                'error' => $e->getMessage()
            ]);
            
            return null;
        }
    }
    
    /**
     * Get visitor from existing event.
     */
    private function getVisitorFromEvent(IntentEvent $event): ?Visitor
    {
        $sessionId = $event->session_id;
        if (!$sessionId) {
            return null;
        }
        
        $session = VisitorSession::where('session_id', $sessionId)
            ->where('tenant_id', $event->tenant_id)
            ->first();
        
        return $session?->visitor;
    }
    
    /**
     * Create new visitor.
     */
    private function createNewVisitor(int $tenantId): Visitor
    {
        return Visitor::create([
            'visitor_id' => Str::uuid(),
            'first_seen_at' => now(),
            'last_seen_at' => now(),
            'tenant_id' => $tenantId,
        ]);
    }
    
    /**
     * Calculate engagement multiplier based on visitor behavior.
     */
    private function calculateEngagementMultiplier(Visitor $visitor): float
    {
        $sessionCount = $visitor->sessions()->count();
        $totalDuration = $visitor->getTotalSessionDuration();
        
        $multiplier = 1.0;
        
        // Return visitor bonus
        if ($sessionCount > 1) {
            $multiplier += 0.2; // 20% bonus for return visitors
        }
        
        // Long session bonus
        if ($totalDuration > 300) { // 5+ minutes total
            $multiplier += 0.1; // 10% bonus
        }
        
        // High engagement bonus
        if ($sessionCount > 3 && $totalDuration > 600) { // 3+ sessions, 10+ minutes
            $multiplier += 0.15; // 15% bonus
        }
        
        return min($multiplier, 1.5); // Cap at 50% bonus
    }
    
    /**
     * Get visitor analytics.
     */
    public function getVisitorAnalytics(int $tenantId, int $visitorId): array
    {
        $visitor = Visitor::where('id', $visitorId)
            ->where('tenant_id', $tenantId)
            ->first();
        
        if (!$visitor) {
            return [];
        }
        
        $sessions = $visitor->sessions()->with('intentEvents')->get();
        $totalEvents = $sessions->sum(function($session) {
            return $session->intentEvents()->count();
        });
        
        $totalScore = $sessions->sum(function($session) {
            return $session->intentEvents()->sum('intent_score');
        });
        
        $avgScore = $totalEvents > 0 ? $totalScore / $totalEvents : 0;
        
        return [
            'visitor_id' => $visitor->visitor_id,
            'first_seen' => $visitor->first_seen_at,
            'last_seen' => $visitor->last_seen_at,
            'session_count' => $sessions->count(),
            'total_events' => $totalEvents,
            'total_score' => $totalScore,
            'avg_score' => round($avgScore, 2),
            'is_active' => $visitor->isActive(),
            'engagement_level' => $this->getEngagementLevel($visitor),
        ];
    }
    
    /**
     * Get engagement level for visitor.
     */
    private function getEngagementLevel(Visitor $visitor): string
    {
        $sessionCount = $visitor->sessions()->count();
        $totalDuration = $visitor->getTotalSessionDuration();
        
        if ($sessionCount >= 5 && $totalDuration >= 1800) { // 5+ sessions, 30+ minutes
            return 'very_high';
        } elseif ($sessionCount >= 3 && $totalDuration >= 900) { // 3+ sessions, 15+ minutes
            return 'high';
        } elseif ($sessionCount >= 2 && $totalDuration >= 300) { // 2+ sessions, 5+ minutes
            return 'medium';
        } elseif ($sessionCount >= 1 && $totalDuration >= 60) { // 1+ session, 1+ minute
            return 'low';
        } else {
            return 'very_low';
        }
    }
}
