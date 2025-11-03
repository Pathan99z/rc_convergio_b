<?php

namespace App\Notifications;

use Illuminate\Auth\Notifications\VerifyEmail as BaseVerifyEmail;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Carbon;

class VerifyEmailNotification extends BaseVerifyEmail implements ShouldQueue
{
    use Queueable;
    
    /**
     * The number of times the job may be attempted.
     * This prevents duplicate email sends on retries
     */
    public $tries = 1;
    protected function verificationUrl($notifiable): string
    {
        $temporarySignedUrl = URL::temporarySignedRoute(
            'auth.verify',
            Carbon::now()->addMinutes(60),
            [
                'id' => $notifiable->getKey(),
                'hash' => sha1($notifiable->getEmailForVerification()),
            ]
        );

        return $temporarySignedUrl;
    }

    public function toMail($notifiable): MailMessage
    {
        $url = $this->verificationUrl($notifiable);
        
        // Get logo as base64 embedded image (works in most email clients)
        // For Gmail: Base64 may be blocked, but we'll use it as best option
        $logoBase64 = $this->getLogoBase64();
        
        // Also try to get public URL (works in Gmail if APP_URL is set to real domain)
        $logoUrl = $this->getLogoUrl();
        
        // Use MailMessage's view method with variables properly passed
        $mailMessage = new MailMessage();
        $mailMessage->subject('Verify Your Email Address - RC Convergio');
        
        // Pass both base64 and URL - template will use URL if available (for Gmail), else base64
        return $mailMessage->view('emails.verify-email', [
            'url' => $url,
            'logoBase64' => $logoBase64,
            'logoUrl' => $logoUrl,
        ]);
    }
    
    /**
     * Get logo file path
     * Place your logo image as: public/images/logo.png
     */
    private function getLogoPath(): ?string
    {
        $logoFileName = 'logo.png';
        $logoPath = public_path('images/' . $logoFileName);
        
        if (file_exists($logoPath)) {
            return $logoPath;
        }
        
        return null;
    }
    
    /**
     * Get logo as public URL (works in Gmail when APP_URL is set to real domain)
     */
    private function getLogoUrl(): ?string
    {
        $logoFileName = 'logo.png';
        $logoPath = public_path('images/' . $logoFileName);
        
        if (file_exists($logoPath)) {
            $baseUrl = config('app.url', 'http://127.0.0.1:8000');
            $baseUrl = rtrim($baseUrl, '/');
            return $baseUrl . '/images/' . $logoFileName;
        }
        
        return null;
    }
    
    /**
     * Get logo as base64 data URI (fallback if CID embedding fails)
     */
    private function getLogoBase64(): ?string
    {
        $logoPath = $this->getLogoPath();
        
        if ($logoPath && file_exists($logoPath)) {
            try {
                $logoData = file_get_contents($logoPath);
                $logoMime = mime_content_type($logoPath) ?: 'image/png';
                $logoBase64 = base64_encode($logoData);
                return "data:{$logoMime};base64,{$logoBase64}";
            } catch (\Exception $e) {
                Log::warning('Failed to load logo for email', ['error' => $e->getMessage()]);
                return null;
            }
        }
        
        return null;
    }
}


