<?php

namespace App\Jobs;

use App\Models\Contact;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB; // ADDED: Database facade for transactions
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use League\Csv\Reader;

class ImportContactsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public string $path, public int $tenantId, public int $userId) {}

    public function handle(): void
    {
        $result = $this->processImport();
        
        // Log success message for immediate visibility
        if ($result['imported'] > 0) {
            Log::info('ImportContactsJob: SUCCESS - Contacts are now available in database', [
                'contacts_imported' => $result['imported'],
                'tenant_id' => $this->tenantId,
                'user_id' => $this->userId,
                'contact_ids' => $result['contact_ids'] ?? []
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
        Log::info('ImportContactsJob: Starting contact import with enhanced persistence', [
            'path' => $this->path,
            'tenantId' => $this->tenantId,
            'userId' => $this->userId
        ]);

        $fullPath = Storage::path($this->path);

        if (! is_file($fullPath)) {
            Log::error('ImportContactsJob: file missing', ['path' => $fullPath]);
            throw new \RuntimeException('Import file not found: ' . $this->path);
        }

        Log::info('ImportContactsJob: File found, processing CSV');

        try {
            $csv = Reader::createFromPath($fullPath, 'r');
            $csv->setHeaderOffset(0);
        } catch (\Exception $e) {
            Log::error('ImportContactsJob: Failed to read CSV file', [
                'path' => $fullPath,
                'error' => $e->getMessage()
            ]);
            throw new \RuntimeException('Failed to read CSV file: ' . $e->getMessage());
        }

        $processedCount = 0;
        $errorCount = 0;
        $totalRows = 0;
        $importedContacts = [];

        // Enhanced database transaction with explicit commit and verification
        return DB::transaction(function () use ($csv, &$processedCount, &$errorCount, &$totalRows, &$importedContacts) {
            
            Log::info('ImportContactsJob: Starting database transaction for contact import');
            
            foreach ($csv->getRecords() as $offset => $record) {
                $totalRows++;
                try {
                    // Skip empty rows
                    if (empty(array_filter($record))) {
                        Log::debug('ImportContactsJob: Skipping empty row', ['row' => $offset]);
                        continue;
                    }

                    Log::debug('ImportContactsJob: Processing row', ['row' => $offset, 'record' => $record]);

                    $data = [
                        'first_name' => trim((string) ($record['first_name'] ?? '')),
                        'last_name' => trim((string) ($record['last_name'] ?? '')),
                        'email' => trim((string) ($record['email'] ?? '')) ?: null,
                        'phone' => trim((string) ($record['phone'] ?? '')) ?: null,
                        'owner_id' => $this->userId, // Always use the current user's ID
                        'company_id' => isset($record['company_id']) && trim((string) $record['company_id']) !== '' ? (int) $record['company_id'] : null,
                        'lifecycle_stage' => trim((string) ($record['lifecycle_stage'] ?? '')) ?: null,
                        'source' => trim((string) ($record['source'] ?? '')) ?: null,
                        'tags' => isset($record['tags']) && trim((string) $record['tags']) !== '' 
                            ? array_values(array_filter(array_map('trim', explode('|', (string) $record['tags'])))) 
                            : [],
                        'tenant_id' => $this->tenantId,
                    ];

                    Log::debug('ImportContactsJob: Processed data', ['data' => $data]);

                    // Basic per-row validation (mirrors FormRequest core checks)
                    if ($data['first_name'] === '' || $data['last_name'] === '') {
                        throw new \RuntimeException('Missing first_name/last_name');
                    }
                    if ($data['email'] !== null && ! filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
                        throw new \RuntimeException('Invalid email format');
                    }
                    if ($data['phone'] !== null && ! preg_match('/^\+?[1-9]\d{1,14}$/', (string) $data['phone'])) {
                        throw new \RuntimeException('Invalid phone format');
                    }

                    // Use updateOrCreate to avoid duplicates based on email and tenant
                    $contact = Contact::updateOrCreate(
                        [
                            'email' => $data['email'], 
                            'tenant_id' => $this->tenantId
                        ],
                        $data
                    );

                    // Force refresh to ensure we have the latest data
                    $contact->refresh();

                    Log::info('ImportContactsJob: Contact created/updated in DB', [
                        'contact_id' => $contact->id,
                        'email' => $contact->email,
                        'name' => $contact->first_name . ' ' . $contact->last_name,
                        'tenant_id' => $contact->tenant_id,
                        'owner_id' => $contact->owner_id
                    ]);

                    $importedContacts[] = $contact->id;
                    $processedCount++;

                } catch (\Throwable $e) {
                    Log::warning('ImportContactsJob: row skipped', [
                        'row' => $offset,
                        'error' => $e->getMessage(),
                        'record' => $record
                    ]);
                    $errorCount++;
                }
            }

            // Enhanced verification: Verify that contacts were actually inserted into database
            if ($processedCount > 0) {
                Log::info('ImportContactsJob: Starting database verification', [
                    'expected_contacts' => $processedCount,
                    'contact_ids' => $importedContacts
                ]);

                // First verification: Count contacts by IDs
                $actualCount = Contact::whereIn('id', $importedContacts)
                    ->where('tenant_id', $this->tenantId)
                    ->count();
                
                Log::info('ImportContactsJob: Database verification - Count check', [
                    'expected_contacts' => $processedCount,
                    'actual_contacts_in_db' => $actualCount,
                    'contact_ids' => $importedContacts
                ]);

                if ($actualCount !== $processedCount) {
                    throw new \RuntimeException("Database verification failed: Expected {$processedCount} contacts, but found {$actualCount} in database.");
                }

                // Second verification: Verify each contact exists and has correct data
                foreach ($importedContacts as $contactId) {
                    $contact = Contact::find($contactId);
                    if (!$contact) {
                        throw new \RuntimeException("Database verification failed: Contact ID {$contactId} not found in database.");
                    }
                    if ($contact->tenant_id !== $this->tenantId) {
                        throw new \RuntimeException("Database verification failed: Contact ID {$contactId} has wrong tenant_id.");
                    }
                }

                Log::info('ImportContactsJob: Database verification - Individual contact check passed', [
                    'verified_contacts' => count($importedContacts)
                ]);

                // Force database commit to ensure data is immediately visible
                DB::commit();
                
                Log::info('ImportContactsJob: Database transaction committed successfully', [
                    'contacts_imported' => $processedCount,
                    'contact_ids' => $importedContacts
                ]);

                // Final verification: Query again to ensure data is visible
                $finalCount = Contact::whereIn('id', $importedContacts)
                    ->where('tenant_id', $this->tenantId)
                    ->count();
                
                Log::info('ImportContactsJob: Final database verification', [
                    'expected_contacts' => $processedCount,
                    'final_contacts_in_db' => $finalCount,
                    'verification_status' => $finalCount === $processedCount ? 'PASSED' : 'FAILED'
                ]);

                if ($finalCount !== $processedCount) {
                    throw new \RuntimeException("Final database verification failed: Expected {$processedCount} contacts, but found {$finalCount} in database after commit.");
                }

                Log::info('ImportContactsJob: DB persistence confirmed ✅', [
                    'contacts_imported' => $processedCount,
                    'contact_ids' => $importedContacts,
                    'tenant_id' => $this->tenantId
                ]);
            }

            // Clean up the file after processing
            try {
                Storage::delete($this->path);
                Log::info('ImportContactsJob: Cleaned up import file', ['path' => $this->path]);
            } catch (\Exception $e) {
                Log::warning('ImportContactsJob: Failed to clean up import file', [
                    'path' => $this->path,
                    'error' => $e->getMessage()
                ]);
            }

            Log::info('ImportContactsJob: Import completed and verified in database', [
                'total_rows' => $totalRows,
                'processed' => $processedCount,
                'errors' => $errorCount,
                'success_rate' => $totalRows > 0 ? round(($processedCount / $totalRows) * 100, 2) : 0,
                'contact_ids_imported' => $importedContacts
            ]);

            if ($processedCount === 0 && $totalRows > 0) {
                throw new \RuntimeException('No contacts were imported. Please check your CSV format and data.');
            }

            return [
                'imported' => $processedCount,
                'failed' => $errorCount,
                'total' => $totalRows,
                'success_rate' => $totalRows > 0 ? round(($processedCount / $totalRows) * 100, 2) : 0,
                'contact_ids' => $importedContacts
            ];
        });
    }
}


