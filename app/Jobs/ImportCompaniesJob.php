<?php

namespace App\Jobs;

use App\Models\Company;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB; // ADDED: Database facade for transactions
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use League\Csv\Reader;

class ImportCompaniesJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 300; // 5 minutes

    /**
     * Create a new job instance.
     */
    public function __construct(
        private string $path,
        private int $tenantId,
        private int $userId
    ) {}

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $result = $this->processImport();
        
        // Log success message for immediate visibility
        if ($result['imported'] > 0) {
            Log::info('ImportCompaniesJob: SUCCESS - Companies are now available in database', [
                'companies_imported' => $result['imported'],
                'tenant_id' => $this->tenantId,
                'user_id' => $this->userId,
                'company_ids' => $result['company_ids'] ?? []
            ]);
        }
    }

    /**
     * Process import synchronously and return results
     */
    public function handleSync(): array
    {
        return $this->processImport();
    }

    /**
     * Core import processing logic with enhanced database transaction and verification
     */
    private function processImport(): array
    {
        Log::info('ImportCompaniesJob: Starting company import with enhanced persistence', [
            'path' => $this->path,
            'tenantId' => $this->tenantId,
            'userId' => $this->userId
        ]);

        $fullPath = Storage::path($this->path);

        if (! is_file($fullPath)) {
            Log::error('ImportCompaniesJob: file missing', ['path' => $fullPath]);
            throw new \RuntimeException('Import file not found: ' . $this->path);
        }

        Log::info('ImportCompaniesJob: File found, processing CSV');

        try {
            $csv = Reader::createFromPath($fullPath, 'r');
            $csv->setHeaderOffset(0);
        } catch (\Exception $e) {
            Log::error('ImportCompaniesJob: Failed to read CSV file', [
                'path' => $fullPath,
                'error' => $e->getMessage()
            ]);
            throw new \RuntimeException('Failed to read CSV file: ' . $e->getMessage());
        }

        $processedCount = 0;
        $errorCount = 0;
        $totalRows = 0;
        $importedCompanies = [];

        // Enhanced database transaction with explicit commit and verification
        return DB::transaction(function () use ($csv, &$processedCount, &$errorCount, &$totalRows, &$importedCompanies) {
            
            Log::info('ImportCompaniesJob: Starting database transaction for company import');
            
            foreach ($csv->getRecords() as $offset => $record) {
                $totalRows++;
                try {
                    // Skip empty rows
                    if (empty(array_filter($record))) {
                        Log::debug('ImportCompaniesJob: Skipping empty row', ['row' => $offset]);
                        continue;
                    }

                    Log::debug('ImportCompaniesJob: Processing row', ['row' => $offset, 'record' => $record]);

                    $data = $this->cleanRecord($record);
                    
                    // Add required fields
                    $data['tenant_id'] = $this->tenantId;
                    $data['owner_id'] = $this->userId;

                    Log::debug('ImportCompaniesJob: Processed data', ['data' => $data]);

                    // Basic validation
                    if (empty($data['name'])) {
                        throw new \RuntimeException('Company name is required');
                    }

                    // Use updateOrCreate to avoid duplicates based on domain and tenant
                    $company = Company::updateOrCreate(
                        [
                            'domain' => $data['domain'] ?? null,
                            'tenant_id' => $this->tenantId
                        ],
                        $data
                    );

                    // Force refresh to ensure we have the latest data
                    $company->refresh();

                    Log::info('ImportCompaniesJob: Company created/updated in DB', [
                        'company_id' => $company->id,
                        'name' => $company->name,
                        'domain' => $company->domain,
                        'tenant_id' => $company->tenant_id,
                        'owner_id' => $company->owner_id,
                        'address' => $company->address
                    ]);

                    $importedCompanies[] = $company->id;
                    $processedCount++;

                } catch (\Throwable $e) {
                    Log::warning('ImportCompaniesJob: row skipped', [
                        'row' => $offset,
                        'error' => $e->getMessage(),
                        'record' => $record
                    ]);
                    $errorCount++;
                }
            }

            // Enhanced verification: Verify that companies were actually inserted into database
            if ($processedCount > 0) {
                Log::info('ImportCompaniesJob: Starting database verification', [
                    'expected_companies' => $processedCount,
                    'company_ids' => $importedCompanies
                ]);

                // First verification: Count companies by IDs
                $actualCount = Company::whereIn('id', $importedCompanies)
                    ->where('tenant_id', $this->tenantId)
                    ->count();
                
                Log::info('ImportCompaniesJob: Database verification - Count check', [
                    'expected_companies' => $processedCount,
                    'actual_companies_in_db' => $actualCount,
                    'company_ids' => $importedCompanies
                ]);

                if ($actualCount !== $processedCount) {
                    throw new \RuntimeException("Database verification failed: Expected {$processedCount} companies, but found {$actualCount} in database.");
                }

                // Second verification: Verify each company exists and has correct data
                foreach ($importedCompanies as $companyId) {
                    $company = Company::find($companyId);
                    if (!$company) {
                        throw new \RuntimeException("Database verification failed: Company ID {$companyId} not found in database.");
                    }
                    if ($company->tenant_id !== $this->tenantId) {
                        throw new \RuntimeException("Database verification failed: Company ID {$companyId} has wrong tenant_id.");
                    }
                }

                Log::info('ImportCompaniesJob: Database verification - Individual company check passed', [
                    'verified_companies' => count($importedCompanies)
                ]);

                // Force database commit to ensure data is immediately visible
                DB::commit();
                
                Log::info('ImportCompaniesJob: Database transaction committed successfully', [
                    'companies_imported' => $processedCount,
                    'company_ids' => $importedCompanies
                ]);

                // Final verification: Query again to ensure data is visible
                $finalCount = Company::whereIn('id', $importedCompanies)
                    ->where('tenant_id', $this->tenantId)
                    ->count();
                
                Log::info('ImportCompaniesJob: Final database verification', [
                    'expected_companies' => $processedCount,
                    'final_companies_in_db' => $finalCount,
                    'verification_status' => $finalCount === $processedCount ? 'PASSED' : 'FAILED'
                ]);

                if ($finalCount !== $processedCount) {
                    throw new \RuntimeException("Final database verification failed: Expected {$processedCount} companies, but found {$finalCount} in database after commit.");
                }

                Log::info('ImportCompaniesJob: DB persistence confirmed ✅', [
                    'companies_imported' => $processedCount,
                    'company_ids' => $importedCompanies,
                    'tenant_id' => $this->tenantId
                ]);
            }

            // Clean up the file after processing
            try {
                Storage::delete($this->path);
                Log::info('ImportCompaniesJob: Cleaned up import file', ['path' => $this->path]);
            } catch (\Exception $e) {
                Log::warning('ImportCompaniesJob: Failed to clean up import file', [
                    'path' => $this->path,
                    'error' => $e->getMessage()
                ]);
            }

            Log::info('ImportCompaniesJob: Import completed and verified in database', [
                'total_rows' => $totalRows,
                'processed' => $processedCount,
                'errors' => $errorCount,
                'success_rate' => $totalRows > 0 ? round(($processedCount / $totalRows) * 100, 2) : 0,
                'company_ids_imported' => $importedCompanies
            ]);

            if ($processedCount === 0 && $totalRows > 0) {
                throw new \RuntimeException('No companies were imported. Please check your CSV format and data.');
            }

            return [
                'imported' => $processedCount,
                'failed' => $errorCount,
                'total' => $totalRows,
                'success_rate' => $totalRows > 0 ? round(($processedCount / $totalRows) * 100, 2) : 0,
                'company_ids' => $importedCompanies
            ];
        });
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
            'phone' => ['phone', 'telephone', 'contact_phone'],
            'email' => ['email', 'contact_email'],
        ];

        foreach ($mapping as $field => $possibleNames) {
            foreach ($possibleNames as $name) {
                if (isset($record[$name]) && !empty(trim((string) $record[$name]))) {
                    $data[$field] = trim((string) $record[$name]);
                    break;
                }
            }
        }

        // Handle address fields - map flat CSV fields to nested JSON structure
        $addressMapping = [
            'street' => ['address_street', 'street', 'address_line1'],
            'city' => ['address_city', 'city'],
            'state' => ['address_state', 'state', 'province'],
            'postal_code' => ['address_postal_code', 'postal_code', 'zip_code', 'zip'],
            'country' => ['address_country', 'country']
        ];
        
        $address = [];
        foreach ($addressMapping as $addressField => $possibleNames) {
            foreach ($possibleNames as $csvField) {
                if (isset($record[$csvField])) {
                    $value = trim((string) $record[$csvField]);
                    if (!empty($value)) {
                        $address[$addressField] = $value;
                    }
                    break; // Use first match found
                }
            }
        }
        
        // Only add address if at least one field is present
        if (!empty($address)) {
            $data['address'] = $address;
            
            Log::debug('ImportCompaniesJob: Address mapping completed', [
                'original_record' => array_intersect_key($record, array_flip(['address_street', 'address_city', 'address_state', 'address_postal_code', 'address_country'])),
                'mapped_address' => $address
            ]);
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
