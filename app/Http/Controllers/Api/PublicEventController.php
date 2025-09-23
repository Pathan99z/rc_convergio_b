<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\EventAttendee;
use App\Models\Contact;
use App\Models\Company;
use App\Services\EventService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class PublicEventController extends Controller
{
    public function __construct(
        private EventService $eventService
    ) {}

    /**
     * Register for a public event.
     */
    public function register(Request $request, $eventId): JsonResponse
    {
        $request->validate([
            'email' => 'required|email|max:255',
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'phone' => 'nullable|string|max:20',
            'company' => 'nullable|string|max:255',
            'source' => 'nullable|string|max:255',
            'utm_campaign' => 'nullable|string|max:255',
            'agreed_to_communications' => 'required|boolean|accepted',
        ]);

        try {
            DB::beginTransaction();

            // Find the event (must be active and public)
            $event = Event::where('id', $eventId)
                ->where('is_active', true)
                ->firstOrFail();

            // Check if event is public (you can add a 'is_public' field to events table)
            // For now, we'll assume all events are public

            // Create or find contact
            $contact = $this->findOrCreateContact($request->all(), $event->tenant_id, $event->created_by);

            // Create or find company if provided
            $company = null;
            if ($request->filled('company')) {
                $company = $this->findOrCreateCompany($request->all(), $event->tenant_id, $event->created_by);
                
                // Link contact to company if not already linked
                if ($contact->company_id !== $company->id) {
                    $contact->update(['company_id' => $company->id]);
                }
            }

            // Check if already registered
            $existingAttendee = EventAttendee::where('event_id', $eventId)
                ->where('contact_id', $contact->id)
                ->where('tenant_id', $event->tenant_id)
                ->first();

            if ($existingAttendee) {
                // Update existing registration
                $existingAttendee->updateRsvpStatus('going');
                $attendee = $existingAttendee;
                
                Log::info('Public event registration updated', [
                    'event_id' => $eventId,
                    'contact_id' => $contact->id,
                    'attendee_id' => $attendee->id
                ]);
            } else {
                // Create new registration
                $attendee = $this->eventService->addAttendee(
                    $event,
                    $contact->id,
                    'going',
                    [
                        'source' => 'public_registration',
                        'utm_campaign' => $request->input('utm_campaign'),
                        'registration_ip' => $request->ip(),
                        'registration_user_agent' => $request->userAgent(),
                        'registration_date' => now()->toISOString(),
                    ],
                    $event->tenant_id
                );
                
                Log::info('Public event registration created', [
                    'event_id' => $eventId,
                    'contact_id' => $contact->id,
                    'attendee_id' => $attendee->id
                ]);
            }

            DB::commit();

            // Send automatic email confirmation to registrant
            $this->sendEventConfirmationEmail($event, $contact, $attendee);

            return response()->json([
                'success' => true,
                'message' => 'Successfully registered for the event',
                'data' => [
                    'event' => [
                        'id' => $event->id,
                        'name' => $event->name,
                        'scheduled_at' => $event->scheduled_at->toISOString(),
                        'location' => $event->location,
                    ],
                    'attendee' => [
                        'id' => $attendee->id,
                        'rsvp_status' => $attendee->rsvp_status,
                        'contact' => [
                            'id' => $contact->id,
                            'name' => $contact->name,
                            'email' => $contact->email,
                        ],
                    ],
                ]
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            
            Log::error('Public event registration failed', [
                'event_id' => $eventId,
                'email' => $request->input('email'),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Registration failed. Please try again.',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Get public event details.
     */
    public function show($eventId): JsonResponse
    {
        try {
            $event = Event::where('id', $eventId)
                ->where('is_active', true)
                ->with('attendees')
                ->firstOrFail();

            // Return limited public information
            return response()->json([
                'data' => [
                    'id' => $event->id,
                    'name' => $event->name,
                    'description' => $event->description,
                    'type' => $event->type,
                    'scheduled_at' => $event->scheduled_at->toISOString(),
                    'location' => $event->location,
                    'settings' => [
                        'max_attendees' => $event->settings['max_attendees'] ?? null,
                        'recording_enabled' => $event->settings['recording_enabled'] ?? false,
                    ],
                    'rsvp_stats' => [
                        'total_invited' => $event->attendees->count(),
                        'going' => $event->attendees->where('rsvp_status', 'going')->count(),
                    ],
                ],
                'message' => 'Event details retrieved successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Event not found or not available',
                'error' => 'Event not found'
            ], 404);
        }
    }

    /**
     * Find or create contact.
     */
    private function findOrCreateContact(array $data, int $tenantId, int $eventCreatorId): Contact
    {
        $email = $data['email'];
        
        // Try to find existing contact
        $contact = Contact::where('email', $email)
            ->where('tenant_id', $tenantId)
            ->first();

        if (!$contact) {
            // Create new contact with event creator as owner
            $contact = Contact::create([
                'first_name' => $data['first_name'],
                'last_name' => $data['last_name'],
                'email' => $email,
                'phone' => $data['phone'] ?? null,
                'tenant_id' => $tenantId,
                'owner_id' => $eventCreatorId, // Assign to event creator
                'source' => 'public_event_registration',
            ]);
        } else {
            // Update existing contact if needed
            $updateData = [];
            if (empty($contact->first_name)) $updateData['first_name'] = $data['first_name'];
            if (empty($contact->last_name)) $updateData['last_name'] = $data['last_name'];
            if (empty($contact->phone) && !empty($data['phone'])) $updateData['phone'] = $data['phone'];
            
            if (!empty($updateData)) {
                $contact->update($updateData);
            }
        }

        return $contact;
    }

    /**
     * Find or create company.
     */
    private function findOrCreateCompany(array $data, int $tenantId, int $eventCreatorId): ?Company
    {
        $companyName = $data['company'];
        
        if (empty($companyName)) {
            return null;
        }

        // Try to find existing company
        $company = Company::where('name', $companyName)
            ->where('tenant_id', $tenantId)
            ->first();

        if (!$company) {
            // Create new company with event creator as owner
            $company = Company::create([
                'name' => $companyName,
                'tenant_id' => $tenantId,
                'owner_id' => $eventCreatorId, // Assign to event creator
            ]);
        }

        return $company;
    }

    /**
     * Show simple RSVP page.
     */
    public function showRsvpPage(Request $request, $eventId)
    {
        $request->validate([
            'contact_id' => 'required|integer|exists:contacts,id',
        ]);

        try {
            // Find the event (must be active)
            $event = Event::where('id', $eventId)
                ->where('is_active', true)
                ->firstOrFail();

            // Find the contact
            $contact = Contact::where('id', $request->input('contact_id'))
                ->where('tenant_id', $event->tenant_id)
                ->firstOrFail();

            return view('rsvp', [
                'event' => $event,
                'contactId' => $contact->id
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to show RSVP page', [
                'event_id' => $eventId,
                'contact_id' => $request->input('contact_id'),
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Event not found or not available',
                'error' => 'Event not found'
            ], 404);
        }
    }

    /**
     * Simple RSVP endpoint - one-click response from email buttons.
     */
    public function rsvp(Request $request, $eventId)
    {
        $request->validate([
            'status' => 'required|in:going,interested,declined',
            'contact_id' => 'required|integer|exists:contacts,id',
        ]);

        try {
            DB::beginTransaction();

            // Find the event (must be active)
            $event = Event::where('id', $eventId)
                ->where('is_active', true)
                ->firstOrFail();

            // Find the contact
            $contact = Contact::where('id', $request->input('contact_id'))
                ->where('tenant_id', $event->tenant_id)
                ->firstOrFail();

            $rsvpStatus = $request->input('status');

            // Check if attendee already exists
            $existingAttendee = EventAttendee::where('event_id', $eventId)
                ->where('contact_id', $contact->id)
                ->where('tenant_id', $event->tenant_id)
                ->first();

            if ($existingAttendee) {
                // Update existing RSVP
                $existingAttendee->updateRsvpStatus($rsvpStatus);
                $attendee = $existingAttendee;
            } else {
                // Create new attendee
                $attendee = EventAttendee::create([
                    'event_id' => $eventId,
                    'contact_id' => $contact->id,
                    'rsvp_status' => $rsvpStatus,
                    'rsvp_at' => now(),
                    'metadata' => [
                        'source' => 'email_rsvp',
                        'rsvp_ip' => $request->ip(),
                        'rsvp_user_agent' => $request->userAgent(),
                        'rsvp_date' => now()->toISOString(),
                    ],
                    'tenant_id' => $event->tenant_id,
                ]);
            }

            DB::commit();

            // Send confirmation email
            $this->sendRsvpConfirmationEmail($event, $contact, $attendee, $rsvpStatus);

            // If this is a GET request (from email link), redirect to success page
            if ($request->isMethod('get')) {
                $statusMessages = [
                    'going' => 'Great! We\'re excited to see you at the event.',
                    'interested' => 'Thanks for your interest! We\'ll keep you updated.',
                    'declined' => 'We\'re sorry you can\'t make it. We hope to see you at future events.'
                ];

                return redirect()->back()->with('success', $statusMessages[$rsvpStatus] ?? 'Thank you for your RSVP response.');
            }

            // For API calls, return JSON
            return response()->json([
                'success' => true,
                'message' => 'RSVP response recorded successfully',
                'data' => [
                    'event' => [
                        'id' => $event->id,
                        'name' => $event->name,
                        'scheduled_at' => $event->scheduled_at->toISOString(),
                    ],
                    'contact' => [
                        'id' => $contact->id,
                        'name' => $contact->name,
                        'email' => $contact->email,
                    ],
                    'rsvp_status' => $rsvpStatus,
                    'rsvp_at' => $attendee->rsvp_at->toISOString(),
                ]
            ], 200);

        } catch (\Exception $e) {
            DB::rollBack();
            
            Log::error('Simple RSVP failed', [
                'event_id' => $eventId,
                'contact_id' => $request->input('contact_id'),
                'rsvp_status' => $request->input('status'),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            // If this is a GET request, redirect with error
            if ($request->isMethod('get')) {
                return redirect()->back()->with('error', 'RSVP failed. Please try again.');
            }

            return response()->json([
                'success' => false,
                'message' => 'RSVP failed. Please try again.',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Send RSVP confirmation email.
     */
    private function sendRsvpConfirmationEmail($event, $contact, $attendee, $rsvpStatus): void
    {
        try {
            $statusMessages = [
                'going' => 'Great! We\'re excited to see you at the event.',
                'interested' => 'Thanks for your interest! We\'ll keep you updated.',
                'declined' => 'We\'re sorry you can\'t make it. We hope to see you at future events.'
            ];

            $statusEmojis = [
                'going' => '🎉',
                'interested' => '🤔',
                'declined' => '😔'
            ];

            $subject = "RSVP Confirmed: {$event->name}";
            $message = $statusMessages[$rsvpStatus] ?? 'Thank you for your RSVP response.';
            $emoji = $statusEmojis[$rsvpStatus] ?? '✅';
            
            // Format event date and time
            $eventDate = $event->scheduled_at->format('l, F j, Y');
            $eventTime = $event->scheduled_at->format('g:i A');
            
            // Create professional RSVP confirmation email
            $emailContent = $this->generateRsvpConfirmationEmail($event, $contact, $eventDate, $eventTime, $rsvpStatus, $message, $emoji);
            
            // Send email using Laravel's mail system
            Mail::html($emailContent, function ($message) use ($contact, $subject) {
                $message->to($contact->email, $contact->name)
                        ->subject($subject)
                        ->from(config('mail.from.address'), config('mail.from.name'));
            });
            
            Log::info('RSVP confirmation email sent successfully', [
                'event_id' => $event->id,
                'contact_email' => $contact->email,
                'rsvp_status' => $rsvpStatus,
                'attendee_id' => $attendee->id
            ]);
            
        } catch (\Exception $e) {
            Log::error('Error sending RSVP confirmation email', [
                'event_id' => $event->id,
                'contact_email' => $contact->email,
                'rsvp_status' => $rsvpStatus,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Send automatic event confirmation email to registrant.
     */
    private function sendEventConfirmationEmail($event, $contact, $attendee): void
    {
        try {
            $subject = "Event Registration Confirmation: {$event->name}";
            
            // Format event date and time
            $eventDate = $event->scheduled_at->format('l, F j, Y');
            $eventTime = $event->scheduled_at->format('g:i A');
            
            // Create professional email content
            $emailContent = $this->generateEventConfirmationEmail($event, $contact, $eventDate, $eventTime);
            
            // Send email using Laravel's mail system with SMTP
            Mail::html($emailContent, function ($message) use ($contact, $subject) {
                $message->to($contact->email, $contact->name)
                        ->subject($subject)
                        ->from(config('mail.from.address'), config('mail.from.name'));
            });
            
            Log::info('Event confirmation email sent successfully', [
                'event_id' => $event->id,
                'contact_email' => $contact->email,
                'attendee_id' => $attendee->id
            ]);
            
        } catch (\Exception $e) {
            Log::error('Error sending event confirmation email', [
                'event_id' => $event->id,
                'contact_email' => $contact->email,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Generate professional RSVP confirmation email content.
     */
    private function generateRsvpConfirmationEmail($event, $contact, $eventDate, $eventTime, $rsvpStatus, $message, $emoji): string
    {
        $statusLabels = [
            'going' => 'Yes, I\'m Coming',
            'interested' => 'Maybe',
            'declined' => 'No, Can\'t Make It'
        ];

        $statusLabel = $statusLabels[$rsvpStatus] ?? ucfirst($rsvpStatus);
        $zoomLink = $event->location ?: 'Zoom link will be provided closer to the event';
        $eventDescription = $event->description ?: 'Join us for this exciting event!';
        
        return "
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset='UTF-8'>
            <meta name='viewport' content='width=device-width, initial-scale=1.0'>
            <title>RSVP Confirmation</title>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background: #4F46E5; color: white; padding: 20px; text-align: center; border-radius: 8px 8px 0 0; }
                .content { background: #f9f9f9; padding: 30px; border-radius: 0 0 8px 8px; }
                .rsvp-confirmation { background: #e8f5e8; padding: 20px; border-radius: 8px; margin: 20px 0; text-align: center; border-left: 4px solid #28a745; }
                .event-details { background: white; padding: 20px; border-radius: 8px; margin: 20px 0; border-left: 4px solid #4F46E5; }
                .zoom-section { background: #e0f2fe; padding: 20px; border-radius: 8px; margin: 20px 0; text-align: center; }
                .zoom-button { background: #4F46E5; color: white; padding: 12px 24px; text-decoration: none; border-radius: 6px; display: inline-block; margin: 10px 0; }
                .footer { text-align: center; margin-top: 30px; color: #666; font-size: 14px; }
                .highlight { color: #4F46E5; font-weight: bold; }
                .status-badge { background: #28a745; color: white; padding: 8px 16px; border-radius: 20px; font-weight: bold; display: inline-block; }
            </style>
        </head>
        <body>
            <div class='header'>
                <h1>{$emoji} RSVP Confirmed!</h1>
                <p>Your response has been recorded</p>
            </div>
            
            <div class='content'>
                <p>Hi <strong>{$contact->name}</strong>,</p>
                
                <div class='rsvp-confirmation'>
                    <h2>Your RSVP Response</h2>
                    <div class='status-badge'>{$statusLabel}</div>
                    <p style='margin-top: 15px;'><strong>{$message}</strong></p>
                </div>
                
                <div class='event-details'>
                    <h2>📅 Event Details</h2>
                    <p><strong>Event:</strong> <span class='highlight'>{$event->name}</span></p>
                    <p><strong>Date:</strong> {$eventDate}</p>
                    <p><strong>Time:</strong> {$eventTime}</p>
                    <p><strong>Type:</strong> " . ucfirst($event->type) . "</p>
                    <p><strong>Description:</strong> {$eventDescription}</p>
                </div>
                
                " . ($rsvpStatus === 'going' ? "
                <div class='zoom-section'>
                    <h3>🔗 Join the Event</h3>
                    <p>Click the button below to join the event when it starts:</p>
                    <a href='{$zoomLink}' class='zoom-button'>Join Zoom Meeting</a>
                    <p><small>Or copy this link: {$zoomLink}</small></p>
                </div>
                
                <div style='background: white; padding: 20px; border-radius: 8px; margin: 20px 0;'>
                    <h3>📋 What's Next?</h3>
                    <ul>
                        <li>Add this event to your calendar</li>
                        <li>Test your audio and video before the event</li>
                        <li>Join 5 minutes early to ensure everything works</li>
                        <li>Check your email for any updates</li>
                    </ul>
                </div>
                " : "") . "
                
                <p>If you have any questions or need to change your RSVP, feel free to reach out to us.</p>
                
                <p>Best regards,<br>
                <strong>RC Convergio Team</strong></p>
            </div>
            
            <div class='footer'>
                <p>This is an automated confirmation email. Please do not reply to this email.</p>
                <p>© " . date('Y') . " RC Convergio. All rights reserved.</p>
            </div>
        </body>
        </html>
        ";
    }

    /**
     * Generate professional event confirmation email content.
     */
    private function generateEventConfirmationEmail($event, $contact, $eventDate, $eventTime): string
    {
        $zoomLink = $event->location ?: 'Zoom link will be provided closer to the event';
        $eventDescription = $event->description ?: 'Join us for this exciting event!';
        
        return "
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset='UTF-8'>
            <meta name='viewport' content='width=device-width, initial-scale=1.0'>
            <title>Event Registration Confirmation</title>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background: #4F46E5; color: white; padding: 20px; text-align: center; border-radius: 8px 8px 0 0; }
                .content { background: #f9f9f9; padding: 30px; border-radius: 0 0 8px 8px; }
                .event-details { background: white; padding: 20px; border-radius: 8px; margin: 20px 0; border-left: 4px solid #4F46E5; }
                .zoom-section { background: #e0f2fe; padding: 20px; border-radius: 8px; margin: 20px 0; text-align: center; }
                .zoom-button { background: #4F46E5; color: white; padding: 12px 24px; text-decoration: none; border-radius: 6px; display: inline-block; margin: 10px 0; }
                .footer { text-align: center; margin-top: 30px; color: #666; font-size: 14px; }
                .highlight { color: #4F46E5; font-weight: bold; }
            </style>
        </head>
        <body>
            <div class='header'>
                <h1>🎉 Registration Confirmed!</h1>
                <p>You're all set for the event</p>
            </div>
            
            <div class='content'>
                <p>Hi <strong>{$contact->name}</strong>,</p>
                
                <p>Thank you for registering! We're excited to have you join us.</p>
                
                <div class='event-details'>
                    <h2>📅 Event Details</h2>
                    <p><strong>Event:</strong> <span class='highlight'>{$event->name}</span></p>
                    <p><strong>Date:</strong> {$eventDate}</p>
                    <p><strong>Time:</strong> {$eventTime}</p>
                    <p><strong>Type:</strong> " . ucfirst($event->type) . "</p>
                    <p><strong>Description:</strong> {$eventDescription}</p>
                </div>
                
                <div class='zoom-section'>
                    <h3>🔗 Join the Event</h3>
                    <p>Click the button below to join the event when it starts:</p>
                    <a href='{$zoomLink}' class='zoom-button'>Join Zoom Meeting</a>
                    <p><small>Or copy this link: {$zoomLink}</small></p>
                </div>
                
                <div style='background: white; padding: 20px; border-radius: 8px; margin: 20px 0;'>
                    <h3>📋 What's Next?</h3>
                    <ul>
                        <li>Add this event to your calendar</li>
                        <li>Test your audio and video before the event</li>
                        <li>Join 5 minutes early to ensure everything works</li>
                        <li>Check your email for any updates</li>
                    </ul>
                </div>
                
                <p>If you have any questions, feel free to reach out to us.</p>
                
                <p>We look forward to seeing you at the event!</p>
                
                <p>Best regards,<br>
                <strong>RC Convergio Team</strong></p>
            </div>
            
            <div class='footer'>
                <p>This is an automated confirmation email. Please do not reply to this email.</p>
                <p>© " . date('Y') . " RC Convergio. All rights reserved.</p>
            </div>
        </body>
        </html>
        ";
    }
}
