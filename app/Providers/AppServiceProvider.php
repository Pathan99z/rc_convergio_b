<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Database\Eloquent\Relations\Relation;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        RateLimiter::for('login', function (Request $request): Limit {
            $email = (string) str($request->input('email', ''))->lower();
            return Limit::perMinutes(15, 5)->by($email.'|'.$request->ip());
        });

        // Customize the password reset URL used in emails so it doesn't rely on a missing named route
        ResetPassword::createUrlUsing(function ($notifiable, string $token): string {
            $email = urlencode($notifiable->getEmailForPasswordReset());
            // Point to a frontend URL you control; for local testing we'll just use app.url
            return config('app.url')."/reset-password?token={$token}&email={$email}";
        });

        // Define morph map for polymorphic relationships
        Relation::morphMap([
            'deal' => \App\Models\Deal::class,
            'contact' => \App\Models\Contact::class,
            'company' => \App\Models\Company::class,
        ]);

        // Auto-start queue worker for campaign automation
        $this->startQueueWorkerIfNeeded();
    }

    /**
     * Automatically start queue worker if not running
     * This ensures campaigns work without manual intervention
     */
    private function startQueueWorkerIfNeeded(): void
    {
        // Only start in web context (not CLI) and if not already running
        if (php_sapi_name() !== 'cli' && !$this->isQueueWorkerRunning()) {
            $this->startQueueWorkerInBackground();
        }
    }

    /**
     * Check if queue worker is already running
     */
    private function isQueueWorkerRunning(): bool
    {
        if (PHP_OS_FAMILY === 'Windows') {
            $processes = shell_exec('tasklist /FI "IMAGENAME eq php.exe" /FO CSV | findstr "queue:work"');
            return !empty($processes);
        } else {
            $processes = shell_exec('ps aux | grep "queue:work" | grep -v grep');
            return !empty($processes);
        }
    }

    /**
     * Start queue worker in background
     */
    private function startQueueWorkerInBackground(): void
    {
        $command = 'php artisan queue:work --queue=default --tries=3 --timeout=120 --memory=512';
        
        if (PHP_OS_FAMILY === 'Windows') {
            // Windows: Start in background
            pclose(popen("start /B {$command}", 'r'));
        } else {
            // Linux/Mac: Start in background
            exec("{$command} > /dev/null 2>&1 &");
        }
        
        // Log that we started the worker
        \Illuminate\Support\Facades\Log::info('Queue worker started automatically for campaign automation');
    }
}
