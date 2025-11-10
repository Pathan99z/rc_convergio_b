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

        // Ensure storage directories exist (safe - no background processes, no loops)
        // This fixes permission issues on servers (Linux/ngrok) while working locally
        $this->ensureStorageDirectoriesExist();

        // Auto-start queue worker for campaign automation
        $this->startQueueWorkerIfNeeded();
        
        // Auto-start scheduler for scheduled campaigns
        // TEMPORARILY DISABLED - Commented out to prevent server issues
        // $this->startSchedulerIfNeeded();
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
            // Windows: Use exec() instead of popen() to avoid PowerShell input redirection error
            // The empty quotes after start /B ensure proper command parsing
            try {
                exec("start /B \"\" {$command} > NUL 2>&1");
            } catch (\Exception $e) {
                // Silently fail - queue worker is optional for basic functionality
                \Illuminate\Support\Facades\Log::warning('Failed to start queue worker: ' . $e->getMessage());
            }
        } else {
            // Linux/Mac: Start in background
            exec("{$command} > /dev/null 2>&1 &");
        }
        
        // Log that we started the worker
        \Illuminate\Support\Facades\Log::info('Queue worker started automatically for campaign automation');
    }

    /**
     * Automatically start Laravel scheduler if not running
     * This ensures scheduled campaigns are processed on time
     */
    private function startSchedulerIfNeeded(): void
    {
        // Only start in web context (not CLI) and if not already running
        if (php_sapi_name() !== 'cli' && !$this->isSchedulerRunning()) {
            $this->startSchedulerInBackground();
        }
    }

    /**
     * Check if scheduler is already running
     */
    private function isSchedulerRunning(): bool
    {
        if (PHP_OS_FAMILY === 'Windows') {
            $processes = shell_exec('tasklist /FI "IMAGENAME eq php.exe" /FO CSV | findstr "schedule:run"');
            return !empty($processes);
        } else {
            $processes = shell_exec('ps aux | grep "schedule:run" | grep -v grep');
            return !empty($processes);
        }
    }

    /**
     * Start scheduler in background
     * On Windows, we'll use a loop to run schedule:run every minute
     */
    private function startSchedulerInBackground(): void
    {
        if (PHP_OS_FAMILY === 'Windows') {
            // Windows: Use a batch script that runs schedule:run in a loop
            $scriptsDir = base_path('scripts');
            if (!is_dir($scriptsDir)) {
                mkdir($scriptsDir, 0755, true);
            }
            $scriptPath = $scriptsDir . '/start-scheduler.bat';
            if (!file_exists($scriptPath)) {
                // Create the script if it doesn't exist
                $scriptContent = "@echo off\n";
                $scriptContent .= ":loop\n";
                $scriptContent .= "cd /d " . base_path() . "\n";
                $scriptContent .= "php artisan schedule:run\n";
                $scriptContent .= "timeout /t 60 /nobreak > nul\n";
                $scriptContent .= "goto loop\n";
                file_put_contents($scriptPath, $scriptContent);
            }
            // Use exec() instead of popen() to avoid PowerShell input redirection error
            // The empty quotes after start /B ensure proper command parsing
            try {
                exec("start /B \"\" \"{$scriptPath}\" > NUL 2>&1");
            } catch (\Exception $e) {
                // Silently fail - scheduler is optional for basic functionality
                \Illuminate\Support\Facades\Log::warning('Failed to start scheduler: ' . $e->getMessage());
            }
        } else {
            // Linux/Mac: Use a simple loop
            $command = 'while true; do php artisan schedule:run; sleep 60; done';
            exec("{$command} > /dev/null 2>&1 &");
        }
        
        // Log that we started the scheduler
        \Illuminate\Support\Facades\Log::info('Laravel scheduler started automatically for scheduled campaigns');
    }

    /**
     * Ensure all required storage directories exist with proper permissions
     * SAFE: Only creates directories, no background processes, no loops, no blocking
     * This fixes permission issues on servers (Linux/ngrok) while working locally
     * Does not affect any APIs, login, or other functionality
     */
    private function ensureStorageDirectoriesExist(): void
    {
        // Only check critical directories that are needed for Blade compilation and file operations
        $criticalDirectories = [
            storage_path('framework/views'), // Critical for PDF generation and Blade templates
        ];

        foreach ($criticalDirectories as $directory) {
            // Create directory if it doesn't exist
            if (!is_dir($directory)) {
                try {
                    // Create directory with recursive flag (creates parent directories if needed)
                    mkdir($directory, 0755, true);
                } catch (\Exception $e) {
                    // Silently fail - log only if directory creation fails
                    // This won't break the application, just log the issue for debugging
                    \Illuminate\Support\Facades\Log::warning("Storage directory check: {$directory} - " . $e->getMessage());
                }
            }

            // Try to fix permissions on Linux servers (even if directory already exists)
            if (is_dir($directory) && PHP_OS_FAMILY !== 'Windows') {
                try {
                    // Check if directory is writable
                    if (!is_writable($directory)) {
                        // Try to set writable permissions (0755 = rwxr-xr-x)
                        @chmod($directory, 0755);
                        
                        // Also ensure parent directories have correct permissions
                        $parentDir = dirname($directory);
                        if (is_dir($parentDir) && !is_writable($parentDir)) {
                            @chmod($parentDir, 0755);
                        }
                    }
                } catch (\Exception $e) {
                    // Silently fail - permissions might be managed by system or require sudo
                    // This is expected on some servers where PHP doesn't have permission to chmod
                }
            }
        }
    }
}
