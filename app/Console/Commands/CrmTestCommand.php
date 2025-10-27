<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;

class CrmTestCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'crm:test {--filter= : Filter specific test} {--verbose : Show detailed output}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Run comprehensive CRM automation tests with team-based access control';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('🚀 Starting CRM Automation Test Suite...');
        $this->newLine();

        // Check if team access feature is properly configured
        $this->checkConfiguration();

        // Run the tests
        $this->runTests();

        $this->newLine();
        $this->info('✅ CRM Automation Test Suite completed!');
        $this->info('📊 Check storage/logs/laravel.log for detailed results');
    }

    /**
     * Check configuration before running tests
     */
    private function checkConfiguration(): void
    {
        $this->info('🔍 Checking configuration...');

        // Check if feature config exists
        if (!config('features.team_access_enabled')) {
            $this->warn('⚠️  TEAM_ACCESS_ENABLED not set in config - will use default (false)');
        } else {
            $this->info('✅ Team access feature flag configured');
        }

        // Check if required models exist
        $requiredModels = [
            'App\Models\User',
            'App\Models\Team',
            'App\Models\Contact',
            'App\Models\Company',
            'App\Models\Deal',
            'App\Models\Task',
            'App\Models\Activity',
            'App\Models\Product',
            'App\Models\Campaign',
            'App\Models\Sequence'
        ];

        foreach ($requiredModels as $model) {
            if (class_exists($model)) {
                $this->info("✅ {$model} exists");
            } else {
                $this->error("❌ {$model} not found");
            }
        }

        $this->newLine();
    }

    /**
     * Run the CRM automation tests
     */
    private function runTests(): void
    {
        $this->info('🧪 Running CRM Automation Tests...');
        $this->newLine();

        $filter = $this->option('filter');
        $verbose = $this->option('verbose');

        // Build the test command
        $command = 'test tests/Feature/FullCrmAutomationTest.php';
        
        if ($filter) {
            $command .= " --filter={$filter}";
        }

        if ($verbose) {
            $command .= ' --verbose';
        }

        // Add group filter for selective runs
        $command .= ' --group=crm-automation';

        $this->info("Running: php artisan {$command}");
        $this->newLine();

        // Execute the test command
        $exitCode = Artisan::call($command);
        
        // Display the output
        $output = Artisan::output();
        $this->line($output);

        if ($exitCode === 0) {
            $this->info('✅ All tests passed!');
        } else {
            $this->error('❌ Some tests failed. Check the output above for details.');
        }
    }
}