<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Http\UploadedFile;

class CampaignCsvService
{
    /**
     * Parse email addresses from CSV file
     * 
     * @param string $filePath Full path to CSV file
     * @return array Array of valid email addresses
     */
    public function parseEmailsFromCsv(string $filePath): array
    {
        $emails = [];
        
        if (!file_exists($filePath)) {
            Log::error('CSV file not found', ['path' => $filePath]);
            return $emails;
        }

        $handle = fopen($filePath, 'r');
        if ($handle === false) {
            Log::error('Failed to open CSV file', ['path' => $filePath]);
            return $emails;
        }

        // Read header row
        $header = fgetcsv($handle);
        if ($header === false) {
            fclose($handle);
            return $emails;
        }

        // Find email column index
        $emailColumnIndex = $this->getEmailColumnIndex($header);

        // Read data rows
        $lineNumber = 1; // Header is line 1
        while (($row = fgetcsv($handle)) !== false) {
            $lineNumber++;
            
            if (isset($row[$emailColumnIndex])) {
                $email = trim($row[$emailColumnIndex]);
                
                // Skip empty cells
                if (empty($email)) {
                    continue;
                }
                
                // Validate email
                if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    $emails[] = strtolower($email); // Normalize to lowercase
                } else {
                    Log::warning('Invalid email in CSV', [
                        'line' => $lineNumber,
                        'email' => $email
                    ]);
                }
            }
        }
        
        fclose($handle);
        
        // Remove duplicates while preserving order
        $emails = array_values(array_unique($emails));
        
        Log::info('CSV parsing completed', [
            'file_path' => $filePath,
            'total_emails' => count($emails),
            'line_number' => $lineNumber
        ]);
        
        return $emails;
    }

    /**
     * Get email column index from CSV header
     * 
     * @param array $header CSV header row
     * @return int Column index (defaults to 0 if not found)
     */
    private function getEmailColumnIndex(array $header): int
    {
        foreach ($header as $index => $column) {
            $columnLower = strtolower(trim($column));
            // Check for common email column names
            if (in_array($columnLower, ['email', 'e-mail', 'email address', 'email_address', 'mail'])) {
                return $index;
            }
        }
        
        // If no email column found, assume first column
        return 0;
    }

    /**
     * Save uploaded CSV file to storage
     * 
     * @param UploadedFile $file
     * @param int $campaignId
     * @return string Relative path to stored file
     */
    public function saveCsvFile(UploadedFile $file, int $campaignId): string
    {
        $filename = $campaignId . '_' . time() . '.csv';
        $relativePath = 'temp/campaigns/' . $filename;
        $fullPath = storage_path('app/' . $relativePath);
        
        // Ensure directory exists
        $directory = dirname($fullPath);
        if (!is_dir($directory)) {
            mkdir($directory, 0755, true);
        }
        
        // Move uploaded file
        $file->move(dirname($fullPath), $filename);
        
        Log::info('CSV file saved', [
            'campaign_id' => $campaignId,
            'file_path' => $relativePath,
            'file_size' => filesize($fullPath)
        ]);
        
        return $relativePath;
    }

    /**
     * Delete CSV file from storage
     * 
     * @param string $relativePath Relative path to file
     * @return bool True if deleted, false otherwise
     */
    public function deleteCsvFile(string $relativePath): bool
    {
        $fullPath = storage_path('app/' . $relativePath);
        
        if (file_exists($fullPath)) {
            $deleted = @unlink($fullPath);
            if ($deleted) {
                Log::info('CSV file deleted', ['file_path' => $relativePath]);
            }
            return $deleted;
        }
        
        return false;
    }

    /**
     * Get email count from CSV file without loading all emails
     * Useful for quick validation
     * 
     * @param string $filePath
     * @return int Email count
     */
    public function countEmailsInCsv(string $filePath): int
    {
        $emails = $this->parseEmailsFromCsv($filePath);
        return count($emails);
    }
}

