<?php

namespace App\Services;

use App\Models\Quote;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class QuoteNumberGenerator
{
    /**
     * Generate a unique quote number in format Q-YYYY-####
     * Note: Quote numbers are globally unique across all tenants due to database constraint
     */
    public function generate(int $tenantId): string
    {
        $year = Carbon::now()->year;
        $prefix = "Q-{$year}-";

        // Get the highest quote number for this year across ALL tenants (including soft deleted)
        // The database has a global unique constraint, so we must check globally
        $lastQuote = Quote::withoutGlobalScopes()
            ->withTrashed() // Include soft-deleted records in the search
            ->where('quote_number', 'like', $prefix . '%')
            ->orderBy('quote_number', 'desc')
            ->first();

        if ($lastQuote) {
            // Extract the number part and increment
            $lastNumber = (int) substr($lastQuote->quote_number, strlen($prefix));
            $nextNumber = $lastNumber + 1;
        } else {
            $nextNumber = 1;
        }

        return $prefix . str_pad($nextNumber, 4, '0', STR_PAD_LEFT);
    }

    /**
     * Check if a quote number is unique globally.
     * Note: Due to global database constraint, quote numbers must be unique across all tenants
     */
    public function isUnique(string $quoteNumber, int $tenantId): bool
    {
        // Check globally since the database constraint is global
        return !Quote::withoutGlobalScopes()
            ->withTrashed() // Include soft-deleted records in the uniqueness check
            ->where('quote_number', $quoteNumber)
            ->exists();
    }

    /**
     * Generate a quote number with retry logic for uniqueness.
     */
    public function generateUnique(int $tenantId): string
    {
        $maxAttempts = 10;
        $attempts = 0;

        do {
            $quoteNumber = $this->generate($tenantId);
            $attempts++;
            
            // If not unique, try to clean up soft-deleted quotes with the same number
            if (!$this->isUnique($quoteNumber, $tenantId)) {
                $this->cleanupSoftDeletedQuote($quoteNumber, $tenantId);
            }
            
        } while (!$this->isUnique($quoteNumber, $tenantId) && $attempts < $maxAttempts);

        if ($attempts >= $maxAttempts) {
            throw new \Exception('Unable to generate unique quote number after multiple attempts');
        }

        return $quoteNumber;
    }
    
    /**
     * Clean up soft-deleted quotes with the same number to free up the number.
     * Note: Since quote numbers are globally unique, we clean up globally
     */
    private function cleanupSoftDeletedQuote(string $quoteNumber, int $tenantId): void
    {
        $softDeletedQuotes = Quote::withTrashed()
            ->where('quote_number', $quoteNumber)
            ->whereNotNull('deleted_at')
            ->get();
            
        foreach ($softDeletedQuotes as $quote) {
            $quote->forceDelete();
        }
    }
}
