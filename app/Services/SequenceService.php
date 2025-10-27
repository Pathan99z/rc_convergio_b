<?php

namespace App\Services;

use App\Models\Sequence;
use App\Models\SequenceStep;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SequenceService
{
    /**
     * Get paginated sequences with filters
     */
    public function getSequences(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $query = Sequence::query()
            ->forTenant($filters['tenant_id'] ?? null)
            ->with(['creator:id,name,email'])
            ->withCount(['steps', 'enrollments']);

        // Apply filters
        if (!empty($filters['name'])) {
            $query->where('name', 'like', "%{$filters['name']}%");
        }

        if (!empty($filters['target_type'])) {
            $query->byTargetType($filters['target_type']);
        }

        if (isset($filters['is_active'])) {
            $query->where('is_active', $filters['is_active']);
        }

        // Handle search query
        if (!empty($filters['q'])) {
            $query->search($filters['q']);
        }

        // Apply sorting
        $sortBy = $filters['sortBy'] ?? 'created_at';
        $sortOrder = $filters['sortOrder'] ?? 'desc';
        $query->orderBy($sortBy, $sortOrder);

        return $query->paginate($perPage);
    }

    /**
     * Create a new sequence
     */
    public function createSequence(array $data): Sequence
    {
        return DB::transaction(function () use ($data) {
            // Handle steps if provided
            $steps = $data['steps'] ?? [];
            unset($data['steps']); // Remove steps from main data array
            
            $sequence = Sequence::create($data);
            
            // Create steps if provided
            if (!empty($steps)) {
                $this->createSequenceSteps($sequence, $steps, $data['created_by'] ?? null);
            }
            
            Log::info('Sequence created', [
                'sequence_id' => $sequence->id,
                'name' => $sequence->name,
                'tenant_id' => $sequence->tenant_id,
                'created_by' => $sequence->created_by,
                'steps_count' => count($steps),
            ]);

            return $sequence->fresh(['steps'])->loadCount(['steps', 'enrollments']);
        });
    }

    /**
     * Update a sequence
     */
    public function updateSequence(Sequence $sequence, array $data): Sequence
    {
        return DB::transaction(function () use ($sequence, $data) {
            // Handle steps if provided
            if (isset($data['steps'])) {
                $this->updateSequenceSteps($sequence, $data['steps'], $data['updated_by'] ?? null);
                unset($data['steps']); // Remove steps from main data array
            }
            
            $sequence->update($data);
            
            Log::info('Sequence updated', [
                'sequence_id' => $sequence->id,
                'name' => $sequence->name,
                'tenant_id' => $sequence->tenant_id,
                'updated_by' => $data['updated_by'] ?? null,
            ]);

            return $sequence->fresh(['steps'])->loadCount(['steps', 'enrollments']);
        });
    }

    /**
     * Delete a sequence (soft delete)
     */
    public function deleteSequence(Sequence $sequence): bool
    {
        return DB::transaction(function () use ($sequence) {
            $result = $sequence->delete();
            
            Log::info('Sequence deleted', [
                'sequence_id' => $sequence->id,
                'name' => $sequence->name,
                'tenant_id' => $sequence->tenant_id,
            ]);

            return $result;
        });
    }

    /**
     * Get a single sequence with steps for detailed view
     */
    public function getSequenceWithSteps(Sequence $sequence): Sequence
    {
        return $sequence->load([
            'steps' => function ($query) {
                $query->orderBy('step_order');
            },
            'creator:id,name,email',
            'updater:id,name,email'
        ])->loadCount(['steps', 'enrollments']);
    }

    /**
     * Get sequences for enrollment dropdown
     */
    public function getSequencesForEnrollment(string $targetType, int $tenantId): \Illuminate\Database\Eloquent\Collection
    {
        return Sequence::query()
            ->forTenant($tenantId)
            ->active()
            ->byTargetType($targetType)
            ->select(['id', 'name', 'description'])
            ->orderBy('name')
            ->get();
    }

    /**
     * Create sequence steps
     */
    private function createSequenceSteps(Sequence $sequence, array $steps, ?int $createdBy = null): void
    {
        foreach ($steps as $index => $stepData) {
            // Convert granular delay fields to total hours
            $stepData['delay_hours'] = $this->convertDelayToHours($stepData);

            // Remove granular delay fields as they're not stored in database
            unset($stepData['delay_days'], $stepData['delay_minutes']);

            $stepData['sequence_id'] = $sequence->id;
            $stepData['step_order'] = $index + 1;
            $stepData['tenant_id'] = $sequence->tenant_id;
            $stepData['created_by'] = $createdBy;
            $stepData['updated_by'] = $createdBy;

            SequenceStep::create($stepData);
        }

        Log::info('Sequence steps created', [
            'sequence_id' => $sequence->id,
            'steps_count' => count($steps),
            'created_by' => $createdBy,
        ]);
    }

    /**
     * Update sequence steps
     */
    private function updateSequenceSteps(Sequence $sequence, array $steps, ?int $updatedBy = null): void
    {
        // Delete existing steps with force delete to ensure they're removed
        $sequence->steps()->forceDelete();
        
        // Create new steps
        foreach ($steps as $index => $stepData) {
            // Convert granular delay fields to total hours
            $stepData['delay_hours'] = $this->convertDelayToHours($stepData);
            
            // Remove granular delay fields as they're not stored in database
            unset($stepData['delay_days'], $stepData['delay_minutes']);
            
            $stepData['sequence_id'] = $sequence->id;
            $stepData['step_order'] = $index + 1;
            $stepData['tenant_id'] = $sequence->tenant_id;
            $stepData['created_by'] = $updatedBy;
            $stepData['updated_by'] = $updatedBy;
            
            SequenceStep::create($stepData);
        }
        
        Log::info('Sequence steps updated', [
            'sequence_id' => $sequence->id,
            'steps_count' => count($steps),
            'updated_by' => $updatedBy,
        ]);
    }
    
    /**
     * Convert granular delay fields (days, hours, minutes) to total hours
     */
    private function convertDelayToHours(array $stepData): float
    {
        $days = (int) ($stepData['delay_days'] ?? 0);
        $hours = (int) ($stepData['delay_hours'] ?? 0);
        $minutes = (int) ($stepData['delay_minutes'] ?? 0);
        
        // Convert everything to hours
        $totalHours = ($days * 24) + $hours + ($minutes / 60);
        
        return round($totalHours, 2); // Round to 2 decimal places for precision
    }
}
