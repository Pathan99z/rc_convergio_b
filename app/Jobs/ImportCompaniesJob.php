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
use League\Csv\Reader;

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
            $csv = Reader::createFromPath($this->filePath, 'r');
            $csv->setHeaderOffset(0);

            $records = $csv->getRecords();
            $imported = 0;
            $errors = [];

            foreach ($records as $index => $record) {
                try {
                    // Clean and validate the data
                    $data = $this->cleanRecord($record);
                    
                    // Check for duplicates
                    $duplicates = $companyService->checkDuplicates($data, $this->tenantId);
                    if ($duplicates->isNotEmpty()) {
                        $errors[] = [
                            'row' => $index + 2, // +2 because of header and 0-based index
                            'data' => $data,
                            'error' => 'Duplicate company found: ' . $duplicates->first()->name
                        ];
                        continue;
                    }

                    // Create the company
                    $data['tenant_id'] = $this->tenantId;
                    $companyService->createCompany($data);
                    $imported++;

                } catch (\Exception $e) {
                    $errors[] = [
                        'row' => $index + 2,
                        'data' => $record,
                        'error' => $e->getMessage()
                    ];
                }
            }

            // Log results
            Log::info('Company import completed', [
                'tenant_id' => $this->tenantId,
                'user_id' => $this->userId,
                'imported' => $imported,
                'errors' => count($errors),
                'file' => $this->filePath
            ]);

            // Clean up the file
            if (file_exists($this->filePath)) {
                unlink($this->filePath);
            }

        } catch (\Exception $e) {
            Log::error('Company import failed', [
                'tenant_id' => $this->tenantId,
                'user_id' => $this->userId,
                'file' => $this->filePath,
                'error' => $e->getMessage()
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
            'owner_id' => ['owner_id', 'owner', 'assigned_to'],
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

        // Clean up data types
        if (isset($data['size']) && is_numeric($data['size'])) {
            $data['size'] = (int) $data['size'];
        }

        if (isset($data['annual_revenue']) && is_numeric($data['annual_revenue'])) {
            $data['annual_revenue'] = (float) $data['annual_revenue'];
        }

        if (isset($data['owner_id']) && is_numeric($data['owner_id'])) {
            $data['owner_id'] = (int) $data['owner_id'];
        }

        return $data;
    }
}
