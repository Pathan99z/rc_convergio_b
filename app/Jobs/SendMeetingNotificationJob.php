<?php

namespace App\Jobs;

use App\Models\Meeting;
use App\Models\Contact;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class SendMeetingNotificationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $meetingId;
    public string $notificationType;
    public int $tries = 3;
    public int $timeout = 60;

    public function __construct(int $meetingId, string $notificationType = 'created')
    {
        $this->meetingId = $meetingId;
        $this->notificationType = $notificationType;
    }

    public function handle(): void
    {
        try {
            $meeting = Meeting::with(['contact', 'user'])->find($this->meetingId);
            
            if (!$meeting || !$meeting->contact || !$meeting->contact->email) {
                Log::warning('Meeting or contact not found for notification', [
                    'meeting_id' => $this->meetingId
                ]);
                return;
            }

            $emailData = $this->generateEmailContent($meeting);

            Mail::html($emailData['content'], function ($message) use ($meeting, $emailData) {
                $message->to($meeting->contact->email, $meeting->contact->first_name . ' ' . $meeting->contact->last_name)
                        ->subject($emailData['subject'])
                        ->from(config('mail.from.address'), config('mail.from.name'));
            });

            Log::info('Meeting notification sent successfully', [
                'meeting_id' => $this->meetingId,
                'contact_email' => $meeting->contact->email,
                'notification_type' => $this->notificationType
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to send meeting notification', [
                'meeting_id' => $this->meetingId,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    private function generateEmailContent(Meeting $meeting): array
    {
        $meetingLink = $meeting->getMeetingLink();
        $meetingTime = $meeting->scheduled_at->format('M j, Y \a\t g:i A');
        $meetingDuration = $meeting->duration_minutes . ' minutes';
        $userName = $meeting->user ? $meeting->user->name : 'Our Team';
        $contactName = $meeting->contact->first_name . ' ' . $meeting->contact->last_name;
        $companyName = config('app.name', 'Our Company');

        $joinButton = $meetingLink ? 
            '<div style="text-align: center; margin: 30px 0;">
                <a href="' . $meetingLink . '" style="background-color: #007bff; color: white; padding: 15px 30px; text-decoration: none; border-radius: 6px; display: inline-block; font-weight: bold; font-size: 16px;">Join Meeting</a>
            </div>' : 
            '<div style="text-align: center; margin: 30px 0; padding: 20px; background-color: #f8f9fa; border-radius: 6px;">
                <p style="color: #666; margin: 0; font-style: italic;">Meeting link will be provided separately</p>
            </div>';

        $subject = match($this->notificationType) {
            'created' => "Meeting Scheduled: {$meeting->title}",
            'updated' => "Meeting Updated: {$meeting->title}",
            'cancelled' => "Meeting Cancelled: {$meeting->title}",
            default => "Meeting Notification: {$meeting->title}"
        };

        $headerColor = match($this->notificationType) {
            'created' => '#007bff',
            'updated' => '#ffc107',
            'cancelled' => '#dc3545',
            default => '#007bff'
        };

        $headerText = match($this->notificationType) {
            'created' => 'Meeting Scheduled',
            'updated' => 'Meeting Updated',
            'cancelled' => 'Meeting Cancelled',
            default => 'Meeting Notification'
        };

        $content = '
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="utf-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>' . $headerText . '</title>
        </head>
        <body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333; max-width: 600px; margin: 0 auto; padding: 20px; background-color: #f8f9fa;">
            <div style="background-color: white; border-radius: 12px; padding: 0; box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1); overflow: hidden;">
                <div style="background: linear-gradient(135deg, ' . $headerColor . ' 0%, ' . $headerColor . 'dd 100%); padding: 30px; text-align: center;">
                    <h1 style="color: white; margin: 0; font-size: 28px; font-weight: 600;">' . $headerText . '</h1>
                    <p style="color: rgba(255, 255, 255, 0.9); margin: 10px 0 0 0; font-size: 16px;">' . $companyName . '</p>
                </div>
                
                <div style="padding: 40px 30px;">
                    <p style="font-size: 18px; margin: 0 0 20px 0;">Hello ' . htmlspecialchars($contactName) . ',</p>
                    
                    <p style="font-size: 16px; color: #666; margin: 0 0 30px 0;">' . 
                        match($this->notificationType) {
                            'created' => 'A meeting has been scheduled with you. Here are the details:',
                            'updated' => 'The following meeting has been updated with new details:',
                            'cancelled' => 'Unfortunately, the following meeting has been cancelled:',
                            default => 'Here are the meeting details:'
                        } . '
                    </p>
                    
                    <div style="background-color: #f8f9fa; border-radius: 8px; padding: 25px; margin: 30px 0; border-left: 4px solid ' . $headerColor . ';">
                        <h2 style="margin: 0 0 20px 0; color: #333; font-size: 24px;">' . htmlspecialchars($meeting->title) . '</h2>
                        
                        <div style="margin: 15px 0;">
                            <div style="display: flex; align-items: center; margin: 10px 0;">
                                <div style="width: 20px; height: 20px; background-color: ' . $headerColor . '; border-radius: 50%; margin-right: 15px; display: flex; align-items: center; justify-content: center;">
                                    <span style="color: white; font-size: 12px;">📅</span>
                                </div>
                                <div>
                                    <strong style="color: #333;">Date & Time:</strong><br>
                                    <span style="color: #666;">' . $meetingTime . '</span>
                                </div>
                            </div>
                            
                            <div style="display: flex; align-items: center; margin: 10px 0;">
                                <div style="width: 20px; height: 20px; background-color: #28a745; border-radius: 50%; margin-right: 15px; display: flex; align-items: center; justify-content: center;">
                                    <span style="color: white; font-size: 12px;">⏱️</span>
                                </div>
                                <div>
                                    <strong style="color: #333;">Duration:</strong><br>
                                    <span style="color: #666;">' . $meetingDuration . '</span>
                                </div>
                            </div>
                            
                            <div style="display: flex; align-items: center; margin: 10px 0;">
                                <div style="width: 20px; height: 20px; background-color: #6c757d; border-radius: 50%; margin-right: 15px; display: flex; align-items: center; justify-content: center;">
                                    <span style="color: white; font-size: 12px;">👤</span>
                                </div>
                                <div>
                                    <strong style="color: #333;">Organizer:</strong><br>
                                    <span style="color: #666;">' . htmlspecialchars($userName) . '</span>
                                </div>
                            </div>
                        </div>
                        
                        ' . ($meeting->description ? '<div style="margin-top: 20px; padding-top: 20px; border-top: 1px solid #dee2e6;"><strong style="color: #333;">Description:</strong><br><span style="color: #666; line-height: 1.5;">' . nl2br(htmlspecialchars($meeting->description)) . '</span></div>' : '') . '
                    </div>
                    
                    ' . $joinButton . '
                    
                    <div style="background-color: #e3f2fd; border-radius: 8px; padding: 20px; margin: 30px 0;">
                        <h3 style="margin: 0 0 10px 0; color: #1976d2; font-size: 18px;">📝 Meeting Purpose</h3>
                        <p style="margin: 0; color: #666; line-height: 1.5;">' . ($meeting->description ? nl2br(htmlspecialchars($meeting->description)) : 'Please prepare for our upcoming discussion.') . '</p>
                    </div>
                    
                    <p style="font-size: 16px; color: #666; margin: 30px 0 0 0;">If you have any questions, please contact ' . htmlspecialchars($userName) . '.</p>
                    
                    <p style="font-size: 16px; margin: 30px 0 0 0;">Best regards,<br><strong>' . htmlspecialchars($userName) . '</strong><br>' . $companyName . '</p>
                </div>
                
                <div style="background-color: #f8f9fa; padding: 20px 30px; border-top: 1px solid #dee2e6; text-align: center;">
                    <p style="font-size: 12px; color: #666; margin: 0;">
                        This is an automated message from ' . $companyName . '. Please do not reply to this email.
                    </p>
                </div>
            </div>
        </body>
        </html>';

        return [
            'subject' => $subject,
            'content' => $content
        ];
    }
}
