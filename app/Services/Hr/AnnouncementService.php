<?php

namespace App\Services\Hr;

use App\Models\Hr\Announcement;
use App\Models\Hr\AnnouncementAcknowledgment;
use App\Models\Hr\AnnouncementComment;
use App\Models\Hr\AnnouncementLike;
use App\Models\Hr\AnnouncementView;
use App\Models\Hr\Employee;
use App\Notifications\AnnouncementPublishedNotification;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AnnouncementService
{
    /**
     * Create a new announcement.
     */
    public function createAnnouncement(array $data): Announcement
    {
        $currentUser = Auth::user();
        $tenantId = (int) ($currentUser->tenant_id ?? $currentUser->id);

        $announcement = Announcement::create([
            'tenant_id' => $tenantId,
            'title' => $data['title'],
            'category' => $data['category'] ?? 'general',
            'message' => $data['message'],
            'attachment_url' => $data['attachment_url'] ?? null,
            'target_audience_type' => $data['target_audience_type'] ?? 'all_employees',
            'target_departments' => $data['target_departments'] ?? null,
            'target_employee_ids' => $data['target_employee_ids'] ?? null,
            'is_mandatory' => $data['is_mandatory'] ?? false,
            'priority' => $data['priority'] ?? 'medium',
            'status' => $data['status'] ?? 'draft',
            'scheduled_publish_at' => $data['scheduled_publish_at'] ?? null,
            'created_by' => $currentUser->id,
        ]);

        return $announcement->load(['creator']);
    }

    /**
     * Update an announcement.
     */
    public function updateAnnouncement(Announcement $announcement, array $data): Announcement
    {
        $currentUser = Auth::user();
        $tenantId = (int) ($currentUser->tenant_id ?? $currentUser->id);

        // Ensure announcement belongs to tenant
        if ($announcement->tenant_id != $tenantId) {
            throw new \Exception('Unauthorized access to this resource');
        }

        // Only allow editing if draft or not yet published
        if ($announcement->status === 'published' && !isset($data['status'])) {
            throw new \Exception('Cannot edit published announcement. Please archive and create a new one.');
        }

        $announcement->update($data);
        $announcement->refresh();

        return $announcement->load(['creator', 'publishedBy']);
    }

    /**
     * Publish an announcement.
     */
    public function publishAnnouncement(Announcement $announcement): Announcement
    {
        $currentUser = Auth::user();
        $tenantId = (int) ($currentUser->tenant_id ?? $currentUser->id);

        // Ensure announcement belongs to tenant
        if ($announcement->tenant_id != $tenantId) {
            throw new \Exception('Unauthorized access to this resource');
        }

        DB::beginTransaction();
        try {
            $announcement->update([
                'status' => 'published',
                'published_at' => now(),
                'published_by' => $currentUser->id,
            ]);

            // Get target employees
            $targetEmployees = $this->calculateTargetEmployees($announcement);

            // Create lightweight in-app notifications
            foreach ($targetEmployees as $employee) {
                if ($employee->user_id) {
                    try {
                        $employee->user->notify(new AnnouncementPublishedNotification($announcement));
                    } catch (\Exception $e) {
                        Log::warning('Failed to send announcement notification', [
                            'employee_id' => $employee->id,
                            'announcement_id' => $announcement->id,
                            'error' => $e->getMessage(),
                        ]);
                    }
                }
            }

            DB::commit();
            return $announcement->load(['creator', 'publishedBy']);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to publish announcement', [
                'announcement_id' => $announcement->id,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Archive an announcement.
     */
    public function archiveAnnouncement(Announcement $announcement): Announcement
    {
        $currentUser = Auth::user();
        $tenantId = (int) ($currentUser->tenant_id ?? $currentUser->id);

        // Ensure announcement belongs to tenant
        if ($announcement->tenant_id != $tenantId) {
            throw new \Exception('Unauthorized access to this resource');
        }

        $announcement->update(['status' => 'archived']);
        return $announcement->load(['creator', 'publishedBy']);
    }

    /**
     * Delete an announcement (soft delete).
     */
    public function deleteAnnouncement(Announcement $announcement): bool
    {
        $currentUser = Auth::user();
        $tenantId = (int) ($currentUser->tenant_id ?? $currentUser->id);

        // Ensure announcement belongs to tenant
        if ($announcement->tenant_id != $tenantId) {
            throw new \Exception('Unauthorized access to this resource');
        }

        // Delete attachment file if exists
        if ($announcement->attachment_url) {
            try {
                // Get raw path (before accessor converts it)
                $filePath = $announcement->getRawOriginal('attachment_url');
                
                // If it's a URL, try to extract the path
                if (str_starts_with($filePath, 'http') || str_starts_with($filePath, '/')) {
                    $urlPath = parse_url($filePath, PHP_URL_PATH);
                    if ($urlPath) {
                        $filePath = str_replace('/storage/', '', $urlPath);
                    }
                }
                
                // Delete the file if it exists
                if ($filePath && \Illuminate\Support\Facades\Storage::disk('public')->exists($filePath)) {
                    \Illuminate\Support\Facades\Storage::disk('public')->delete($filePath);
                }
            } catch (\Exception $e) {
                // Log but don't fail if file doesn't exist
                Log::warning('Failed to delete announcement attachment file', [
                    'announcement_id' => $announcement->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $announcement->delete();
    }

    /**
     * Get announcements for HR with filters.
     */
    public function getAnnouncements(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $currentUser = Auth::user();
        $tenantId = (int) ($currentUser->tenant_id ?? $currentUser->id);

        $query = Announcement::where('tenant_id', $tenantId)
            ->with(['creator', 'publishedBy']);

        // Filter by status
        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        // Filter by category
        if (!empty($filters['category'])) {
            $query->where('category', $filters['category']);
        }

        // Search
        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                  ->orWhere('message', 'like', "%{$search}%");
            });
        }

        // Sorting
        $sortBy = $filters['sortBy'] ?? 'created_at';
        $sortOrder = $filters['sortOrder'] ?? 'desc';
        $query->orderBy($sortBy, $sortOrder);

        return $query->paginate($perPage);
    }

    /**
     * Get announcements for employee.
     */
    public function getAnnouncementsForEmployee(int $employeeId, array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $employee = Employee::findOrFail($employeeId);
        $tenantId = $employee->tenant_id;

        $query = Announcement::where('tenant_id', $tenantId)
            ->where('status', 'published')
            ->with(['creator', 'publishedBy', 'likes', 'comments']);

        // Filter by target audience
        $query->where(function ($q) use ($employee) {
            $q->where('target_audience_type', 'all_employees')
              ->orWhere(function ($subQ) use ($employee) {
                  $subQ->where('target_audience_type', 'department_specific')
                       ->whereJsonContains('target_departments', $employee->department_id);
              })
              ->orWhere(function ($subQ) use ($employee) {
                  $subQ->where('target_audience_type', 'individual')
                       ->whereJsonContains('target_employee_ids', $employee->id);
              });
        });

        // Filter by category
        if (!empty($filters['category'])) {
            $query->where('category', $filters['category']);
        }

        // Sorting
        $sortBy = $filters['sortBy'] ?? 'published_at';
        $sortOrder = $filters['sortOrder'] ?? 'desc';
        $query->orderBy($sortBy, $sortOrder);

        $announcements = $query->paginate($perPage);

        // Add engagement data for each announcement
        $announcements->getCollection()->transform(function ($announcement) use ($employeeId) {
            $announcement->is_viewed = $announcement->isViewedBy($employeeId);
            $announcement->is_acknowledged = $announcement->isAcknowledgedBy($employeeId);
            $announcement->is_liked = $announcement->isLikedBy($employeeId);
            $announcement->views_count = $announcement->views()->count();
            $announcement->likes_count = $announcement->likes()->count();
            $announcement->comments_count = $announcement->comments()->count();
            $announcement->acknowledgments_count = $announcement->acknowledgments()->count();
            return $announcement;
        });

        return $announcements;
    }

    /**
     * Mark announcement as viewed.
     */
    public function markAsViewed(Announcement $announcement, int $employeeId): bool
    {
        // Check if already viewed
        if ($announcement->isViewedBy($employeeId)) {
            return true;
        }

        $employee = Employee::findOrFail($employeeId);
        $tenantId = $employee->tenant_id;

        // Ensure announcement belongs to same tenant
        if ($announcement->tenant_id != $tenantId) {
            throw new \Exception('Unauthorized access to this resource');
        }

        AnnouncementView::create([
            'tenant_id' => $tenantId,
            'announcement_id' => $announcement->id,
            'employee_id' => $employeeId,
            'viewed_at' => now(),
        ]);

        return true;
    }

    /**
     * Acknowledge announcement.
     */
    public function acknowledgeAnnouncement(Announcement $announcement, int $employeeId): bool
    {
        // Check if already acknowledged
        if ($announcement->isAcknowledgedBy($employeeId)) {
            return true;
        }

        $employee = Employee::findOrFail($employeeId);
        $tenantId = $employee->tenant_id;

        // Ensure announcement belongs to same tenant
        if ($announcement->tenant_id != $tenantId) {
            throw new \Exception('Unauthorized access to this resource');
        }

        AnnouncementAcknowledgment::create([
            'tenant_id' => $tenantId,
            'announcement_id' => $announcement->id,
            'employee_id' => $employeeId,
            'acknowledged_at' => now(),
        ]);

        return true;
    }

    /**
     * Toggle like on announcement.
     */
    public function toggleLike(Announcement $announcement, int $employeeId): bool
    {
        $employee = Employee::findOrFail($employeeId);
        $tenantId = $employee->tenant_id;

        // Ensure announcement belongs to same tenant
        if ($announcement->tenant_id != $tenantId) {
            throw new \Exception('Unauthorized access to this resource');
        }

        $like = AnnouncementLike::where('announcement_id', $announcement->id)
            ->where('employee_id', $employeeId)
            ->first();

        if ($like) {
            $like->delete();
            return false; // Unliked
        } else {
            AnnouncementLike::create([
                'tenant_id' => $tenantId,
                'announcement_id' => $announcement->id,
                'employee_id' => $employeeId,
            ]);
            return true; // Liked
        }
    }

    /**
     * Add comment to announcement.
     */
    public function addComment(Announcement $announcement, int $employeeId, string $comment): AnnouncementComment
    {
        $employee = Employee::findOrFail($employeeId);
        $tenantId = $employee->tenant_id;

        // Ensure announcement belongs to same tenant
        if ($announcement->tenant_id != $tenantId) {
            throw new \Exception('Unauthorized access to this resource');
        }

        return AnnouncementComment::create([
            'tenant_id' => $tenantId,
            'announcement_id' => $announcement->id,
            'employee_id' => $employeeId,
            'comment' => $comment,
        ]);
    }

    /**
     * Delete comment.
     */
    public function deleteComment(int $commentId, int $employeeId): bool
    {
        $comment = AnnouncementComment::findOrFail($commentId);

        // Only allow employee to delete their own comment
        if ($comment->employee_id !== $employeeId) {
            throw new \Exception('Unauthorized: You can only delete your own comments');
        }

        return $comment->delete();
    }

    /**
     * Get analytics data.
     */
    public function getAnalytics(array $filters = []): array
    {
        $currentUser = Auth::user();
        $tenantId = (int) ($currentUser->tenant_id ?? $currentUser->id);

        $query = Announcement::where('tenant_id', $tenantId);

        // Filter by date range if provided
        if (!empty($filters['start_date'])) {
            $query->where('created_at', '>=', $filters['start_date']);
        }
        if (!empty($filters['end_date'])) {
            $query->where('created_at', '<=', $filters['end_date']);
        }

        $total = $query->count();
        $published = (clone $query)->where('status', 'published')->count();
        $drafts = (clone $query)->where('status', 'draft')->count();
        $archived = (clone $query)->where('status', 'archived')->count();

        // Get published announcements for detailed analytics
        $publishedAnnouncements = (clone $query)
            ->where('status', 'published')
            ->with(['views', 'acknowledgments', 'likes', 'comments'])
            ->get();

        $totalViews = 0;
        $totalAcknowledged = 0;
        $totalLikes = 0;
        $totalComments = 0;
        $mandatoryAcknowledged = 0;
        $mandatoryTotal = 0;

        foreach ($publishedAnnouncements as $announcement) {
            $totalViews += $announcement->views()->count();
            $totalAcknowledged += $announcement->acknowledgments()->count();
            $totalLikes += $announcement->likes()->count();
            $totalComments += $announcement->comments()->count();

            if ($announcement->is_mandatory) {
                $mandatoryTotal++;
                $targetEmployees = $this->calculateTargetEmployees($announcement);
                $acknowledged = $announcement->acknowledgments()->count();
                if ($acknowledged >= count($targetEmployees)) {
                    $mandatoryAcknowledged++;
                }
            }
        }

        $acknowledgmentRate = $mandatoryTotal > 0 
            ? round(($mandatoryAcknowledged / $mandatoryTotal) * 100, 2) 
            : 0;

        return [
            'total_announcements' => $total,
            'published' => $published,
            'drafts' => $drafts,
            'archived' => $archived,
            'total_views' => $totalViews,
            'total_acknowledged' => $totalAcknowledged,
            'total_likes' => $totalLikes,
            'total_comments' => $totalComments,
            'acknowledgment_rate' => $acknowledgmentRate,
            'mandatory_acknowledged' => $mandatoryAcknowledged,
            'mandatory_total' => $mandatoryTotal,
        ];
    }

    /**
     * Send reminders to employees who haven't acknowledged.
     */
    public function sendReminders(Announcement $announcement): array
    {
        if (!$announcement->is_mandatory) {
            throw new \Exception('Reminders can only be sent for mandatory announcements');
        }

        $targetEmployees = $this->calculateTargetEmployees($announcement);
        $acknowledgedEmployeeIds = $announcement->acknowledgments()->pluck('employee_id')->toArray();

        $pendingEmployees = $targetEmployees->filter(function ($employee) use ($acknowledgedEmployeeIds) {
            return !in_array($employee->id, $acknowledgedEmployeeIds);
        });

        $remindersSent = 0;
        foreach ($pendingEmployees as $employee) {
            if ($employee->user_id) {
                try {
                    $employee->user->notify(new AnnouncementPublishedNotification($announcement));
                    $remindersSent++;
                } catch (\Exception $e) {
                    Log::warning('Failed to send reminder', [
                        'employee_id' => $employee->id,
                        'announcement_id' => $announcement->id,
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        }

        return [
            'reminders_sent' => $remindersSent,
            'pending_count' => $pendingEmployees->count(),
        ];
    }

    /**
     * Calculate target employees for an announcement.
     */
    private function calculateTargetEmployees(Announcement $announcement): Collection
    {
        $tenantId = $announcement->tenant_id;

        $query = Employee::where('tenant_id', $tenantId)
            ->where('employment_status', '!=', 'offboarded')
            ->whereNull('archived_at');

        switch ($announcement->target_audience_type) {
            case 'all_employees':
                // No additional filter needed
                break;

            case 'department_specific':
                if ($announcement->target_departments && is_array($announcement->target_departments)) {
                    $query->whereIn('department_id', $announcement->target_departments);
                } else {
                    return collect([]);
                }
                break;

            case 'individual':
                if ($announcement->target_employee_ids && is_array($announcement->target_employee_ids)) {
                    $query->whereIn('id', $announcement->target_employee_ids);
                } else {
                    return collect([]);
                }
                break;
        }

        return $query->get();
    }
}

