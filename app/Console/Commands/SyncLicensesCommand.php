<?php

namespace App\Console\Commands;

use App\Services\LicenseService;
use Illuminate\Console\Command;

class SyncLicensesCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:sync-licenses 
                            {--dry-run : Show what would be done without making changes}
                            {--force : Force sync even if licenses already exist}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create licenses for existing tenants that don\'t have one';

    protected LicenseService $licenseService;

    /**
     * Create a new command instance.
     */
    public function __construct(LicenseService $licenseService)
    {
        parent::__construct();
        $this->licenseService = $licenseService;
    }

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('Starting license sync process...');

        if ($this->option('dry-run')) {
            $this->warn('DRY RUN MODE - No changes will be made');
        }

        try {
            $created = $this->licenseService->syncLicensesForExistingTenants();

            if ($this->option('dry-run')) {
                $this->info("Would create {$created} licenses for existing tenants");
            } else {
                $this->info("Successfully created {$created} licenses for existing tenants");
            }

            return Command::SUCCESS;

        } catch (\Exception $e) {
            $this->error('Failed to sync licenses: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }
}