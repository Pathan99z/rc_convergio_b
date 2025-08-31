<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class StartQueueWorker extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'queue:start-worker {--timeout=60 : The number of seconds a child process can run} {--tries=3 : Number of times to attempt a job before logging it failed}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Start the queue worker for processing background jobs like contact imports';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('🚀 Starting Laravel Queue Worker...');
        $this->info('This will process background jobs like contact imports.');
        $this->info('Press Ctrl+C to stop the worker.');
        $this->newLine();

        // Check if there are any pending jobs
        $pendingJobs = \Illuminate\Support\Facades\DB::table('jobs')->count();
        if ($pendingJobs > 0) {
            $this->warn("⚠️  Found {$pendingJobs} pending job(s) in the queue.");
            $this->info('These jobs will be processed when the worker starts.');
            $this->newLine();
        }

        // Check queue configuration
        $queueDriver = config('queue.default');
        $this->info("📋 Queue Driver: {$queueDriver}");
        
        if ($queueDriver === 'database') {
            $this->info('✅ Database queue driver is configured correctly.');
        } else {
            $this->warn("⚠️  Current queue driver is '{$queueDriver}'. For best performance, consider using 'database'.");
        }

        $this->newLine();
        $this->info('🔄 Starting queue worker...');
        $this->info('The worker will now process jobs continuously.');
        $this->newLine();

        // Start the queue worker
        $timeout = $this->option('timeout');
        $tries = $this->option('tries');
        
        $command = "php artisan queue:work --timeout={$timeout} --tries={$tries} --stop-when-empty";
        
        $this->info("Command: {$command}");
        $this->newLine();

        // Execute the queue work command
        passthru($command);
    }
}
