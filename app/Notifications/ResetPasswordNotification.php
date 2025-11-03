<?php

namespace App\Notifications;

use Illuminate\Auth\Notifications\ResetPassword as BaseResetPassword;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Facades\Log;

class ResetPasswordNotification extends BaseResetPassword implements ShouldQueue
{
    use Queueable;
    
    /**
     * The number of times the job may be attempted.
     * This prevents duplicate email sends on retries
     */
    public $tries = 1;
    
    /**
     * Build the mail representation of the notification.
     */
    public function toMail($notifiable): MailMessage
    {
        // Use parent's resetUrl() method which respects ResetPassword::createUrlUsing() callbacks
        // This ensures the URL points to frontend as configured in AuthServiceProvider/AppServiceProvider
        $url = parent::resetUrl($notifiable);
        
        // Get logo as base64 embedded image (works in most email clients)
        $logoBase64 = $this->getLogoBase64();
        
        // Also try to get public URL (works in Gmail if APP_URL is set to real domain)
        $logoUrl = $this->getLogoUrl();
        
        // Use MailMessage's view method with variables properly passed
        $mailMessage = new MailMessage();
        $mailMessage->subject('Reset Password Notification - RC Convergio');
        
        // Pass both base64 and URL - template will use URL if available (for Gmail), else base64
        return $mailMessage->view('emails.reset-password', [
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
     * Ensures HTTPS for production domains (Gmail prefers HTTPS)
     */
    private function getLogoUrl(): ?string
    {
        $logoFileName = 'logo.png';
        $logoPath = public_path('images/' . $logoFileName);
        
        if (file_exists($logoPath)) {
            $baseUrl = config('app.url', 'http://127.0.0.1:8000');
            $baseUrl = rtrim($baseUrl, '/');
            
            // Ensure HTTPS for production domains (Gmail prefers secure URLs)
            if (strpos($baseUrl, 'http://') === 0 && strpos($baseUrl, 'localhost') === false && strpos($baseUrl, '127.0.0.1') === false) {
                $baseUrl = str_replace('http://', 'https://', $baseUrl);
            }
            
            $logoUrl = $baseUrl . '/images/' . $logoFileName;
            
            Log::debug('Logo URL generated for email', ['url' => $logoUrl, 'exists' => file_exists($logoPath)]);
            
            return $logoUrl;
        }
        
        Log::debug('Logo file not found', ['path' => $logoPath]);
        return null;
    }
    
    /**
     * Get logo as base64 data URI (fallback)
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

