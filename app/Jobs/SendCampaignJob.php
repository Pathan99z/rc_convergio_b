<?php

namespace App\Jobs;

use App\Models\Campaign;
use App\Models\CampaignRecipient;
use App\Models\Contact;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class SendCampaignJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 300; // 5 minutes
    public $tries = 3;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public Campaign $campaign
    ) {}

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        try {
            // Check if campaign is still in sending state
            if ($this->campaign->status !== 'sending' && $this->campaign->status !== 'scheduled') {
                Log::info('Campaign is not in sending state', [
                    'campaign_id' => $this->campaign->id,
                    'status' => $this->campaign->status,
                ]);
                return;
            }

            // Get contacts for this tenant
            $contacts = Contact::where('tenant_id', $this->campaign->tenant_id)
                ->whereNotNull('email')
                ->where('email', '!=', '')
                ->get();

            $totalRecipients = $contacts->count();
            $this->campaign->update(['total_recipients' => $totalRecipients]);

            // Create recipients
            foreach ($contacts as $contact) {
                CampaignRecipient::create([
                    'campaign_id' => $this->campaign->id,
                    'contact_id' => $contact->id,
                    'email' => $contact->email,
                    'name' => $contact->first_name . ' ' . $contact->last_name,
                    'status' => 'pending',
                    'tenant_id' => $this->campaign->tenant_id,
                ]);
            }

            // Send emails to recipients
            $recipients = $this->campaign->recipients()->where('status', 'pending')->get();
            
            foreach ($recipients as $recipient) {
                try {
                    // Send email (implement with your email provider)
                    $this->sendEmail($recipient);
                    
                    $recipient->update([
                        'status' => 'sent',
                        'sent_at' => now(),
                    ]);

                    // Update campaign sent count
                    $this->campaign->increment('sent_count');

                } catch (\Exception $e) {
                    Log::error('Failed to send email to recipient', [
                        'recipient_id' => $recipient->id,
                        'email' => $recipient->email,
                        'error' => $e->getMessage(),
                    ]);

                    $recipient->update([
                        'status' => 'failed',
                        'metadata' => array_merge($recipient->metadata ?? [], [
                            'error' => $e->getMessage(),
                        ]),
                    ]);
                }
            }

            // Update campaign status
            $this->campaign->update([
                'status' => 'sent',
                'sent_at' => now(),
            ]);

            Log::info('Campaign sent successfully', [
                'campaign_id' => $this->campaign->id,
                'total_recipients' => $totalRecipients,
                'sent_count' => $this->campaign->sent_count,
            ]);

        } catch (\Exception $e) {
            Log::error('Campaign sending failed', [
                'campaign_id' => $this->campaign->id,
                'error' => $e->getMessage(),
            ]);

            $this->campaign->update(['status' => 'failed']);
            throw $e;
        }
    }

    private function sendEmail(CampaignRecipient $recipient): void
    {
        try {
            // Send email using Laravel Mail
            Mail::raw($this->campaign->content, function ($message) use ($recipient) {
                $message->to($recipient->email, $recipient->name)
                        ->subject($this->campaign->subject)
                        ->from(config('mail.from.address'), config('mail.from.name'));
            });

            Log::info('Email sent successfully', [
                'recipient_id' => $recipient->id,
                'email' => $recipient->email,
                'campaign_id' => $this->campaign->id,
                'subject' => $this->campaign->subject,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to send email', [
                'recipient_id' => $recipient->id,
                'email' => $recipient->email,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('Campaign sending job failed', [
            'campaign_id' => $this->campaign->id,
            'error' => $exception->getMessage(),
        ]);

        $this->campaign->update(['status' => 'failed']);
    }
}
