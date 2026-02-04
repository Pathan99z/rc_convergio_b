<?php

namespace App\Services\Hr;

use App\Constants\HrConstants;
use App\Models\Hr\Employee;
use App\Models\Hr\KpiAssignment;
use App\Models\Hr\KpiReview;
use App\Models\Hr\KpiReviewItem;
use App\Models\Hr\KpiTemplate;
use App\Models\Hr\KpiTemplateItem;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class KpiService
{
    /**
     * Get paginated KPI templates with filters.
     */
    public function getTemplates(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $currentUser = Auth::user();
        $tenantId = (int) ($currentUser->tenant_id ?? $currentUser->id);

        $query = KpiTemplate::withoutGlobalScope('tenant')
            ->where('tenant_id', $tenantId)
            ->with(['creator', 'department', 'designation', 'items']);

        // Filter by status
        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        } else {
            // Default: exclude archived
            $query->where('status', '!=', 'archived');
        }

        // Filter by department
        if (!empty($filters['department_id'])) {
            $query->where('department_id', $filters['department_id']);
        }

        // Filter by designation
        if (!empty($filters['designation_id'])) {
            $query->where('designation_id', $filters['designation_id']);
        }

        // Filter by review period
        if (!empty($filters['review_period'])) {
            $query->where('review_period', $filters['review_period']);
        }

        // Search filter
        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%");
            });
        }

        // Sorting
        $sortBy = $filters['sortBy'] ?? 'created_at';
        $sortOrder = $filters['sortOrder'] ?? 'desc';
        $query->orderBy($sortBy, $sortOrder);

        return $query->paginate($perPage);
    }

    /**
     * Get a single KPI template by ID.
     */
    public function getTemplate(int $id): KpiTemplate
    {
        $currentUser = Auth::user();
        $tenantId = $currentUser->tenant_id ?? $currentUser->id;

        $template = KpiTemplate::withoutGlobalScope('tenant')
            ->where('tenant_id', $tenantId)
            ->with(['creator', 'department', 'designation', 'items'])
            ->findOrFail($id);

        return $template;
    }

    /**
     * Create a new KPI template.
     */
    public function createTemplate(array $data): KpiTemplate
    {
        $currentUser = Auth::user();
        $tenantId = $currentUser->tenant_id ?? $currentUser->id;

        DB::beginTransaction();
        try {
            // Validate total weight
            $items = $data['items'] ?? [];
            $totalWeight = array_sum(array_column($items, 'weight'));
            
            if (abs($totalWeight - 100) > 0.01) {
                throw new \Exception('Total weight of all KPI items must equal exactly 100%. Current total: ' . $totalWeight . '%');
            }

            // Create template
            $template = KpiTemplate::create([
                'tenant_id' => $tenantId,
                'name' => $data['name'],
                'department_id' => $data['department_id'] ?? null,
                'designation_id' => $data['designation_id'] ?? null,
                'review_period' => $data['review_period'] ?? 'quarterly',
                'description' => $data['description'] ?? null,
                'status' => $data['status'] ?? 'draft',
                'created_by' => $currentUser->id,
            ]);

            // Create template items
            $order = 0;
            foreach ($items as $itemData) {
                KpiTemplateItem::create([
                    'kpi_template_id' => $template->id,
                    'name' => $itemData['name'],
                    'weight' => $itemData['weight'],
                    'description' => $itemData['description'] ?? null,
                    'order' => $order++,
                ]);
            }

            DB::commit();
            return $template->load(['creator', 'department', 'designation', 'items']);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to create KPI template', [
                'error' => $e->getMessage(),
                'data' => $data,
            ]);
            throw $e;
        }
    }

    /**
     * Update a KPI template.
     */
    public function updateTemplate(KpiTemplate $template, array $data): KpiTemplate
    {
        $currentUser = Auth::user();
        $tenantId = $currentUser->tenant_id ?? $currentUser->id;

        // Ensure template belongs to tenant
        if ($template->tenant_id != $tenantId) {
            throw new \Exception(HrConstants::ERROR_UNAUTHORIZED_ACCESS);
        }

        DB::beginTransaction();
        try {
            // If items are being updated, validate total weight
            if (isset($data['items'])) {
                $totalWeight = array_sum(array_column($data['items'], 'weight'));
                
                if (abs($totalWeight - 100) > 0.01) {
                    throw new \Exception('Total weight of all KPI items must equal exactly 100%. Current total: ' . $totalWeight . '%');
                }

                // Delete existing items
                $template->items()->delete();

                // Create new items
                $order = 0;
                foreach ($data['items'] as $itemData) {
                    KpiTemplateItem::create([
                        'kpi_template_id' => $template->id,
                        'name' => $itemData['name'],
                        'weight' => $itemData['weight'],
                        'description' => $itemData['description'] ?? null,
                        'order' => $order++,
                    ]);
                }
            }

            // Update template
            $template->update(array_filter($data, function($key) {
                return !in_array($key, ['items']);
            }, ARRAY_FILTER_USE_KEY));

            $template->refresh();

            DB::commit();
            return $template->load(['creator', 'department', 'designation', 'items']);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to update KPI template', [
                'template_id' => $template->id,
                'error' => $e->getMessage(),
                'data' => $data,
            ]);
            throw $e;
        }
    }

    /**
     * Delete a KPI template (soft delete).
     */
    public function deleteTemplate(KpiTemplate $template): bool
    {
        $currentUser = Auth::user();
        $tenantId = $currentUser->tenant_id ?? $currentUser->id;

        // Ensure template belongs to tenant
        if ($template->tenant_id != $tenantId) {
            throw new \Exception(HrConstants::ERROR_UNAUTHORIZED_ACCESS);
        }

        DB::beginTransaction();
        try {
            // Check if template has active assignments
            $hasAssignments = $template->assignments()
                ->where('status', '!=', 'completed')
                ->exists();

            if ($hasAssignments) {
                throw new \Exception('Cannot delete template with active assignments. Please complete or cancel all assignments first.');
            }

            $template->delete();

            DB::commit();
            return true;
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to delete KPI template', [
                'template_id' => $template->id,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Assign KPI template to employees.
     */
    public function assignTemplate(array $data): array
    {
        $currentUser = Auth::user();
        $tenantId = $currentUser->tenant_id ?? $currentUser->id;

        DB::beginTransaction();
        try {
            $template = KpiTemplate::withoutGlobalScope('tenant')
                ->where('tenant_id', $tenantId)
                ->findOrFail($data['kpi_template_id']);

            if ($template->status !== 'published') {
                throw new \Exception('Can only assign published templates.');
            }

            $employeeIds = $data['employee_ids'] ?? [];
            $reviewPeriodValue = $data['review_period_value']; // e.g., "Q1 2026"
            $reviewPeriodStart = $data['review_period_start'];
            $reviewPeriodEnd = $data['review_period_end'];

            $assignments = [];
            foreach ($employeeIds as $employeeId) {
                // Check if assignment already exists
                $existing = KpiAssignment::withoutGlobalScope('tenant')
                    ->where('tenant_id', $tenantId)
                    ->where('employee_id', $employeeId)
                    ->where('kpi_template_id', $template->id)
                    ->where('review_period_value', $reviewPeriodValue)
                    ->first();

                if ($existing) {
                    continue; // Skip if already assigned
                }

                $assignment = KpiAssignment::create([
                    'tenant_id' => $tenantId,
                    'employee_id' => $employeeId,
                    'kpi_template_id' => $template->id,
                    'review_period_value' => $reviewPeriodValue,
                    'review_period_start' => $reviewPeriodStart,
                    'review_period_end' => $reviewPeriodEnd,
                    'status' => 'self_review_pending',
                    'assigned_by' => $currentUser->id,
                ]);

                $assignments[] = $assignment;
            }

            DB::commit();
            return $assignments;
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to assign KPI template', [
                'error' => $e->getMessage(),
                'data' => $data,
            ]);
            throw $e;
        }
    }

    /**
     * Get employee assignments.
     */
    public function getEmployeeAssignments(int $employeeId, array $filters = []): LengthAwarePaginator
    {
        $currentUser = Auth::user();
        $tenantId = $currentUser->tenant_id ?? $currentUser->id;

        $query = KpiAssignment::withoutGlobalScope('tenant')
            ->where('tenant_id', $tenantId)
            ->where('employee_id', $employeeId)
            ->with(['template.items', 'selfReview', 'managerReview']);

        // Filter by status
        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        // Filter by review period
        if (!empty($filters['review_period_value'])) {
            $query->where('review_period_value', $filters['review_period_value']);
        }

        $sortBy = $filters['sortBy'] ?? 'created_at';
        $sortOrder = $filters['sortOrder'] ?? 'desc';
        $query->orderBy($sortBy, $sortOrder);

        $perPage = $filters['per_page'] ?? 15;
        return $query->paginate($perPage);
    }

    /**
     * Submit self review.
     */
    public function submitSelfReview(KpiAssignment $assignment, array $data): KpiReview
    {
        $currentUser = Auth::user();
        $employee = Employee::where('user_id', $currentUser->id)->firstOrFail();

        // Verify assignment belongs to employee
        if ($assignment->employee_id !== $employee->id) {
            throw new \Exception(HrConstants::ERROR_UNAUTHORIZED_ACCESS);
        }

        // Check if self review already exists
        if ($assignment->hasSelfReview()) {
            throw new \Exception('Self review already submitted for this assignment.');
        }

        DB::beginTransaction();
        try {
            $items = $data['items'] ?? [];
            $template = $assignment->template;

            // Create self review
            $review = KpiReview::create([
                'kpi_assignment_id' => $assignment->id,
                'review_type' => 'self_review',
                'reviewed_by' => $currentUser->id,
                'comments' => $data['comments'] ?? null,
                'submitted_at' => now(),
            ]);

            // Create review items
            $totalScore = 0;
            foreach ($items as $itemData) {
                $templateItem = $template->items()->findOrFail($itemData['kpi_template_item_id']);
                $score = $itemData['score'];

                // Validate score range (0-10)
                if ($score < 0 || $score > 10) {
                    throw new \Exception('Score must be between 0 and 10.');
                }

                KpiReviewItem::create([
                    'kpi_review_id' => $review->id,
                    'kpi_template_item_id' => $templateItem->id,
                    'score' => $score,
                    'comments' => $itemData['comments'] ?? null,
                ]);

                // Calculate weighted contribution
                $totalScore += ($score * $templateItem->weight / 100);
            }

            // Calculate final score and grade
            $finalScore = round($totalScore, 2);
            $grade = $this->calculateGrade($finalScore);

            $review->update([
                'final_score' => $finalScore,
                'grade' => $grade,
            ]);

            // Update assignment status
            $assignment->update(['status' => 'self_review_submitted']);

            DB::commit();
            return $review->load(['items.templateItem']);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to submit self review', [
                'assignment_id' => $assignment->id,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Submit manager review.
     */
    public function submitManagerReview(KpiAssignment $assignment, array $data): KpiReview
    {
        $currentUser = Auth::user();

        // Verify assignment belongs to manager's team
        $employee = $assignment->employee;
        if ($employee->manager_id && $employee->manager->user_id !== $currentUser->id) {
            // Check if user is HR admin
            if (!$currentUser->hasRole('hr_admin') && !$currentUser->hasRole('system_admin') && !$currentUser->hasRole('admin')) {
                throw new \Exception(HrConstants::ERROR_UNAUTHORIZED_ACCESS);
            }
        }

        // Check if self review exists
        if (!$assignment->hasSelfReview()) {
            throw new \Exception('Employee must submit self review before manager review.');
        }

        // Check if manager review already exists
        if ($assignment->hasManagerReview()) {
            throw new \Exception('Manager review already submitted for this assignment.');
        }

        DB::beginTransaction();
        try {
            $items = $data['items'] ?? [];
            $template = $assignment->template;

            // Create manager review
            $review = KpiReview::create([
                'kpi_assignment_id' => $assignment->id,
                'review_type' => 'manager_review',
                'reviewed_by' => $currentUser->id,
                'comments' => $data['comments'] ?? null,
                'submitted_at' => now(),
            ]);

            // Create review items
            $totalScore = 0;
            foreach ($items as $itemData) {
                $templateItem = $template->items()->findOrFail($itemData['kpi_template_item_id']);
                $score = $itemData['score'];

                // Validate score range (0-10)
                if ($score < 0 || $score > 10) {
                    throw new \Exception('Score must be between 0 and 10.');
                }

                KpiReviewItem::create([
                    'kpi_review_id' => $review->id,
                    'kpi_template_item_id' => $templateItem->id,
                    'score' => $score,
                    'comments' => $itemData['comments'] ?? null,
                ]);

                // Calculate weighted contribution
                $totalScore += ($score * $templateItem->weight / 100);
            }

            // Calculate final score and grade
            $finalScore = round($totalScore, 2);
            $grade = $this->calculateGrade($finalScore);

            $review->update([
                'final_score' => $finalScore,
                'grade' => $grade,
            ]);

            // Update assignment status to completed
            $assignment->update(['status' => 'completed']);

            DB::commit();
            return $review->load(['items.templateItem']);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to submit manager review', [
                'assignment_id' => $assignment->id,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Get manager's team assignments.
     */
    public function getManagerTeamAssignments(int $managerUserId, array $filters = []): LengthAwarePaginator
    {
        $currentUser = Auth::user();
        $tenantId = $currentUser->tenant_id ?? $currentUser->id;

        // Get manager's employee record
        $manager = Employee::withoutGlobalScope('tenant')
            ->where('tenant_id', $tenantId)
            ->where('user_id', $managerUserId)
            ->firstOrFail();

        // Get team member IDs
        $teamMemberIds = Employee::withoutGlobalScope('tenant')
            ->where('tenant_id', $tenantId)
            ->where('manager_id', $manager->id)
            ->pluck('id')
            ->toArray();

        $query = KpiAssignment::withoutGlobalScope('tenant')
            ->where('tenant_id', $tenantId)
            ->whereIn('employee_id', $teamMemberIds)
            ->with(['employee', 'template.items', 'selfReview', 'managerReview']);

        // Filter by status
        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        $sortBy = $filters['sortBy'] ?? 'created_at';
        $sortOrder = $filters['sortOrder'] ?? 'desc';
        $query->orderBy($sortBy, $sortOrder);

        $perPage = $filters['per_page'] ?? 15;
        return $query->paginate($perPage);
    }

    /**
     * Get analytics data.
     */
    public function getAnalytics(array $filters = []): array
    {
        $currentUser = Auth::user();
        $tenantId = $currentUser->tenant_id ?? $currentUser->id;

        $query = KpiAssignment::withoutGlobalScope('tenant')
            ->where('tenant_id', $tenantId)
            ->with(['employee', 'template', 'managerReview']);

        // Filter by review period
        if (!empty($filters['review_period_value'])) {
            $query->where('review_period_value', $filters['review_period_value']);
        }

        // Filter by department
        if (!empty($filters['department_id'])) {
            $query->whereHas('employee', function($q) use ($filters) {
                $q->where('department_id', $filters['department_id']);
            });
        }

        // Filter by status
        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        $assignments = $query->get();

        $totalEmployees = $assignments->unique('employee_id')->count();
        $completed = $assignments->where('status', 'completed')->count();
        $pending = $assignments->whereIn('status', ['self_review_pending', 'self_review_submitted', 'manager_review_pending'])->count();
        $overdue = $assignments->where('status', 'overdue')->count();

        $completedPercentage = $totalEmployees > 0 ? round(($completed / $totalEmployees) * 100, 2) : 0;

        // Calculate average score
        $completedAssignments = $assignments->where('status', 'completed');
        $scores = $completedAssignments->map(function($assignment) {
            return $assignment->managerReview?->final_score;
        })->filter()->values();

        $averageScore = $scores->count() > 0 ? round($scores->avg(), 2) : 0;

        // Group by department
        $byDepartment = $assignments->where('status', 'completed')
            ->groupBy(function($assignment) {
                return $assignment->employee->department ?? 'Unknown';
            })
            ->map(function($group) {
                $scores = $group->map(function($assignment) {
                    return $assignment->managerReview?->final_score;
                })->filter()->values();
                
                return [
                    'department' => $group->first()->employee->department ?? 'Unknown',
                    'average_score' => $scores->count() > 0 ? round($scores->avg(), 2) : 0,
                    'count' => $group->count(),
                ];
            })
            ->values();

        return [
            'total_employees' => $totalEmployees,
            'completed_reviews' => $completed,
            'pending_reviews' => $pending,
            'overdue_reviews' => $overdue,
            'completion_rate' => $completedPercentage,
            'average_score' => $averageScore,
            'by_department' => $byDepartment,
            'status_breakdown' => [
                'completed' => $completed,
                'pending' => $pending,
                'overdue' => $overdue,
            ],
        ];
    }

    /**
     * Calculate grade from score (0-10 scale).
     */
    private function calculateGrade(float $score): string
    {
        // Convert 0-10 scale to percentage
        $percentage = ($score / 10) * 100;

        if ($percentage >= 90) {
            return 'A';
        } elseif ($percentage >= 75) {
            return 'B';
        } elseif ($percentage >= 60) {
            return 'C';
        } else {
            return 'D';
        }
    }
}

