<?php

namespace App\Services\Service;

use App\Models\Service\Ticket;
use App\Models\Service\TicketMessage;
use App\Models\Service\Survey;
use App\Models\Contact;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;

class TicketMailerService
{
    /**
     * Send new ticket notification
     */
    public function sendNewTicketNotification(Ticket $ticket): void
    {
        try {
            $contact = $ticket->contact;
            $assignee = $ticket->assignee;
            $contactEmail = null;

            // Try to get email from contact record first
            if ($contact && $contact->email) {
                $contactEmail = $contact->email;
            } else {
                // Extract email from ticket description for public tickets
                $contactEmail = $this->extractEmailFromTicketDescription($ticket);
            }

            $data = [
                'ticket' => $ticket,
                'contact' => $contact,
                'assignee' => $assignee,
                'subject' => "New Support Ticket: {$ticket->subject}",
            ];

            // Send to customer if we have their email
            if ($contactEmail) {
                $this->sendMail($contactEmail, 'emails.service.tickets.new-ticket', $data);
            } else {
                Log::warning('Cannot send new ticket notification to customer - no contact email found', [
                    'ticket_id' => $ticket->id,
                ]);
            }

            // Send to assignee if exists
            if ($assignee && $assignee->email) {
                $this->sendMail($assignee->email, 'emails.service.tickets.assigned', $data);
            }

            Log::info('New ticket notifications sent', [
                'ticket_id' => $ticket->id,
                'contact_email' => $contactEmail,
                'assignee_email' => $assignee?->email,
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to send new ticket notification', [
                'ticket_id' => $ticket->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Send ticket reply notification
     */
    public function sendReplyNotification(TicketMessage $message): void
    {
        try {
            $ticket = $message->ticket;
            $contact = $ticket->contact;
            $contactEmail = null;

            // Try to get email from contact record first
            if ($contact && $contact->email) {
                $contactEmail = $contact->email;
            } else {
                // Extract email from ticket description for public tickets
                $contactEmail = $this->extractEmailFromTicketDescription($ticket);
            }

            if (!$contactEmail) {
                Log::warning('Cannot send reply notification - no contact email found', [
                    'ticket_id' => $ticket->id,
                    'message_id' => $message->id,
                ]);
                return;
            }

            // Only send notifications for public messages from agents
            if ($message->type !== 'public' || $message->direction !== 'outbound') {
                return;
            }

            $data = [
                'ticket' => $ticket,
                'ticketMessage' => $message,
                'contact' => $contact,
                'subject' => "Re: {$ticket->subject}",
            ];

            $this->sendMail($contactEmail, 'emails.service.tickets.reply', $data);

            Log::info('Reply notification sent', [
                'ticket_id' => $ticket->id,
                'message_id' => $message->id,
                'contact_email' => $contactEmail,
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to send reply notification', [
                'ticket_id' => $message->ticket_id,
                'message_id' => $message->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Send ticket closed notification
     */
    public function sendTicketClosedNotification(Ticket $ticket): void
    {
        try {
            $contact = $ticket->contact;
            $contactEmail = null;

            // Try to get email from contact record first
            if ($contact && $contact->email) {
                $contactEmail = $contact->email;
            } else {
                // Extract email from ticket description for public tickets
                $contactEmail = $this->extractEmailFromTicketDescription($ticket);
            }

            if (!$contactEmail) {
                Log::warning('Cannot send ticket closed notification - no contact email found', [
                    'ticket_id' => $ticket->id,
                ]);
                return;
            }

            $data = [
                'ticket' => $ticket,
                'contact' => $contact,
                'subject' => "Support Ticket Closed: {$ticket->subject}",
            ];

            $this->sendMail($contactEmail, 'emails.service.tickets.closed', $data);

            Log::info('Ticket closed notification sent', [
                'ticket_id' => $ticket->id,
                'contact_email' => $contactEmail,
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to send ticket closed notification', [
                'ticket_id' => $ticket->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Send SLA breach notification
     */
    public function sendSlaBreachNotification(Ticket $ticket): void
    {
        try {
            $assignee = $ticket->assignee;
            $team = $ticket->team;

            if (!$assignee && !$team) {
                Log::warning('Cannot send SLA breach notification - no assignee or team', [
                    'ticket_id' => $ticket->id,
                ]);
                return;
            }

            $data = [
                'ticket' => $ticket,
                'assignee' => $assignee,
                'team' => $team,
                'subject' => "SLA Breach Alert: {$ticket->subject}",
            ];

            // Send to assignee
            if ($assignee && $assignee->email) {
                $this->sendMail($assignee->email, 'service.tickets.sla-breach', $data);
            }

            // Send to team members if no assignee
            if (!$assignee && $team) {
                $teamMembers = $team->users;
                foreach ($teamMembers as $member) {
                    if ($member->email) {
                        $this->sendMail($member->email, 'service.tickets.sla-breach', $data);
                    }
                }
            }

            Log::info('SLA breach notification sent', [
                'ticket_id' => $ticket->id,
                'assignee_email' => $assignee?->email,
                'team_id' => $team?->id,
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to send SLA breach notification', [
                'ticket_id' => $ticket->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Send email using Laravel Mail
     */
    private function sendMail(string $email, string $template, array $data): void
    {
        try {
            // Check if queue is available
            if (config('queue.default') !== 'sync') {
                Mail::queue($template, $data, function ($message) use ($email, $data) {
                    $message->to($email)
                           ->subject($data['subject']);
                });
            } else {
                // Fallback to sync sending
                Mail::send($template, $data, function ($message) use ($email, $data) {
                    $message->to($email)
                           ->subject($data['subject']);
                });
            }
        } catch (\Exception $e) {
            Log::error('Failed to send email', [
                'email' => $email,
                'template' => $template,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Extract email address from ticket description for public tickets
     */
    private function extractEmailFromTicketDescription(Ticket $ticket): ?string
    {
        $description = $ticket->description;
        
        // Look for email pattern in the description
        // Common patterns: "Customer: Name (email@domain.com)" or "email@domain.com"
        $emailPatterns = [
            '/Customer:\s*[^(]*\(([^)]+@[^)]+)\)/',  // Customer: Name (email@domain.com)
            '/([a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,})/',  // Standard email pattern
        ];
        
        foreach ($emailPatterns as $pattern) {
            if (preg_match($pattern, $description, $matches)) {
                $email = trim($matches[1]);
                // Validate the extracted email
                if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    return $email;
                }
            }
        }
        
        return null;
    }

    /**
     * Send post-ticket survey
     */
    public function sendPostTicketSurvey(Ticket $ticket, Survey $survey, string $contactEmail): void
    {
        try {
            $data = [
                'ticket' => $ticket,
                'survey' => $survey,
                'surveyUrl' => config('app.url') . "/survey/{$survey->id}?ticket={$ticket->id}",
                'subject' => "How was your support experience?",
            ];

            $this->sendMail($contactEmail, 'emails.service.tickets.post-survey', $data);

            Log::info('Post-ticket survey sent', [
                'ticket_id' => $ticket->id,
                'survey_id' => $survey->id,
                'contact_email' => $contactEmail,
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to send post-ticket survey', [
                'ticket_id' => $ticket->id,
                'survey_id' => $survey->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Send bulk notifications for multiple tickets
     */
    public function sendBulkNotifications(array $ticketIds, string $type): void
    {
        $tickets = Ticket::whereIn('id', $ticketIds)->with(['contact', 'assignee', 'team'])->get();

        foreach ($tickets as $ticket) {
            match ($type) {
                'new' => $this->sendNewTicketNotification($ticket),
                'closed' => $this->sendTicketClosedNotification($ticket),
                'sla_breach' => $this->sendSlaBreachNotification($ticket),
                default => Log::warning('Unknown bulk notification type', ['type' => $type]),
            };
        }
    }

    /**
     * Test email configuration
     */
    public function testEmailConfiguration(string $email): bool
    {
        try {
            $data = [
                'subject' => 'Test Email - RC Convergio Service Platform',
                'message' => 'This is a test email to verify email configuration.',
            ];

            Mail::send('service.tickets.test', $data, function ($message) use ($email) {
                $message->to($email)
                       ->subject('Test Email - RC Convergio Service Platform');
            });

            Log::info('Test email sent successfully', ['email' => $email]);
            return true;

        } catch (\Exception $e) {
            Log::error('Failed to send test email', [
                'email' => $email,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }
}
