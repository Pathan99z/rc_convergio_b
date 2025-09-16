<?php

namespace App\Services;

use App\Models\Form;
use App\Models\ContactList;
use App\Models\Event;
use App\Models\Meeting;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class BulkOpsService
{
    /**
     * Bulk delete forms.
     */
    public function bulkDeleteForms(int $tenantId, array $formIds): array
    {
        $results = [];
        $successCount = 0;
        $errorCount = 0;

        foreach ($formIds as $formId) {
            try {
                $form = Form::where('tenant_id', $tenantId)->find($formId);
                
                if (!$form) {
                    $results[] = [
                        'form_id' => $formId,
                        'status' => 'error',
                        'message' => 'Form not found'
                    ];
                    $errorCount++;
                    continue;
                }

                $form->delete();
                
                $results[] = [
                    'form_id' => $formId,
                    'status' => 'success',
                    'message' => 'Form deleted successfully'
                ];
                $successCount++;

            } catch (\Exception $e) {
                $results[] = [
                    'form_id' => $formId,
                    'status' => 'error',
                    'message' => $e->getMessage()
                ];
                $errorCount++;
            }
        }

        return [
            'total_forms' => count($formIds),
            'success_count' => $successCount,
            'error_count' => $errorCount,
            'results' => $results,
        ];
    }

    /**
     * Bulk activate forms.
     */
    public function bulkActivateForms(int $tenantId, array $formIds): array
    {
        $results = [];
        $successCount = 0;
        $errorCount = 0;

        foreach ($formIds as $formId) {
            try {
                $form = Form::where('tenant_id', $tenantId)->find($formId);
                
                if (!$form) {
                    $results[] = [
                        'form_id' => $formId,
                        'status' => 'error',
                        'message' => 'Form not found'
                    ];
                    $errorCount++;
                    continue;
                }

                $form->update(['status' => 'active']);
                
                $results[] = [
                    'form_id' => $formId,
                    'status' => 'success',
                    'message' => 'Form activated successfully'
                ];
                $successCount++;

            } catch (\Exception $e) {
                $results[] = [
                    'form_id' => $formId,
                    'status' => 'error',
                    'message' => $e->getMessage()
                ];
                $errorCount++;
            }
        }

        return [
            'total_forms' => count($formIds),
            'success_count' => $successCount,
            'error_count' => $errorCount,
            'results' => $results,
        ];
    }

    /**
     * Bulk deactivate forms.
     */
    public function bulkDeactivateForms(int $tenantId, array $formIds): array
    {
        $results = [];
        $successCount = 0;
        $errorCount = 0;

        foreach ($formIds as $formId) {
            try {
                $form = Form::where('tenant_id', $tenantId)->find($formId);
                
                if (!$form) {
                    $results[] = [
                        'form_id' => $formId,
                        'status' => 'error',
                        'message' => 'Form not found'
                    ];
                    $errorCount++;
                    continue;
                }

                $form->update(['status' => 'inactive']);
                
                $results[] = [
                    'form_id' => $formId,
                    'status' => 'success',
                    'message' => 'Form deactivated successfully'
                ];
                $successCount++;

            } catch (\Exception $e) {
                $results[] = [
                    'form_id' => $formId,
                    'status' => 'error',
                    'message' => $e->getMessage()
                ];
                $errorCount++;
            }
        }

        return [
            'total_forms' => count($formIds),
            'success_count' => $successCount,
            'error_count' => $errorCount,
            'results' => $results,
        ];
    }

    /**
     * Bulk delete lists.
     */
    public function bulkDeleteLists(int $tenantId, array $listIds): array
    {
        $results = [];
        $successCount = 0;
        $errorCount = 0;

        foreach ($listIds as $listId) {
            try {
                $list = ContactList::where('tenant_id', $tenantId)->find($listId);
                
                if (!$list) {
                    $results[] = [
                        'list_id' => $listId,
                        'status' => 'error',
                        'message' => 'List not found'
                    ];
                    $errorCount++;
                    continue;
                }

                $list->delete();
                
                $results[] = [
                    'list_id' => $listId,
                    'status' => 'success',
                    'message' => 'List deleted successfully'
                ];
                $successCount++;

            } catch (\Exception $e) {
                $results[] = [
                    'list_id' => $listId,
                    'status' => 'error',
                    'message' => $e->getMessage()
                ];
                $errorCount++;
            }
        }

        return [
            'total_lists' => count($listIds),
            'success_count' => $successCount,
            'error_count' => $errorCount,
            'results' => $results,
        ];
    }

    /**
     * Bulk activate lists.
     */
    public function bulkActivateLists(int $tenantId, array $listIds): array
    {
        $results = [];
        $successCount = 0;
        $errorCount = 0;

        foreach ($listIds as $listId) {
            try {
                $list = ContactList::where('tenant_id', $tenantId)->find($listId);
                
                if (!$list) {
                    $results[] = [
                        'list_id' => $listId,
                        'status' => 'error',
                        'message' => 'List not found'
                    ];
                    $errorCount++;
                    continue;
                }

                $list->update(['status' => 'active']);
                
                $results[] = [
                    'list_id' => $listId,
                    'status' => 'success',
                    'message' => 'List activated successfully'
                ];
                $successCount++;

            } catch (\Exception $e) {
                $results[] = [
                    'list_id' => $listId,
                    'status' => 'error',
                    'message' => $e->getMessage()
                ];
                $errorCount++;
            }
        }

        return [
            'total_lists' => count($listIds),
            'success_count' => $successCount,
            'error_count' => $errorCount,
            'results' => $results,
        ];
    }

    /**
     * Bulk deactivate lists.
     */
    public function bulkDeactivateLists(int $tenantId, array $listIds): array
    {
        $results = [];
        $successCount = 0;
        $errorCount = 0;

        foreach ($listIds as $listId) {
            try {
                $list = ContactList::where('tenant_id', $tenantId)->find($listId);
                
                if (!$list) {
                    $results[] = [
                        'list_id' => $listId,
                        'status' => 'error',
                        'message' => 'List not found'
                    ];
                    $errorCount++;
                    continue;
                }

                $list->update(['status' => 'inactive']);
                
                $results[] = [
                    'list_id' => $listId,
                    'status' => 'success',
                    'message' => 'List deactivated successfully'
                ];
                $successCount++;

            } catch (\Exception $e) {
                $results[] = [
                    'list_id' => $listId,
                    'status' => 'error',
                    'message' => $e->getMessage()
                ];
                $errorCount++;
            }
        }

        return [
            'total_lists' => count($listIds),
            'success_count' => $successCount,
            'error_count' => $errorCount,
            'results' => $results,
        ];
    }

    /**
     * Export lists.
     */
    public function exportLists(int $tenantId, array $filters = []): array
    {
        $query = ContactList::where('tenant_id', $tenantId);

        // Apply filters
        if (isset($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (isset($filters['type'])) {
            $query->where('type', $filters['type']);
        }

        if (isset($filters['date_from'])) {
            $query->where('created_at', '>=', $filters['date_from']);
        }

        if (isset($filters['date_to'])) {
            $query->where('created_at', '<=', $filters['date_to']);
        }

        $lists = $query->get();

        $format = $filters['format'] ?? 'json';
        $filename = 'lists_export_' . now()->format('Y-m-d_H-i-s') . '.' . $format;

        // Generate export data
        $exportData = $lists->map(function ($list) {
            return [
                'id' => $list->id,
                'name' => $list->name,
                'description' => $list->description,
                'type' => $list->type,
                'status' => $list->status ?? 'active',
                'created_at' => $list->created_at,
                'updated_at' => $list->updated_at,
            ];
        });

        // Store file temporarily
        $filePath = 'exports/' . $filename;
        Storage::put($filePath, json_encode($exportData, JSON_PRETTY_PRINT));

        return [
            'filename' => $filename,
            'file_path' => $filePath,
            'download_url' => Storage::url($filePath),
            'total_lists' => $lists->count(),
            'format' => $format,
        ];
    }

    /**
     * Import lists.
     */
    public function importLists(int $tenantId, UploadedFile $file, int $userId): array
    {
        $results = [];
        $successCount = 0;
        $errorCount = 0;

        try {
            // Read file content
            $content = file_get_contents($file->getPathname());
            $data = json_decode($content, true);

            if (!$data) {
                throw new \Exception('Invalid file format');
            }

            foreach ($data as $row) {
                try {
                    $listData = [
                        'name' => $row['name'] ?? 'Imported List',
                        'description' => $row['description'] ?? null,
                        'type' => $row['type'] ?? 'static',
                        'status' => $row['status'] ?? 'active',
                        'rule' => $row['rule'] ?? null,
                        'tenant_id' => $tenantId,
                        'created_by' => $userId,
                    ];

                    ContactList::create($listData);
                    $successCount++;

                } catch (\Exception $e) {
                    $errorCount++;
                    $results[] = [
                        'row' => $row,
                        'error' => $e->getMessage()
                    ];
                }
            }

        } catch (\Exception $e) {
            throw new \Exception('Failed to process import file: ' . $e->getMessage());
        }

        return [
            'total_rows' => count($data),
            'success_count' => $successCount,
            'error_count' => $errorCount,
            'errors' => $results,
        ];
    }

    /**
     * Export single list.
     */
    public function exportSingleList(int $tenantId, int $listId): array
    {
        $list = ContactList::where('tenant_id', $tenantId)->findOrFail($listId);
        
        $filename = 'list_' . $list->id . '_export_' . now()->format('Y-m-d_H-i-s') . '.json';

        $exportData = [
            'id' => $list->id,
            'name' => $list->name,
            'description' => $list->description,
            'type' => $list->type,
            'status' => $list->status ?? 'active',
            'rule' => $list->rule,
            'created_at' => $list->created_at,
            'updated_at' => $list->updated_at,
            'contacts' => $list->contacts()->get()->map(function ($contact) {
                return [
                    'id' => $contact->id,
                    'name' => $contact->name,
                    'email' => $contact->email,
                    'phone' => $contact->phone,
                ];
            })
        ];

        $filePath = 'exports/' . $filename;
        Storage::put($filePath, json_encode($exportData, JSON_PRETTY_PRINT));

        return [
            'filename' => $filename,
            'file_path' => $filePath,
            'download_url' => Storage::url($filePath),
            'list_name' => $list->name,
            'total_contacts' => $list->contacts()->count(),
        ];
    }

    /**
     * Bulk delete events.
     */
    public function bulkDeleteEvents(int $tenantId, array $eventIds): array
    {
        $results = [];
        $successCount = 0;
        $errorCount = 0;

        foreach ($eventIds as $eventId) {
            try {
                $event = Event::where('tenant_id', $tenantId)->find($eventId);
                
                if (!$event) {
                    $results[] = [
                        'event_id' => $eventId,
                        'status' => 'error',
                        'message' => 'Event not found'
                    ];
                    $errorCount++;
                    continue;
                }

                $event->delete();
                
                $results[] = [
                    'event_id' => $eventId,
                    'status' => 'success',
                    'message' => 'Event deleted successfully'
                ];
                $successCount++;

            } catch (\Exception $e) {
                $results[] = [
                    'event_id' => $eventId,
                    'status' => 'error',
                    'message' => $e->getMessage()
                ];
                $errorCount++;
            }
        }

        return [
            'total_events' => count($eventIds),
            'success_count' => $successCount,
            'error_count' => $errorCount,
            'results' => $results,
        ];
    }

    /**
     * Bulk cancel events.
     */
    public function bulkCancelEvents(int $tenantId, array $eventIds): array
    {
        $results = [];
        $successCount = 0;
        $errorCount = 0;

        foreach ($eventIds as $eventId) {
            try {
                $event = Event::where('tenant_id', $tenantId)->find($eventId);
                
                if (!$event) {
                    $results[] = [
                        'event_id' => $eventId,
                        'status' => 'error',
                        'message' => 'Event not found'
                    ];
                    $errorCount++;
                    continue;
                }

                $event->update([
                    'is_active' => false,
                    'cancelled_at' => now(),
                ]);
                
                $results[] = [
                    'event_id' => $eventId,
                    'status' => 'success',
                    'message' => 'Event cancelled successfully'
                ];
                $successCount++;

            } catch (\Exception $e) {
                $results[] = [
                    'event_id' => $eventId,
                    'status' => 'error',
                    'message' => $e->getMessage()
                ];
                $errorCount++;
            }
        }

        return [
            'total_events' => count($eventIds),
            'success_count' => $successCount,
            'error_count' => $errorCount,
            'results' => $results,
        ];
    }

    /**
     * Bulk activate events.
     */
    public function bulkActivateEvents(int $tenantId, array $eventIds): array
    {
        $results = [];
        $successCount = 0;
        $errorCount = 0;

        foreach ($eventIds as $eventId) {
            try {
                $event = Event::where('tenant_id', $tenantId)->find($eventId);
                
                if (!$event) {
                    $results[] = [
                        'event_id' => $eventId,
                        'status' => 'error',
                        'message' => 'Event not found'
                    ];
                    $errorCount++;
                    continue;
                }

                $event->update([
                    'is_active' => true,
                    'cancelled_at' => null,
                ]);
                
                $results[] = [
                    'event_id' => $eventId,
                    'status' => 'success',
                    'message' => 'Event activated successfully'
                ];
                $successCount++;

            } catch (\Exception $e) {
                $results[] = [
                    'event_id' => $eventId,
                    'status' => 'error',
                    'message' => $e->getMessage()
                ];
                $errorCount++;
            }
        }

        return [
            'total_events' => count($eventIds),
            'success_count' => $successCount,
            'error_count' => $errorCount,
            'results' => $results,
        ];
    }

    /**
     * Export events.
     */
    public function exportEvents(int $tenantId, array $filters = []): array
    {
        $query = Event::where('tenant_id', $tenantId);

        // Apply filters
        if (isset($filters['type'])) {
            $query->where('type', $filters['type']);
        }

        if (isset($filters['is_active'])) {
            $query->where('is_active', $filters['is_active']);
        }

        if (isset($filters['date_from'])) {
            $query->where('created_at', '>=', $filters['date_from']);
        }

        if (isset($filters['date_to'])) {
            $query->where('created_at', '<=', $filters['date_to']);
        }

        $events = $query->get();

        $format = $filters['format'] ?? 'json';
        $filename = 'events_export_' . now()->format('Y-m-d_H-i-s') . '.' . $format;

        // Generate export data
        $exportData = $events->map(function ($event) {
            return [
                'id' => $event->id,
                'name' => $event->name,
                'description' => $event->description,
                'type' => $event->type,
                'scheduled_at' => $event->scheduled_at,
                'location' => $event->location,
                'is_active' => $event->is_active,
                'created_at' => $event->created_at,
                'updated_at' => $event->updated_at,
            ];
        });

        // Store file temporarily
        $filePath = 'exports/' . $filename;
        Storage::put($filePath, json_encode($exportData, JSON_PRETTY_PRINT));

        return [
            'filename' => $filename,
            'file_path' => $filePath,
            'download_url' => Storage::url($filePath),
            'total_events' => $events->count(),
            'format' => $format,
        ];
    }

    /**
     * Import events.
     */
    public function importEvents(int $tenantId, UploadedFile $file, int $userId): array
    {
        $results = [];
        $successCount = 0;
        $errorCount = 0;

        try {
            // Read file content
            $content = file_get_contents($file->getPathname());
            $data = json_decode($content, true);

            if (!$data) {
                throw new \Exception('Invalid file format');
            }

            foreach ($data as $row) {
                try {
                    $eventData = [
                        'name' => $row['name'] ?? 'Imported Event',
                        'description' => $row['description'] ?? null,
                        'type' => $row['type'] ?? 'webinar',
                        'scheduled_at' => $row['scheduled_at'] ?? now()->addDays(7),
                        'location' => $row['location'] ?? null,
                        'is_active' => $row['is_active'] ?? true,
                        'settings' => $row['settings'] ?? null,
                        'tenant_id' => $tenantId,
                        'created_by' => $userId,
                    ];

                    Event::create($eventData);
                    $successCount++;

                } catch (\Exception $e) {
                    $errorCount++;
                    $results[] = [
                        'row' => $row,
                        'error' => $e->getMessage()
                    ];
                }
            }

        } catch (\Exception $e) {
            throw new \Exception('Failed to process import file: ' . $e->getMessage());
        }

        return [
            'total_rows' => count($data),
            'success_count' => $successCount,
            'error_count' => $errorCount,
            'errors' => $results,
        ];
    }

    /**
     * Export single event.
     */
    public function exportSingleEvent(int $tenantId, int $eventId): array
    {
        $event = Event::where('tenant_id', $tenantId)->findOrFail($eventId);
        
        $filename = 'event_' . $event->id . '_export_' . now()->format('Y-m-d_H-i-s') . '.json';

        $exportData = [
            'id' => $event->id,
            'name' => $event->name,
            'description' => $event->description,
            'type' => $event->type,
            'scheduled_at' => $event->scheduled_at,
            'location' => $event->location,
            'is_active' => $event->is_active,
            'settings' => $event->settings,
            'created_at' => $event->created_at,
            'updated_at' => $event->updated_at,
            'attendees' => $event->attendees()->get()->map(function ($attendee) {
                return [
                    'id' => $attendee->id,
                    'contact_id' => $attendee->contact_id,
                    'rsvp_status' => $attendee->rsvp_status,
                    'attended' => $attendee->attended,
                ];
            })
        ];

        $filePath = 'exports/' . $filename;
        Storage::put($filePath, json_encode($exportData, JSON_PRETTY_PRINT));

        return [
            'filename' => $filename,
            'file_path' => $filePath,
            'download_url' => Storage::url($filePath),
            'event_name' => $event->name,
            'total_attendees' => $event->attendees()->count(),
        ];
    }

    /**
     * Cancel single event.
     */
    public function cancelEvent(int $tenantId, int $eventId): array
    {
        $event = Event::where('tenant_id', $tenantId)->findOrFail($eventId);
        
        $event->update([
            'is_active' => false,
            'cancelled_at' => now(),
        ]);

        return [
            'event_id' => $event->id,
            'status' => 'cancelled',
            'cancelled_at' => $event->cancelled_at,
            'message' => 'Event cancelled successfully',
        ];
    }

    /**
     * Reschedule single event.
     */
    public function rescheduleEvent(int $tenantId, int $eventId, string $newScheduledAt): array
    {
        $event = Event::where('tenant_id', $tenantId)->findOrFail($eventId);
        
        $event->update([
            'scheduled_at' => Carbon::parse($newScheduledAt),
            'is_active' => true,
            'cancelled_at' => null,
        ]);

        return [
            'event_id' => $event->id,
            'scheduled_at' => $event->scheduled_at,
            'message' => 'Event rescheduled successfully',
        ];
    }

    /**
     * Bulk delete meetings.
     */
    public function bulkDeleteMeetings(int $tenantId, array $meetingIds): array
    {
        $results = [];
        $successCount = 0;
        $errorCount = 0;

        foreach ($meetingIds as $meetingId) {
            try {
                $meeting = Meeting::where('tenant_id', $tenantId)->find($meetingId);
                
                if (!$meeting) {
                    $results[] = [
                        'meeting_id' => $meetingId,
                        'status' => 'error',
                        'message' => 'Meeting not found'
                    ];
                    $errorCount++;
                    continue;
                }

                $meeting->delete();
                
                $results[] = [
                    'meeting_id' => $meetingId,
                    'status' => 'success',
                    'message' => 'Meeting deleted successfully'
                ];
                $successCount++;

            } catch (\Exception $e) {
                $results[] = [
                    'meeting_id' => $meetingId,
                    'status' => 'error',
                    'message' => $e->getMessage()
                ];
                $errorCount++;
            }
        }

        return [
            'total_meetings' => count($meetingIds),
            'success_count' => $successCount,
            'error_count' => $errorCount,
            'results' => $results,
        ];
    }

    /**
     * Bulk cancel meetings.
     */
    public function bulkCancelMeetings(int $tenantId, array $meetingIds): array
    {
        $results = [];
        $successCount = 0;
        $errorCount = 0;

        foreach ($meetingIds as $meetingId) {
            try {
                $meeting = Meeting::where('tenant_id', $tenantId)->find($meetingId);
                
                if (!$meeting) {
                    $results[] = [
                        'meeting_id' => $meetingId,
                        'status' => 'error',
                        'message' => 'Meeting not found'
                    ];
                    $errorCount++;
                    continue;
                }

                $meeting->update([
                    'status' => 'cancelled',
                    'cancelled_at' => now(),
                ]);
                
                $results[] = [
                    'meeting_id' => $meetingId,
                    'status' => 'success',
                    'message' => 'Meeting cancelled successfully'
                ];
                $successCount++;

            } catch (\Exception $e) {
                $results[] = [
                    'meeting_id' => $meetingId,
                    'status' => 'error',
                    'message' => $e->getMessage()
                ];
                $errorCount++;
            }
        }

        return [
            'total_meetings' => count($meetingIds),
            'success_count' => $successCount,
            'error_count' => $errorCount,
            'results' => $results,
        ];
    }

    /**
     * Bulk reschedule meetings.
     */
    public function bulkRescheduleMeetings(int $tenantId, array $meetingIds, string $newScheduledAt): array
    {
        $results = [];
        $successCount = 0;
        $errorCount = 0;

        foreach ($meetingIds as $meetingId) {
            try {
                $meeting = Meeting::where('tenant_id', $tenantId)->find($meetingId);
                
                if (!$meeting) {
                    $results[] = [
                        'meeting_id' => $meetingId,
                        'status' => 'error',
                        'message' => 'Meeting not found'
                    ];
                    $errorCount++;
                    continue;
                }

                $meeting->update([
                    'scheduled_at' => Carbon::parse($newScheduledAt),
                    'status' => 'scheduled',
                    'cancelled_at' => null,
                ]);
                
                $results[] = [
                    'meeting_id' => $meetingId,
                    'status' => 'success',
                    'message' => 'Meeting rescheduled successfully'
                ];
                $successCount++;

            } catch (\Exception $e) {
                $results[] = [
                    'meeting_id' => $meetingId,
                    'status' => 'error',
                    'message' => $e->getMessage()
                ];
                $errorCount++;
            }
        }

        return [
            'total_meetings' => count($meetingIds),
            'success_count' => $successCount,
            'error_count' => $errorCount,
            'results' => $results,
        ];
    }

    /**
     * Export meetings.
     */
    public function exportMeetings(int $tenantId, array $filters = []): array
    {
        $query = Meeting::where('tenant_id', $tenantId);

        // Apply filters
        if (isset($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (isset($filters['integration_provider'])) {
            $query->where('integration_provider', $filters['integration_provider']);
        }

        if (isset($filters['date_from'])) {
            $query->where('created_at', '>=', $filters['date_from']);
        }

        if (isset($filters['date_to'])) {
            $query->where('created_at', '<=', $filters['date_to']);
        }

        $meetings = $query->get();

        $format = $filters['format'] ?? 'json';
        $filename = 'meetings_export_' . now()->format('Y-m-d_H-i-s') . '.' . $format;

        // Generate export data
        $exportData = $meetings->map(function ($meeting) {
            return [
                'id' => $meeting->id,
                'title' => $meeting->title,
                'description' => $meeting->description,
                'contact_id' => $meeting->contact_id,
                'user_id' => $meeting->user_id,
                'scheduled_at' => $meeting->scheduled_at,
                'duration_minutes' => $meeting->duration_minutes,
                'location' => $meeting->location,
                'status' => $meeting->status,
                'integration_provider' => $meeting->integration_provider,
                'created_at' => $meeting->created_at,
                'updated_at' => $meeting->updated_at,
            ];
        });

        // Store file temporarily
        $filePath = 'exports/' . $filename;
        Storage::put($filePath, json_encode($exportData, JSON_PRETTY_PRINT));

        return [
            'filename' => $filename,
            'file_path' => $filePath,
            'download_url' => Storage::url($filePath),
            'total_meetings' => $meetings->count(),
            'format' => $format,
        ];
    }

    /**
     * Import meetings.
     */
    public function importMeetings(int $tenantId, UploadedFile $file, int $userId): array
    {
        $results = [];
        $successCount = 0;
        $errorCount = 0;

        try {
            // Read file content
            $content = file_get_contents($file->getPathname());
            $data = json_decode($content, true);

            if (!$data) {
                throw new \Exception('Invalid file format');
            }

            foreach ($data as $row) {
                try {
                    $meetingData = [
                        'title' => $row['title'] ?? 'Imported Meeting',
                        'description' => $row['description'] ?? null,
                        'contact_id' => $row['contact_id'] ?? 1, // Default contact
                        'user_id' => $userId,
                        'scheduled_at' => $row['scheduled_at'] ?? now()->addDays(1),
                        'duration_minutes' => $row['duration_minutes'] ?? 30,
                        'location' => $row['location'] ?? null,
                        'status' => $row['status'] ?? 'scheduled',
                        'integration_provider' => $row['integration_provider'] ?? 'manual',
                        'integration_data' => $row['integration_data'] ?? null,
                        'attendees' => $row['attendees'] ?? null,
                        'notes' => $row['notes'] ?? null,
                        'tenant_id' => $tenantId,
                    ];

                    Meeting::create($meetingData);
                    $successCount++;

                } catch (\Exception $e) {
                    $errorCount++;
                    $results[] = [
                        'row' => $row,
                        'error' => $e->getMessage()
                    ];
                }
            }

        } catch (\Exception $e) {
            throw new \Exception('Failed to process import file: ' . $e->getMessage());
        }

        return [
            'total_rows' => count($data),
            'success_count' => $successCount,
            'error_count' => $errorCount,
            'errors' => $results,
        ];
    }

    /**
     * Export single meeting.
     */
    public function exportSingleMeeting(int $tenantId, int $meetingId): array
    {
        $meeting = Meeting::where('tenant_id', $tenantId)->findOrFail($meetingId);
        
        $filename = 'meeting_' . $meeting->id . '_export_' . now()->format('Y-m-d_H-i-s') . '.json';

        $exportData = [
            'id' => $meeting->id,
            'title' => $meeting->title,
            'description' => $meeting->description,
            'contact_id' => $meeting->contact_id,
            'user_id' => $meeting->user_id,
            'scheduled_at' => $meeting->scheduled_at,
            'duration_minutes' => $meeting->duration_minutes,
            'location' => $meeting->location,
            'status' => $meeting->status,
            'integration_provider' => $meeting->integration_provider,
            'integration_data' => $meeting->integration_data,
            'attendees' => $meeting->attendees,
            'notes' => $meeting->notes,
            'created_at' => $meeting->created_at,
            'updated_at' => $meeting->updated_at,
        ];

        $filePath = 'exports/' . $filename;
        Storage::put($filePath, json_encode($exportData, JSON_PRETTY_PRINT));

        return [
            'filename' => $filename,
            'file_path' => $filePath,
            'download_url' => Storage::url($filePath),
            'meeting_title' => $meeting->title,
        ];
    }

    /**
     * Cancel single meeting.
     */
    public function cancelMeeting(int $tenantId, int $meetingId): array
    {
        $meeting = Meeting::where('tenant_id', $tenantId)->findOrFail($meetingId);
        
        $meeting->update([
            'status' => 'cancelled',
            'cancelled_at' => now(),
        ]);

        return [
            'meeting_id' => $meeting->id,
            'status' => 'cancelled',
            'cancelled_at' => $meeting->cancelled_at,
            'message' => 'Meeting cancelled successfully',
        ];
    }

    /**
     * Reschedule single meeting.
     */
    public function rescheduleMeeting(int $tenantId, int $meetingId, string $newScheduledAt): array
    {
        $meeting = Meeting::where('tenant_id', $tenantId)->findOrFail($meetingId);
        
        $meeting->update([
            'scheduled_at' => Carbon::parse($newScheduledAt),
            'status' => 'scheduled',
            'cancelled_at' => null,
        ]);

        return [
            'meeting_id' => $meeting->id,
            'scheduled_at' => $meeting->scheduled_at,
            'message' => 'Meeting rescheduled successfully',
        ];
    }
}

