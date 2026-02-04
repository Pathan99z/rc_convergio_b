<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Hr\Announcement;
use App\Models\Hr\Employee;
use App\Services\Hr\AnnouncementService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class EmployeeAnnouncementController extends Controller
{
    protected AnnouncementService $announcementService;

    public function __construct(AnnouncementService $announcementService)
    {
        $this->announcementService = $announcementService;
    }

    /**
     * Get employee's announcements feed.
     * 
     * GET /api/employee/announcements
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $employee = Employee::where('user_id', $user->id)->firstOrFail();

        // Filter out empty values
        $filters = [];
        if ($request->filled('category')) {
            $filters['category'] = $request->query('category');
        }
        if ($request->filled('sortBy')) {
            $filters['sortBy'] = $request->query('sortBy');
        }
        if ($request->filled('sortOrder')) {
            $filters['sortOrder'] = $request->query('sortOrder');
        }

        $perPage = min((int) $request->query('per_page', 15), 100);
        $announcements = $this->announcementService->getAnnouncementsForEmployee($employee->id, $filters, $perPage);

        return response()->json([
            'success' => true,
            'data' => $announcements->items(),
            'meta' => [
                'current_page' => $announcements->currentPage(),
                'per_page' => $announcements->perPage(),
                'total' => $announcements->total(),
                'last_page' => $announcements->lastPage(),
            ],
        ]);
    }

    /**
     * View announcement details (auto-tracks view).
     * 
     * GET /api/employee/announcements/{id}
     */
    public function show(int $id): JsonResponse
    {
        $user = request()->user();
        $employee = Employee::where('user_id', $user->id)->firstOrFail();

        $announcement = Announcement::with(['creator', 'publishedBy', 'likes.employee', 'comments.employee'])
            ->findOrFail($id);

        // Verify employee has access to this announcement
        $announcements = $this->announcementService->getAnnouncementsForEmployee($employee->id, [], 1000);
        $hasAccess = $announcements->getCollection()->contains('id', $announcement->id);

        if (!$hasAccess) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized access to this resource',
            ], 403);
        }

        // Auto-track view
        try {
            $this->announcementService->markAsViewed($announcement, $employee->id);
        } catch (\Exception $e) {
            // Log but don't fail the request
            \Log::warning('Failed to track announcement view', [
                'announcement_id' => $announcement->id,
                'employee_id' => $employee->id,
                'error' => $e->getMessage(),
            ]);
        }

        // Add engagement data
        $announcement->is_viewed = $announcement->isViewedBy($employee->id);
        $announcement->is_acknowledged = $announcement->isAcknowledgedBy($employee->id);
        $announcement->is_liked = $announcement->isLikedBy($employee->id);
        $announcement->views_count = $announcement->views()->count();
        $announcement->likes_count = $announcement->likes()->count();
        $announcement->comments_count = $announcement->comments()->count();
        $announcement->acknowledgments_count = $announcement->acknowledgments()->count();

        return response()->json([
            'success' => true,
            'data' => $announcement,
        ]);
    }

    /**
     * Mark announcement as viewed.
     * 
     * POST /api/employee/announcements/{id}/view
     */
    public function view(int $id): JsonResponse
    {
        $user = request()->user();
        $employee = Employee::where('user_id', $user->id)->firstOrFail();

        $announcement = Announcement::findOrFail($id);

        try {
            $this->announcementService->markAsViewed($announcement, $employee->id);

            return response()->json([
                'success' => true,
                'message' => 'Announcement marked as viewed',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Acknowledge announcement (if mandatory).
     * 
     * POST /api/employee/announcements/{id}/acknowledge
     */
    public function acknowledge(int $id): JsonResponse
    {
        $user = request()->user();
        $employee = Employee::where('user_id', $user->id)->firstOrFail();

        $announcement = Announcement::findOrFail($id);

        if (!$announcement->is_mandatory) {
            return response()->json([
                'success' => false,
                'message' => 'This announcement is not mandatory',
            ], 400);
        }

        try {
            $this->announcementService->acknowledgeAnnouncement($announcement, $employee->id);

            return response()->json([
                'success' => true,
                'message' => 'Announcement acknowledged successfully',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Toggle like on announcement.
     * 
     * POST /api/employee/announcements/{id}/like
     */
    public function like(int $id): JsonResponse
    {
        $user = request()->user();
        $employee = Employee::where('user_id', $user->id)->firstOrFail();

        $announcement = Announcement::findOrFail($id);

        try {
            $isLiked = $this->announcementService->toggleLike($announcement, $employee->id);

            return response()->json([
                'success' => true,
                'data' => [
                    'is_liked' => $isLiked,
                    'likes_count' => $announcement->likes()->count(),
                ],
                'message' => $isLiked ? 'Announcement liked' : 'Announcement unliked',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Add comment to announcement.
     * 
     * POST /api/employee/announcements/{id}/comment
     */
    public function comment(Request $request, int $id): JsonResponse
    {
        $user = request()->user();
        $employee = Employee::where('user_id', $user->id)->firstOrFail();

        $validator = Validator::make($request->all(), [
            'comment' => 'required|string|max:1000',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $announcement = Announcement::findOrFail($id);

        try {
            $comment = $this->announcementService->addComment(
                $announcement,
                $employee->id,
                $validator->validated()['comment']
            );

            return response()->json([
                'success' => true,
                'data' => $comment->load('employee'),
                'message' => 'Comment added successfully',
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Delete own comment.
     * 
     * DELETE /api/employee/announcements/{id}/comment/{commentId}
     */
    public function deleteComment(int $id, int $commentId): JsonResponse
    {
        $user = request()->user();
        $employee = Employee::where('user_id', $user->id)->firstOrFail();

        try {
            $this->announcementService->deleteComment($commentId, $employee->id);

            return response()->json([
                'success' => true,
                'message' => 'Comment deleted successfully',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Download/preview announcement attachment.
     * 
     * GET /api/employee/announcements/{id}/attachment
     */
    public function attachment(int $id)
    {
        $user = request()->user();
        $employee = Employee::where('user_id', $user->id)->firstOrFail();

        $announcement = Announcement::findOrFail($id);

        // Verify employee has access to this announcement
        $announcements = $this->announcementService->getAnnouncementsForEmployee($employee->id, [], 1000);
        $hasAccess = $announcements->getCollection()->contains('id', $announcement->id);

        if (!$hasAccess) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized access to this resource',
            ], 403);
        }

        // Get the raw attachment_url value (before accessor converts it)
        $rawUrl = $announcement->getRawOriginal('attachment_url');
        
        if (!$rawUrl) {
            return response()->json([
                'success' => false,
                'message' => 'No attachment found for this announcement',
            ], 404);
        }

        // If it's a storage path (e.g., "announcements/1/filename.png"), use it directly
        $filePath = $rawUrl;
        
        // If it's a URL, try to extract the path
        if (str_starts_with($rawUrl, 'http') || str_starts_with($rawUrl, '/')) {
            // Try to extract path from URL
            $urlPath = parse_url($rawUrl, PHP_URL_PATH);
            if ($urlPath && str_contains($urlPath, 'announcements/')) {
                // Extract the path after /storage/ or directly
                $filePath = str_replace('/storage/', '', $urlPath);
                if (!str_starts_with($filePath, 'announcements/')) {
                    // If still not a path, try to find by filename
                    $filename = basename($urlPath);
                    $tenantId = $announcement->tenant_id;
                    $filePath = "announcements/{$tenantId}/{$filename}";
                }
            } else {
                // Fallback: try to find file by announcement creation time
                $tenantId = $announcement->tenant_id;
                $storagePath = "announcements/{$tenantId}";
                $files = Storage::disk('public')->files($storagePath);
                
                if (empty($files)) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Attachment file not found',
                    ], 404);
                }
                
                // Find file by creation time
                $announcementTime = $announcement->created_at->timestamp;
                foreach ($files as $file) {
                    $fileTime = Storage::disk('public')->lastModified($file);
                    if (abs($fileTime - $announcementTime) < 5) {
                        $filePath = $file;
                        break;
                    }
                }
                
                // If still not found, use most recent
                if (!Storage::disk('public')->exists($filePath)) {
                    usort($files, function($a, $b) {
                        return Storage::disk('public')->lastModified($b) - Storage::disk('public')->lastModified($a);
                    });
                    $filePath = $files[0];
                }
            }
        }

        // Check if file exists
        if (!Storage::disk('public')->exists($filePath)) {
            return response()->json([
                'success' => false,
                'message' => 'Attachment file not found',
            ], 404);
        }

        // Get file content and MIME type
        $fileContent = Storage::disk('public')->get($filePath);
        $mimeType = Storage::disk('public')->mimeType($filePath);
        $filename = basename($filePath);

        // Return file with appropriate headers
        return response($fileContent)
            ->header('Content-Type', $mimeType)
            ->header('Content-Disposition', 'inline; filename="' . $filename . '"')
            ->header('Cache-Control', 'public, max-age=3600');
    }
}

