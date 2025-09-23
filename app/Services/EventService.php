<?php

namespace App\Services;

use App\Models\Event;
use App\Models\EventAttendee;
use App\Models\Contact;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class EventService
{
    /**
     * Create a new event with tenant isolation.
     */
    public function createEvent(array $data, int $tenantId, int $createdBy): Event
    {
        return DB::transaction(function () use ($data, $tenantId, $createdBy) {
            // Ensure tenant isolation
            $eventData = array_merge($data, [
                'tenant_id' => $tenantId,
                'created_by' => $createdBy,
            ]);

            Log::info('EventService: Creating event', [
                'tenant_id' => $tenantId,
                'created_by' => $createdBy,
                'name' => $data['name'] ?? 'N/A'
            ]);

            $event = Event::create($eventData);

            return $event->load(['attendees.contact:id,first_name,last_name,email']);
        });
    }

    /**
     * Update an event with tenant isolation.
     */
    public function updateEvent(Event $event, array $data, int $tenantId): Event
    {
        // Verify tenant isolation
        if ($event->tenant_id !== $tenantId) {
            throw new \Exception('Unauthorized: Event does not belong to tenant');
        }

        return DB::transaction(function () use ($event, $data) {
            $event->update($data);
            
            Log::info('EventService: Event updated', [
                'event_id' => $event->id,
                'tenant_id' => $event->tenant_id,
                'updated_fields' => array_keys($data)
            ]);

            return $event->fresh(['attendees.contact:id,first_name,last_name,email']);
        });
    }

    /**
     * Add attendee to event with tenant isolation.
     */
    public function addAttendee(Event $event, int $contactId, string $rsvpStatus, array $metadata = [], int $tenantId): EventAttendee
    {
        // Verify tenant isolation
        if ($event->tenant_id !== $tenantId) {
            throw new \Exception('Unauthorized: Event does not belong to tenant');
        }

        // Verify contact belongs to same tenant
        $contact = Contact::where('id', $contactId)
            ->where('tenant_id', $tenantId)
            ->firstOrFail();

        return DB::transaction(function () use ($event, $contactId, $rsvpStatus, $metadata, $tenantId) {
            // Check if attendee already exists
            $existingAttendee = EventAttendee::where('event_id', $event->id)
                ->where('contact_id', $contactId)
                ->where('tenant_id', $tenantId)
                ->first();

            if ($existingAttendee) {
                // Update existing RSVP
                $existingAttendee->updateRsvpStatus($rsvpStatus);
                $attendee = $existingAttendee;
            } else {
                // Create new attendee
                $attendee = EventAttendee::create([
                    'event_id' => $event->id,
                    'contact_id' => $contactId,
                    'rsvp_status' => $rsvpStatus,
                    'rsvp_at' => now(),
                    'metadata' => $metadata,
                    'tenant_id' => $tenantId,
                ]);
            }

            Log::info('EventService: Attendee added/updated', [
                'event_id' => $event->id,
                'contact_id' => $contactId,
                'rsvp_status' => $rsvpStatus,
                'tenant_id' => $tenantId
            ]);

            return $attendee->load('contact:id,first_name,last_name,email');
        });
    }

    /**
     * Mark attendee as attended with tenant isolation.
     */
    public function markAttended(Event $event, int $attendeeId, int $tenantId): EventAttendee
    {
        // Verify tenant isolation
        if ($event->tenant_id !== $tenantId) {
            throw new \Exception('Unauthorized: Event does not belong to tenant');
        }

        $attendee = EventAttendee::where('id', $attendeeId)
            ->where('event_id', $event->id)
            ->where('tenant_id', $tenantId)
            ->firstOrFail();

        $attendee->markAsAttended();

        Log::info('EventService: Attendee marked as attended', [
            'event_id' => $event->id,
            'attendee_id' => $attendeeId,
            'tenant_id' => $tenantId
        ]);

        return $attendee->load('contact:id,first_name,last_name,email');
    }

    /**
     * Delete event with tenant isolation.
     */
    public function deleteEvent(Event $event, int $tenantId): bool
    {
        // Verify tenant isolation
        if ($event->tenant_id !== $tenantId) {
            throw new \Exception('Unauthorized: Event does not belong to tenant');
        }

        Log::info('EventService: Event deleted', [
            'event_id' => $event->id,
            'tenant_id' => $tenantId
        ]);

        return $event->delete();
    }

    /**
     * Get events for tenant with proper isolation.
     */
    public function getEventsForTenant(int $tenantId, array $filters = []): \Illuminate\Contracts\Pagination\LengthAwarePaginator
    {
        $query = Event::where('tenant_id', $tenantId)
            ->with(['attendees.contact:id,first_name,last_name,email']);

        // Apply filters
        if (isset($filters['type'])) {
            $query->where('type', $filters['type']);
        }

        if (isset($filters['status'])) {
            switch ($filters['status']) {
                case 'upcoming':
                    $query->upcoming();
                    break;
                case 'past':
                    $query->past();
                    break;
                case 'active':
                    $query->active();
                    break;
            }
        }

        return $query->orderBy('scheduled_at', 'desc')
            ->paginate($filters['per_page'] ?? 15);
    }

    /**
     * Get event attendees with tenant isolation.
     */
    public function getEventAttendees(Event $event, int $tenantId, array $filters = []): \Illuminate\Contracts\Pagination\LengthAwarePaginator
    {
        // Verify tenant isolation
        if ($event->tenant_id !== $tenantId) {
            throw new \Exception('Unauthorized: Event does not belong to tenant');
        }

        $query = EventAttendee::where('event_id', $event->id)
            ->where('tenant_id', $tenantId)
            ->with('contact:id,first_name,last_name,email');

        // Apply filters
        if (isset($filters['rsvp_status'])) {
            $query->where('rsvp_status', $filters['rsvp_status']);
        }

        if (isset($filters['attended'])) {
            $query->where('attended', $filters['attended']);
        }

        return $query->orderBy('created_at', 'desc')
            ->paginate($filters['per_page'] ?? 15);
    }

    /**
     * Get RSVP statistics for an event.
     */
    public function getRsvpStats(Event $event, int $tenantId): array
    {
        // Verify tenant isolation
        if ($event->tenant_id !== $tenantId) {
            throw new \Exception('Unauthorized: Event does not belong to tenant');
        }

        $attendees = $event->attendees;
        
        return [
            'total_invited' => $attendees->count(),
            'going' => $attendees->where('rsvp_status', 'going')->count(),
            'interested' => $attendees->where('rsvp_status', 'interested')->count(),
            'declined' => $attendees->where('rsvp_status', 'declined')->count(),
            'attended' => $attendees->where('attended', true)->count(),
        ];
    }

    /**
     * Generate short URL for event sharing.
     */
    public function generateShortUrl(string $url): string
    {
        // For now, return the original URL
        // In production, you could integrate with bit.ly, tinyurl, or create your own short URL service
        return $url;
    }

    /**
     * Generate QR code for event sharing.
     */
    public function generateQrCode(string $url): array
    {
        // Simple QR code generation using Google Charts API (free)
        $qrCodeUrl = "https://chart.googleapis.com/chart?chs=200x200&chld=M|0&cht=qr&chl=" . urlencode($url);
        
        return [
            'url' => $qrCodeUrl,
            'data_url' => $qrCodeUrl, // For direct image display
            'size' => '200x200',
        ];
    }

    /**
     * Generate frontend public URL for events.
     */
    public function generateFrontendPublicUrl(int $eventId): string
    {
        // Use FRONTEND_URL from .env, fallback to APP_URL if not set
        $frontendUrl = config('app.frontend_url') ?: config('app.url');
        
        // Log warning if FRONTEND_URL is not set
        if (!config('app.frontend_url')) {
            Log::warning('FRONTEND_URL not set in .env. Using APP_URL as fallback for QR code generation.', [
                'event_id' => $eventId,
                'fallback_url' => $frontendUrl
            ]);
        }
        
        // Ensure frontend URL doesn't end with slash
        $frontendUrl = rtrim($frontendUrl, '/');
        
        // Build frontend public event URL
        return "{$frontendUrl}/public/events/{$eventId}";
    }

    /**
     * Generate calendar event data for Google/Outlook/iCal.
     */
    public function generateCalendarEvent(Event $event): array
    {
        $startTime = $event->scheduled_at;
        $endTime = $startTime->copy()->addHours(1); // Default 1 hour duration
        
        // Extract duration from settings if available
        if (isset($event->settings['duration'])) {
            $endTime = $startTime->copy()->addMinutes($event->settings['duration']);
        }

        $title = $event->name;
        $description = $event->description ?? '';
        $location = $event->location ?? '';

        // Google Calendar URL
        $googleUrl = 'https://calendar.google.com/calendar/render?action=TEMPLATE&' . http_build_query([
            'text' => $title,
            'dates' => $startTime->format('Ymd\THis\Z') . '/' . $endTime->format('Ymd\THis\Z'),
            'details' => $description,
            'location' => $location,
        ]);

        // Outlook Calendar URL
        $outlookUrl = 'https://outlook.live.com/calendar/0/deeplink/compose?subject=' . urlencode($title) . 
                     '&startdt=' . $startTime->toISOString() . 
                     '&enddt=' . $endTime->toISOString() . 
                     '&body=' . urlencode($description) . 
                     '&location=' . urlencode($location);

        // iCal data
        $icalData = $this->generateIcalData($event, $startTime, $endTime);

        return [
            'google_url' => $googleUrl,
            'outlook_url' => $outlookUrl,
            'ical_data' => $icalData,
            'start_time' => $startTime->toISOString(),
            'end_time' => $endTime->toISOString(),
        ];
    }

    /**
     * Generate iCal format data.
     */
    private function generateIcalData(Event $event, $startTime, $endTime): string
    {
        $uid = 'event-' . $event->id . '@' . config('app.url');
        $now = now()->format('Ymd\THis\Z');
        
        $ical = "BEGIN:VCALENDAR\r\n";
        $ical .= "VERSION:2.0\r\n";
        $ical .= "PRODID:-//Your Company//Event Calendar//EN\r\n";
        $ical .= "BEGIN:VEVENT\r\n";
        $ical .= "UID:{$uid}\r\n";
        $ical .= "DTSTAMP:{$now}\r\n";
        $ical .= "DTSTART:{$startTime->format('Ymd\THis\Z')}\r\n";
        $ical .= "DTEND:{$endTime->format('Ymd\THis\Z')}\r\n";
        $ical .= "SUMMARY:" . $this->escapeIcalText($event->name) . "\r\n";
        
        if ($event->description) {
            $ical .= "DESCRIPTION:" . $this->escapeIcalText($event->description) . "\r\n";
        }
        
        if ($event->location) {
            $ical .= "LOCATION:" . $this->escapeIcalText($event->location) . "\r\n";
        }
        
        $ical .= "END:VEVENT\r\n";
        $ical .= "END:VCALENDAR\r\n";
        
        return $ical;
    }

    /**
     * Escape text for iCal format.
     */
    private function escapeIcalText(string $text): string
    {
        return str_replace(["\r\n", "\n", "\r", ',', ';'], ['\\n', '\\n', '\\n', '\\,', '\\;'], $text);
    }

    /**
     * Send event invitations to contacts using existing email templates.
     */
    public function sendInvitationsWithTemplate(Event $event, $contacts, ?int $templateId, string $subject, string $message, bool $sendEmail = true): array
    {
        $results = [
            'total_contacts' => $contacts->count(),
            'invitations_sent' => 0,
            'emails_sent' => 0,
            'errors' => [],
        ];

        // Get email template if provided
        $template = null;
        if ($templateId) {
            $template = \App\Models\CampaignTemplate::where('id', $templateId)
                ->where('tenant_id', $event->tenant_id)
                ->where('is_active', true)
                ->first();
        }

        foreach ($contacts as $contact) {
            try {
                // Add attendee to event
                $this->addAttendee($event, $contact->id, 'interested', [
                    'invitation_sent_at' => now()->toISOString(),
                    'invitation_method' => 'email',
                    'template_id' => $templateId,
                ], $event->tenant_id);

                $results['invitations_sent']++;

                // Send email if requested
                if ($sendEmail) {
                    $this->sendEventInvitationEmailWithTemplate($event, $contact, $template, $subject, $message);
                    $results['emails_sent']++;
                }

            } catch (\Exception $e) {
                $results['errors'][] = [
                    'contact_id' => $contact->id,
                    'contact_email' => $contact->email,
                    'error' => $e->getMessage(),
                ];
                
                Log::error('Failed to send event invitation', [
                    'event_id' => $event->id,
                    'contact_id' => $contact->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $results;
    }

    /**
     * Send event invitations to contacts (legacy method for backward compatibility).
     */
    public function sendInvitations(Event $event, $contacts, string $message = '', bool $sendEmail = true): array
    {
        return $this->sendInvitationsWithTemplate($event, $contacts, null, "You're invited to: {$event->name}", $message, $sendEmail);
    }

    /**
     * Send event invitation email using existing campaign templates.
     */
    private function sendEventInvitationEmailWithTemplate(Event $event, Contact $contact, $template, string $subject, string $message = ''): void
    {
        try {
            if ($template) {
                // Use provided template
                $this->sendEmailUsingTemplate($event, $contact, $template, $subject, $message);
            } else {
                // Use default template or simple email
                $this->sendSimpleEventInvitationEmail($event, $contact, $subject, $message);
            }
        } catch (\Exception $e) {
            Log::error('Failed to send event invitation email', [
                'event_id' => $event->id,
                'contact_email' => $contact->email,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Send email using existing campaign template system.
     */
    private function sendEmailUsingTemplate(Event $event, Contact $contact, $template, string $subject, string $message): void
    {
        // Replace template variables with event data
        $templateContent = $template->content;
        $templateSubject = $template->subject ?: $subject;
        
        // Event-specific variables
        $variables = [
            '{{contact_name}}' => $contact->name,
            '{{contact_first_name}}' => $contact->first_name,
            '{{contact_email}}' => $contact->email,
            '{{contact_id}}' => $contact->id,
            '{{event_name}}' => $event->name,
            '{{event_description}}' => $event->description ?? '',
            '{{event_date}}' => $event->scheduled_at->format('M d, Y'),
            '{{event_time}}' => $event->scheduled_at->format('g:i A'),
            '{{event_location}}' => $event->location ?? '',
            '{{event_url}}' => url("/api/public/events/{$event->id}"),
            '{{calendar_url}}' => url("/api/events/{$event->id}/calendar"),
            '{{rsvp_going_url}}' => url("/api/public/events/{$event->id}/rsvp?status=going&contact_id={$contact->id}"),
            '{{rsvp_maybe_url}}' => url("/api/public/events/{$event->id}/rsvp?status=interested&contact_id={$contact->id}"),
            '{{rsvp_declined_url}}' => url("/api/public/events/{$event->id}/rsvp?status=declined&contact_id={$contact->id}"),
            '{{custom_message}}' => $message,
        ];

        // Replace variables in content and subject
        $processedContent = str_replace(array_keys($variables), array_values($variables), $templateContent);
        $processedSubject = str_replace(array_keys($variables), array_values($variables), $templateSubject);
        
        // Append event details to the template content
        $eventDetailsHtml = $this->generateEventDetailsHtml($event, $message, $contact->id);
        $processedContent .= $eventDetailsHtml;

        // Log the email (in production, use your existing email service)
        Log::info('Event invitation email sent using template', [
            'event_id' => $event->id,
            'template_id' => $template->id,
            'template_name' => $template->name,
            'contact_email' => $contact->email,
            'subject' => $processedSubject,
            'content_length' => strlen($processedContent),
        ]);

        // Send email using Laravel Mail facade
        \Illuminate\Support\Facades\Mail::html($processedContent, function ($message) use ($contact, $processedSubject) {
            $message->to($contact->email, $contact->name)
                    ->subject($processedSubject)
                    ->from(config('mail.from.address'), config('mail.from.name'));
        });
    }

    /**
     * Send simple event invitation email without template.
     */
    private function sendSimpleEventInvitationEmail(Event $event, Contact $contact, string $subject, string $message): void
    {
        Log::info('Simple event invitation email sent', [
            'event_id' => $event->id,
            'event_name' => $event->name,
            'contact_email' => $contact->email,
            'contact_name' => $contact->name,
            'subject' => $subject,
            'message' => $message,
            'scheduled_at' => $event->scheduled_at->toISOString(),
        ]);

        // Generate simple email content
        $emailContent = $this->generateSimpleEventInvitationEmail($event, $contact, $subject, $message);
        
        // Send email using Laravel Mail facade
        \Illuminate\Support\Facades\Mail::html($emailContent, function ($message) use ($contact, $subject) {
            $message->to($contact->email, $contact->name)
                    ->subject($subject)
                    ->from(config('mail.from.address'), config('mail.from.name'));
        });
    }

    /**
     * Generate event details HTML to append to template emails.
     */
    private function generateEventDetailsHtml(Event $event, string $message = '', int $contactId = null): string
    {
        $eventDate = $event->scheduled_at->format('l, F j, Y');
        $eventTime = $event->scheduled_at->format('g:i A');
        
        return "
        <div style='margin-top: 30px; padding-top: 20px; border-top: 2px solid #e9ecef;'>
            <h3 style='color: #2c3e50; margin-bottom: 20px;'>Event Details</h3>
            
            <div style='background-color: #f8f9fa; padding: 20px; border-radius: 8px; margin: 20px 0;'>
                <h4 style='color: #2c3e50; margin-top: 0;'>{$event->name}</h4>
                <p><strong>📅 Date:</strong> {$eventDate}</p>
                <p><strong>🕐 Time:</strong> {$eventTime}</p>
                " . ($event->location ? "<p><strong>📍 Location:</strong> {$event->location}</p>" : "") . "
                " . ($event->description ? "<p><strong>📝 Description:</strong> {$event->description}</p>" : "") . "
            </div>
            
            " . ($message ? "<div style='background-color: #e3f2fd; padding: 15px; border-radius: 5px; margin: 15px 0;'><strong>💬 Personal Message:</strong><br>{$message}</div>" : "") . "
            
            <div style='text-align: center; margin: 30px 0;'>
                <h4 style='color: #2c3e50; margin-bottom: 20px;'>RSVP Response</h4>
                <a href='" . url("/api/public/events/{$event->id}/rsvp?status=going&contact_id={$contactId}") . "' 
                   style='background-color: #28a745; color: white; padding: 12px 24px; text-decoration: none; border-radius: 5px; display: inline-block; font-weight: bold; margin: 5px;'>
                    ✅ Yes, I'm Coming
                </a>
                <a href='" . url("/api/public/events/{$event->id}/rsvp?status=interested&contact_id={$contactId}") . "' 
                   style='background-color: #ffc107; color: #212529; padding: 12px 24px; text-decoration: none; border-radius: 5px; display: inline-block; font-weight: bold; margin: 5px;'>
                    🤔 Maybe
                </a>
                <a href='" . url("/api/public/events/{$event->id}/rsvp?status=declined&contact_id={$contactId}") . "' 
                   style='background-color: #dc3545; color: white; padding: 12px 24px; text-decoration: none; border-radius: 5px; display: inline-block; font-weight: bold; margin: 5px;'>
                    ❌ No, Can't Make It
                </a>
            </div>
            
            <div style='text-align: center; margin: 20px 0;'>
                <a href='" . url("/api/events/{$event->id}/calendar") . "' 
                   style='background-color: #28a745; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; display: inline-block; margin: 0 10px;'>
                    📅 Add to Calendar
                </a>
            </div>
        </div>";
    }

    /**
     * Generate simple event invitation email content.
     */
    private function generateSimpleEventInvitationEmail(Event $event, Contact $contact, string $subject, string $message): string
    {
        $eventDate = $event->scheduled_at->format('l, F j, Y');
        $eventTime = $event->scheduled_at->format('g:i A');
        
        return "
        <html>
        <body style='font-family: Arial, sans-serif; line-height: 1.6; color: #333;'>
            <div style='max-width: 600px; margin: 0 auto; padding: 20px;'>
                <h2 style='color: #2c3e50;'>You're Invited!</h2>
                
                <p>Dear {$contact->name},</p>
                
                <p>You're invited to join us for:</p>
                
                <div style='background-color: #f8f9fa; padding: 20px; border-radius: 8px; margin: 20px 0;'>
                    <h3 style='color: #2c3e50; margin-top: 0;'>{$event->name}</h3>
                    <p><strong>Date:</strong> {$eventDate}</p>
                    <p><strong>Time:</strong> {$eventTime}</p>
                    " . ($event->location ? "<p><strong>Location:</strong> {$event->location}</p>" : "") . "
                    " . ($event->description ? "<p><strong>Description:</strong> {$event->description}</p>" : "") . "
                </div>
                
                " . ($message ? "<p><strong>Personal Message:</strong><br>{$message}</p>" : "") . "
                
                <div style='text-align: center; margin: 30px 0;'>
                    <h4 style='color: #2c3e50; margin-bottom: 20px;'>RSVP Response</h4>
                    <a href='" . url("/api/public/events/{$event->id}/rsvp?status=going&contact_id={$contact->id}") . "' 
                       style='background-color: #28a745; color: white; padding: 12px 24px; text-decoration: none; border-radius: 5px; display: inline-block; font-weight: bold; margin: 5px;'>
                        ✅ Yes, I'm Coming
                    </a>
                    <a href='" . url("/api/public/events/{$event->id}/rsvp?status=interested&contact_id={$contact->id}") . "' 
                       style='background-color: #ffc107; color: #212529; padding: 12px 24px; text-decoration: none; border-radius: 5px; display: inline-block; font-weight: bold; margin: 5px;'>
                        🤔 Maybe
                    </a>
                    <a href='" . url("/api/public/events/{$event->id}/rsvp?status=declined&contact_id={$contact->id}") . "' 
                       style='background-color: #dc3545; color: white; padding: 12px 24px; text-decoration: none; border-radius: 5px; display: inline-block; font-weight: bold; margin: 5px;'>
                        ❌ No, Can't Make It
                    </a>
                </div>
                
                <p>We look forward to seeing you there!</p>
                
                <hr style='border: none; border-top: 1px solid #eee; margin: 30px 0;'>
                <p style='font-size: 12px; color: #666;'>
                    This invitation was sent by RC Convergio. If you have any questions, please contact us.
                </p>
            </div>
        </body>
        </html>";
    }

    /**
     * Send event invitation email (legacy method for backward compatibility).
     */
    private function sendEventInvitationEmail(Event $event, Contact $contact, string $message = ''): void
    {
        $this->sendEventInvitationEmailWithTemplate($event, $contact, null, "You're invited to: {$event->name}", $message);
    }

    /**
     * Get or create event invitation template.
     */
    private function getEventInvitationTemplate(Event $event)
    {
        // Try to find existing event invitation template
        $template = \App\Models\CampaignTemplate::where('name', 'Event Invitation Template')
            ->where('tenant_id', $event->tenant_id)
            ->first();

        if (!$template) {
            // Create default event invitation template
            $template = \App\Models\CampaignTemplate::create([
                'name' => 'Event Invitation Template',
                'subject' => 'You\'re invited to: {{event_name}}',
                'content' => $this->getDefaultEventTemplate(),
                'tenant_id' => $event->tenant_id,
                'created_by' => $event->created_by,
                'is_active' => true,
            ]);
        }

        return $template;
    }

    /**
     * Get default event invitation template content.
     */
    private function getDefaultEventTemplate(): string
    {
        return '
        <div style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;">
            <h2>You\'re Invited!</h2>
            <p>Hello {{contact_name}},</p>
            
            <div style="background: #f8f9fa; padding: 20px; border-radius: 8px; margin: 20px 0;">
                <h3>{{event_name}}</h3>
                <p><strong>Date & Time:</strong> {{event_date}}</p>
                <p><strong>Location:</strong> {{event_location}}</p>
                <p><strong>Type:</strong> {{event_type}}</p>
                <p><strong>Description:</strong> {{event_description}}</p>
                <p><strong>Personal Message:</strong> {{custom_message}}</p>
            </div>
            
            <div style="text-align: center; margin: 30px 0;">
                <h4 style="color: #2c3e50; margin-bottom: 20px;">RSVP Response</h4>
                <a href="{{rsvp_going_url}}" style="background: #28a745; color: white; padding: 12px 24px; text-decoration: none; border-radius: 5px; margin: 5px; font-weight: bold;">✅ Yes, I\'m Coming</a>
                <a href="{{rsvp_maybe_url}}" style="background: #ffc107; color: #212529; padding: 12px 24px; text-decoration: none; border-radius: 5px; margin: 5px; font-weight: bold;">🤔 Maybe</a>
                <a href="{{rsvp_declined_url}}" style="background: #dc3545; color: white; padding: 12px 24px; text-decoration: none; border-radius: 5px; margin: 5px; font-weight: bold;">❌ No, Can\'t Make It</a>
            </div>
            
            <div style="text-align: center; margin: 20px 0;">
                <a href="{{calendar_url}}" style="background: #6c757d; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; margin: 0 10px;">📅 Add to Calendar</a>
            </div>
            
            <p style="font-size: 12px; color: #666;">
                This invitation was sent to {{contact_email}}. 
                If you don\'t want to receive these emails, you can unsubscribe.
            </p>
        </div>';
    }

    /**
     * Send template email using existing campaign email service.
     */
    private function sendTemplateEmail(string $email, $template, array $data): void
    {
        // Use the same email service that campaigns use
        // This ensures consistency and reuses existing infrastructure
        
        $subject = $this->replaceTemplateVariables($template->subject, $data);
        $content = $this->replaceTemplateVariables($template->content, $data);
        
        // Send using Laravel Mail (same as campaigns)
        \Illuminate\Support\Facades\Mail::send([], [], function ($message) use ($email, $subject, $content) {
            $message->to($email)
                   ->subject($subject)
                   ->html($content);
        });
    }

    /**
     * Replace template variables with actual data.
     */
    private function replaceTemplateVariables(string $content, array $data): string
    {
        foreach ($data as $key => $value) {
            $content = str_replace('{{' . $key . '}}', $value, $content);
        }
        return $content;
    }

    /**
     * Get comprehensive events analytics overview for tenant.
     */
    public function getEventsAnalytics(int $tenantId): array
    {
        $events = Event::where('tenant_id', $tenantId)
            ->with(['attendees.contact'])
            ->get();

        $totalEvents = $events->count();
        $upcomingEvents = $events->where('scheduled_at', '>', now())->count();
        $pastEvents = $events->where('scheduled_at', '<', now())->count();
        $activeEvents = $events->where('is_active', true)->count();

        // RSVP Statistics
        $totalAttendees = $events->sum(function ($event) {
            return $event->attendees->count();
        });

        $totalGoing = $events->sum(function ($event) {
            return $event->attendees->where('rsvp_status', 'going')->count();
        });

        $totalInterested = $events->sum(function ($event) {
            return $event->attendees->where('rsvp_status', 'interested')->count();
        });

        $totalDeclined = $events->sum(function ($event) {
            return $event->attendees->where('rsvp_status', 'declined')->count();
        });

        $totalAttended = $events->sum(function ($event) {
            return $event->attendees->where('attended', true)->count();
        });

        // Event type distribution
        $eventTypes = $events->groupBy('type')->map(function ($typeEvents) {
            return $typeEvents->count();
        });

        // Monthly event trends
        $monthlyTrends = $events->groupBy(function ($event) {
            return $event->scheduled_at->format('Y-m');
        })->map(function ($monthEvents) {
            return $monthEvents->count();
        });

        // Top performing events by attendance
        $topEvents = $events->sortByDesc(function ($event) {
            return $event->attendees->where('attended', true)->count();
        })->take(5)->map(function ($event) {
            return [
                'id' => $event->id,
                'name' => $event->name,
                'type' => $event->type,
                'scheduled_at' => $event->scheduled_at->toISOString(),
                'total_attendees' => $event->attendees->count(),
                'attended' => $event->attendees->where('attended', true)->count(),
                'attendance_rate' => $event->attendees->count() > 0 
                    ? round(($event->attendees->where('attended', true)->count() / $event->attendees->count()) * 100, 2)
                    : 0,
            ];
        });

        return [
            'overview' => [
                'total_events' => $totalEvents,
                'upcoming_events' => $upcomingEvents,
                'past_events' => $pastEvents,
                'active_events' => $activeEvents,
            ],
            'rsvp_stats' => [
                'total_attendees' => $totalAttendees,
                'going' => $totalGoing,
                'interested' => $totalInterested,
                'declined' => $totalDeclined,
                'attended' => $totalAttended,
                'attendance_rate' => $totalGoing > 0 ? round(($totalAttended / $totalGoing) * 100, 2) : 0,
            ],
            'event_types' => $eventTypes,
            'monthly_trends' => $monthlyTrends,
            'top_events' => $topEvents,
            'generated_at' => now()->toISOString(),
        ];
    }

    /**
     * Get comprehensive event analytics.
     */
    public function getEventAnalytics(Event $event): array
    {
        $attendees = $event->attendees;
        $totalAttendees = $attendees->count();
        
        // RSVP Statistics
        $rsvpStats = [
            'total_invited' => $totalAttendees,
            'going' => $attendees->where('rsvp_status', 'going')->count(),
            'interested' => $attendees->where('rsvp_status', 'interested')->count(),
            'declined' => $attendees->where('rsvp_status', 'declined')->count(),
            'attended' => $attendees->where('attended', true)->count(),
        ];

        // Calculate percentages
        $rsvpStats['going_percentage'] = $totalAttendees > 0 ? round(($rsvpStats['going'] / $totalAttendees) * 100, 2) : 0;
        $rsvpStats['attendance_rate'] = $rsvpStats['going'] > 0 ? round(($rsvpStats['attended'] / $rsvpStats['going']) * 100, 2) : 0;

        // Registration trends (by day)
        $registrationTrends = $attendees->groupBy(function ($attendee) {
            return $attendee->created_at->format('Y-m-d');
        })->map(function ($dayAttendees) {
            return $dayAttendees->count();
        });

        // Source analysis
        $sourceAnalysis = $attendees->groupBy(function ($attendee) {
            return $attendee->metadata['source'] ?? 'unknown';
        })->map(function ($sourceAttendees) {
            return $sourceAttendees->count();
        });

        return [
            'event_id' => $event->id,
            'event_name' => $event->name,
            'event_type' => $event->type,
            'scheduled_at' => $event->scheduled_at->toISOString(),
            'rsvp_stats' => $rsvpStats,
            'registration_trends' => $registrationTrends,
            'source_analysis' => $sourceAnalysis,
            'top_attendees' => $attendees->take(10)->map(function ($attendee) {
                return [
                    'id' => $attendee->id,
                    'name' => $attendee->contact->name ?? 'Unknown',
                    'email' => $attendee->contact->email ?? 'Unknown',
                    'rsvp_status' => $attendee->rsvp_status,
                    'attended' => $attendee->attended,
                    'registered_at' => $attendee->created_at->toISOString(),
                ];
            }),
            'generated_at' => now()->toISOString(),
        ];
    }
}
