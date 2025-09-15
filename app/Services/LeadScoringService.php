<?php

namespace App\Services;

use App\Models\Contact;
use App\Models\LeadScoringRule;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class LeadScoringService
{
    /**
     * Process an event and update lead scores for matching contacts.
     */
    public function processEvent(array $eventData, int $tenantId): void
    {
        try {
            // Get all active scoring rules for the tenant
            $rules = LeadScoringRule::forTenant($tenantId)
                ->active()
                ->byPriority()
                ->get();

            if ($rules->isEmpty()) {
                Log::info('No active scoring rules found for tenant', ['tenant_id' => $tenantId]);
                return;
            }

            // Find matching rules
            $matchingRules = $rules->filter(function ($rule) use ($eventData) {
                return $rule->matchesEvent($eventData);
            });

            if ($matchingRules->isEmpty()) {
                Log::info('No matching scoring rules found for event', [
                    'tenant_id' => $tenantId,
                    'event' => $eventData['event'] ?? 'unknown'
                ]);
                return;
            }

            // Get contact ID from event data
            $contactId = $eventData['contact_id'] ?? null;
            if (!$contactId) {
                Log::warning('No contact_id found in event data', ['event_data' => $eventData]);
                return;
            }

            // Verify contact exists and belongs to tenant
            $contact = Contact::where('id', $contactId)
                ->where('tenant_id', $tenantId)
                ->first();

            if (!$contact) {
                Log::warning('Contact not found or does not belong to tenant', [
                    'contact_id' => $contactId,
                    'tenant_id' => $tenantId
                ]);
                return;
            }

            // Apply matching rules
            $this->applyRulesToContact($contact, $matchingRules, $eventData);

        } catch (\Exception $e) {
            Log::error('Error processing lead scoring event', [
                'tenant_id' => $tenantId,
                'event_data' => $eventData,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Apply scoring rules to a specific contact.
     */
    public function applyRulesToContact(Contact $contact, $rules, array $eventData = []): void
    {
        $totalPoints = 0;
        $appliedRules = [];

        foreach ($rules as $rule) {
            try {
                // Check if rule matches (if event data provided)
                if (!empty($eventData) && !$rule->matchesEvent($eventData)) {
                    continue;
                }

                $totalPoints += $rule->points;
                $appliedRules[] = $rule->condition['event'] ?? 'unknown';

                Log::info('Applied scoring rule to contact', [
                    'contact_id' => $contact->id,
                    'rule_id' => $rule->id,
                    'rule_name' => $rule->name,
                    'points' => $rule->points,
                    'event' => $rule->condition['event'] ?? 'unknown'
                ]);

            } catch (\Exception $e) {
                Log::error('Error applying scoring rule to contact', [
                    'contact_id' => $contact->id,
                    'rule_id' => $rule->id,
                    'error' => $e->getMessage()
                ]);
            }
        }

        if ($totalPoints > 0) {
            $this->updateContactScore($contact, $totalPoints, $appliedRules);
        }
    }

    /**
     * Update contact's lead score.
     */
    private function updateContactScore(Contact $contact, int $points, array $appliedRules = []): void
    {
        try {
            DB::beginTransaction();

            $newScore = $contact->lead_score + $points;
            
            $contact->update([
                'lead_score' => $newScore,
                'lead_score_updated_at' => now(),
            ]);

            DB::commit();

            Log::info('Updated contact lead score', [
                'contact_id' => $contact->id,
                'points_added' => $points,
                'new_score' => $newScore,
                'applied_rules' => $appliedRules
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error updating contact lead score', [
                'contact_id' => $contact->id,
                'points' => $points,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Recalculate lead score for a specific contact.
     */
    public function recalculateContactScore(int $contactId, int $tenantId): array
    {
        try {
            $contact = Contact::where('id', $contactId)
                ->where('tenant_id', $tenantId)
                ->firstOrFail();

            // Reset score to 0
            $contact->update([
                'lead_score' => 0,
                'lead_score_updated_at' => now(),
            ]);

            // Get all active rules for the tenant
            $rules = LeadScoringRule::forTenant($tenantId)
                ->active()
                ->byPriority()
                ->get();

            $appliedRules = [];
            $totalPoints = 0;

            // Apply all rules that match the contact's data
            foreach ($rules as $rule) {
                if ($this->ruleMatchesContact($rule, $contact)) {
                    $totalPoints += $rule->points;
                    $appliedRules[] = $rule->condition['event'] ?? 'unknown';
                }
            }

            // Update final score
            $contact->update([
                'lead_score' => $totalPoints,
                'lead_score_updated_at' => now(),
            ]);

            return [
                'contact_id' => $contact->id,
                'lead_score' => $totalPoints,
                'rules_applied' => $appliedRules,
                'rules_count' => count($appliedRules)
            ];

        } catch (\Exception $e) {
            Log::error('Error recalculating contact lead score', [
                'contact_id' => $contactId,
                'tenant_id' => $tenantId,
                'error' => $e->getMessage()
            ]);

            throw $e;
        }
    }

    /**
     * Check if a rule matches a contact's current data.
     */
    private function ruleMatchesContact(LeadScoringRule $rule, Contact $contact): bool
    {
        // This is a simplified version - in a real implementation,
        // you would check the contact's historical data against the rule conditions
        // For now, we'll just check basic contact properties
        
        if (!$rule->validateCondition()) {
            return false;
        }

        $event = $rule->condition['event'];
        
        // Map events to contact properties or historical data
        switch ($event) {
            case 'contact_created':
                return true; // All contacts match this
            case 'contact_updated':
                return $contact->updated_at > $contact->created_at;
            case 'form_submission':
                // Check if contact has form submissions (would need to query form_submissions table)
                return false; // Simplified for now
            case 'email_open':
                // Check if contact has email opens (would need to query campaign_tracking table)
                return false; // Simplified for now
            default:
                return false;
        }
    }

    /**
     * Get lead scoring statistics for a tenant.
     */
    public function getScoringStats(int $tenantId): array
    {
        $totalContacts = Contact::where('tenant_id', $tenantId)->count();
        $contactsWithScore = Contact::where('tenant_id', $tenantId)
            ->where('lead_score', '>', 0)
            ->count();
        
        $avgScore = Contact::where('tenant_id', $tenantId)
            ->where('lead_score', '>', 0)
            ->avg('lead_score');

        $highScoreContacts = Contact::where('tenant_id', $tenantId)
            ->where('lead_score', '>=', 50)
            ->count();

        $activeRules = LeadScoringRule::forTenant($tenantId)
            ->active()
            ->count();

        return [
            'total_contacts' => $totalContacts,
            'contacts_with_score' => $contactsWithScore,
            'average_score' => round($avgScore, 2),
            'high_score_contacts' => $highScoreContacts,
            'active_rules' => $activeRules,
            'scoring_coverage' => $totalContacts > 0 ? round(($contactsWithScore / $totalContacts) * 100, 2) : 0,
        ];
    }

    /**
     * Get top scoring contacts for a tenant.
     */
    public function getTopScoringContacts(int $tenantId, int $limit = 10): array
    {
        return Contact::where('tenant_id', $tenantId)
            ->where('lead_score', '>', 0)
            ->orderBy('lead_score', 'desc')
            ->limit($limit)
            ->get(['id', 'first_name', 'last_name', 'email', 'lead_score', 'lead_score_updated_at'])
            ->toArray();
    }
}
