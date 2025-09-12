<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CampaignRecipient;
use App\Models\Contact;
use App\Models\ContactSubscription;
use App\Models\AuditLog;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

class UnsubscribeController extends Controller
{
    /**
     * Handle unsubscribe request from email link
     * Public route - no authentication required
     */
    public function unsubscribe(Request $request, int $recipientId): Response
    {
        try {
            // Find the recipient
            $recipient = CampaignRecipient::findOrFail($recipientId);
            
            // Find the contact by email (in case contact_id is null)
            $contact = Contact::where('email', $recipient->email)
                ->where('tenant_id', $recipient->tenant_id)
                ->first();
            
            if (!$contact) {
                Log::warning('Unsubscribe attempt for unknown contact', [
                    'recipient_id' => $recipientId,
                    'email' => $recipient->email,
                    'ip' => $request->ip()
                ]);
                
                return $this->unsubscribeResponse(false, 'Contact not found');
            }
            
            // Check if already unsubscribed
            if (\App\Models\ContactSubscription::isUnsubscribed($contact->id)) {
                $subscription = \App\Models\ContactSubscription::where('contact_id', $contact->id)->first();
                Log::info('Unsubscribe attempt for already unsubscribed contact', [
                    'contact_id' => $contact->id,
                    'email' => $contact->email,
                    'unsubscribed_at' => $subscription?->unsubscribed_at
                ]);
                
                return $this->unsubscribeResponse(true, 'You are already unsubscribed');
            }
            
            // Mark contact as unsubscribed using ContactSubscription model
            \App\Models\ContactSubscription::unsubscribe($contact->id, [
                'unsubscribed_via' => 'email_link',
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'metadata' => [
                    'campaign_id' => $recipient->campaign_id,
                    'campaign_name' => $recipient->campaign->name ?? 'Unknown'
                ]
            ]);
            
            // Log the unsubscribe event
            AuditLog::log('recipient_unsubscribed', [
                'contact_id' => $contact->id,
                'recipient_id' => $recipientId,
                'campaign_id' => $recipient->campaign_id,
                'metadata' => [
                    'email' => $contact->email,
                    'campaign_name' => $recipient->campaign->name ?? 'Unknown',
                    'unsubscribed_via' => 'email_link',
                    'ip_address' => $request->ip(),
                    'user_agent' => $request->userAgent(),
                ]
            ]);
            
            Log::info('Contact unsubscribed successfully', [
                'contact_id' => $contact->id,
                'email' => $contact->email,
                'campaign_id' => $recipient->campaign_id,
                'ip' => $request->ip()
            ]);
            
            return $this->unsubscribeResponse(true, 'You have been successfully unsubscribed');
            
        } catch (\Exception $e) {
            Log::error('Unsubscribe failed', [
                'recipient_id' => $recipientId,
                'error' => $e->getMessage(),
                'ip' => $request->ip()
            ]);
            
            return $this->unsubscribeResponse(false, 'An error occurred while processing your request');
        }
    }
    
    /**
     * Return unsubscribe confirmation page
     */
    private function unsubscribeResponse(bool $success, string $message): Response
    {
        $html = $this->getUnsubscribeHtml($success, $message);
        
        return response($html, 200, [
            'Content-Type' => 'text/html; charset=UTF-8',
        ]);
    }
    
    /**
     * Generate unsubscribe confirmation HTML
     */
    private function getUnsubscribeHtml(bool $success, string $message): string
    {
        $title = $success ? 'Unsubscribed Successfully' : 'Unsubscribe Error';
        $color = $success ? '#28a745' : '#dc3545';
        
        return "
        <!DOCTYPE html>
        <html lang='en'>
        <head>
            <meta charset='UTF-8'>
            <meta name='viewport' content='width=device-width, initial-scale=1.0'>
            <title>{$title}</title>
            <style>
                body {
                    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
                    background-color: #f8f9fa;
                    margin: 0;
                    padding: 20px;
                    display: flex;
                    justify-content: center;
                    align-items: center;
                    min-height: 100vh;
                }
                .container {
                    background: white;
                    border-radius: 8px;
                    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
                    padding: 40px;
                    text-align: center;
                    max-width: 500px;
                    width: 100%;
                }
                .icon {
                    font-size: 48px;
                    margin-bottom: 20px;
                }
                h1 {
                    color: {$color};
                    margin-bottom: 20px;
                    font-size: 24px;
                }
                p {
                    color: #6c757d;
                    font-size: 16px;
                    line-height: 1.5;
                    margin-bottom: 30px;
                }
                .footer {
                    color: #adb5bd;
                    font-size: 14px;
                    margin-top: 30px;
                }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='icon'>" . ($success ? '✅' : '❌') . "</div>
                <h1>{$title}</h1>
                <p>{$message}</p>
                <div class='footer'>
                    <p>You will no longer receive marketing emails from us.</p>
                    <p>If you have any questions, please contact our support team.</p>
                </div>
            </div>
        </body>
        </html>";
    }
}