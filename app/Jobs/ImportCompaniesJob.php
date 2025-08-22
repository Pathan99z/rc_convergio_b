<?php

namespace App\Jobs;

use App\Models\Company;
use App\Services\CompanyService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ImportCompaniesJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 300; // 5 minutes

    /**
     * Create a new job instance.
     */
    public function __construct(
        private string $filePath,
        private int $tenantId,
        private int $userId
    ) {}

    /**
     * Execute the job.
     */
    public function handle(CompanyService $companyService): void
    {
        try {
            Log::info('ImportCompaniesJob: Starting import', [
                'file' => $this->filePath,
                'tenant_id' => $this->tenantId,
                'user_id' => $this->userId
            ]);

            if (!file_exists($this->filePath)) {
                Log::error('ImportCompaniesJob: File not found', ['path' => $this->filePath]);
                return;
            }

            $content = file_get_contents($this->filePath);
            $lines = explode("\n", $content);
            
            // Skip header
            $headers = str_getcsv(array_shift($lines));
            $imported = 0;
            $errors = [];

            foreach ($lines as $index => $line) {
                if (empty(trim($line))) continue;
                
                try {
                    $record = str_getcsv($line);
                    if (count($record) < count($headers)) continue;
                    
                    $data = array_combine($headers, $record);
                    $data = $this->cleanRecord($data);
                    
                    // Create the company
                    $data['tenant_id'] = $this->tenantId;
                    $data['owner_id'] = $this->userId;
                    
                    Company::create($data);
                    $imported++;
                    
                    Log::info('ImportCompaniesJob: Company created', ['name' => $data['name']]);
                    
                } catch (\Exception $e) {
                    $errors[] = [
                        'row' => $index + 2,
                        'error' => $e->getMessage()
                    ];
                    Log::error('ImportCompaniesJob: Row error', ['row' => $index + 2, 'error' => $e->getMessage()]);
                }
            }

            Log::info('ImportCompaniesJob: Import completed', [
                'imported' => $imported,
                'errors' => count($errors)
            ]);

            // Clean up the file
            if (file_exists($this->filePath)) {
                unlink($this->filePath);
            }

        } catch (\Exception $e) {
            Log::error('ImportCompaniesJob: Import failed', [
                'error' => $e->getMessage(),
                'file' => $this->filePath
            ]);

            // Clean up the file on error too
            if (file_exists($this->filePath)) {
                unlink($this->filePath);
            }

            throw $e;
        }
    }

    /**
     * Clean and validate a CSV record
     */
    private function cleanRecord(array $record): array
    {
        $data = [];

        // Map CSV columns to model fields
        $mapping = [
            'name' => ['name', 'company_name', 'company'],
            'domain' => ['domain', 'company_domain'],
            'website' => ['website', 'url', 'company_website'],
            'industry' => ['industry', 'sector'],
            'size' => ['size', 'employee_count', 'employees'],
            'type' => ['type', 'company_type'],
            'annual_revenue' => ['annual_revenue', 'revenue', 'income'],
            'timezone' => ['timezone', 'time_zone'],
            'description' => ['description', 'about', 'notes'],
            'linkedin_page' => ['linkedin_page', 'linkedin', 'linkedin_url'],
            'status' => ['status'],
        ];

        foreach ($mapping as $field => $possibleNames) {
            foreach ($possibleNames as $name) {
                if (isset($record[$name]) && !empty(trim($record[$name]))) {
                    $data[$field] = trim($record[$name]);
                    break;
                }
            }
        }

        // Handle address fields
        $addressFields = ['street', 'city', 'state', 'postal_code', 'country'];
        $address = [];
        foreach ($addressFields as $field) {
            if (isset($record[$field]) && !empty(trim($record[$field]))) {
                $address[$field] = trim($record[$field]);
            }
        }
        if (!empty($address)) {
            $data['address'] = $address;
        }

        // Validate required fields
        if (empty($data['name'])) {
            throw new \Exception('Company name is required');
        }

        // Set default status if not provided
        if (empty($data['status'])) {
            $data['status'] = 'prospect';
        }

        // Clean up data types
        if (isset($data['size']) && is_numeric($data['size'])) {
            $data['size'] = (int) $data['size'];
        }

        if (isset($data['annual_revenue']) && is_numeric($data['annual_revenue'])) {
            $data['annual_revenue'] = (float) $data['annual_revenue'];
        }

        return $data;
    }
}
