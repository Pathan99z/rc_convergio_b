<?php

namespace App\Services;

use App\Models\ExchangeRate;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;

class ExchangeRateService
{
    /**
     * Get exchange rate from database or API.
     */
    public function getRate(string $fromCurrency, string $toCurrency, ?string $date = null): ?float
    {
        // Same currency, no conversion needed
        if ($fromCurrency === $toCurrency) {
            return 1.0;
        }

        $date = $date ?? now()->toDateString();

        // Check cache first
        $cacheKey = "exchange_rate_{$fromCurrency}_{$toCurrency}_{$date}";
        $cachedRate = Cache::get($cacheKey);
        
        if ($cachedRate !== null) {
            return (float) $cachedRate;
        }

        // Check database
        // Use whereDate for date comparison to handle different date formats
        $rate = ExchangeRate::where('from_currency', $fromCurrency)
            ->where('to_currency', $toCurrency)
            ->whereDate('effective_date', $date)
            ->where('is_active', true)
            ->first();

        if ($rate) {
            // Cache for 24 hours
            Cache::put($cacheKey, $rate->rate, now()->addHours(24));
            return (float) $rate->rate;
        }

        // Check for inverse rate before calling API
        $inverseRate = ExchangeRate::where('from_currency', $toCurrency)
            ->where('to_currency', $fromCurrency)
            ->whereDate('effective_date', $date)
            ->where('is_active', true)
            ->first();

        if ($inverseRate && $inverseRate->rate > 0) {
            $calculatedRate = 1 / (float) $inverseRate->rate;
            Cache::put($cacheKey, $calculatedRate, now()->addHours(24));
            return $calculatedRate;
        }

        // If not in database, fetch from API and store
        try {
            $apiResult = $this->fetchFromAPI($fromCurrency, $toCurrency);
            
            if ($apiResult && isset($apiResult['rate'])) {
                $fetchedRate = $apiResult['rate'];
                $source = $apiResult['source'] ?? 'api';
                
                // Store in database
                ExchangeRate::updateOrCreate(
                    [
                        'from_currency' => $fromCurrency,
                        'to_currency' => $toCurrency,
                        'effective_date' => $date,
                    ],
                    [
                        'rate' => $fetchedRate,
                        'source' => $source,
                        'is_active' => true,
                    ]
                );

                // Cache it
                Cache::put($cacheKey, $fetchedRate, now()->addHours(24));
                
                return (float) $fetchedRate;
            }
        } catch (\Exception $e) {
            Log::warning('Failed to fetch exchange rate from API', [
                'from' => $fromCurrency,
                'to' => $toCurrency,
                'error' => $e->getMessage(),
            ]);
        }

        return null;
    }

    /**
     * Convert amount from one currency to another.
     */
    public function convert(float $amount, string $fromCurrency, string $toCurrency, ?string $date = null): float
    {
        if ($fromCurrency === $toCurrency) {
            return $amount;
        }

        $rate = $this->getRate($fromCurrency, $toCurrency, $date);

        if ($rate === null) {
            throw new \Exception("Exchange rate not available for {$fromCurrency} to {$toCurrency}");
        }

        return round($amount * $rate, 2);
    }

    /**
     * Fetch exchange rate from external API (using free APIs).
     * Tries multiple free APIs as fallback.
     * Returns array with 'rate' and 'source' keys.
     */
    private function fetchFromAPI(string $fromCurrency, string $toCurrency): ?array
    {
        // Try Frankfurter.app first (free, reliable, no API key)
        try {
            $url = "https://api.frankfurter.app/latest";
            $response = Http::timeout(10)->get($url, [
                'from' => $fromCurrency,
                'to' => $toCurrency,
            ]);

            if ($response->successful()) {
                $data = $response->json();
                
                // Response format: { "amount": 1, "base": "USD", "date": "2025-11-21", "rates": { "EUR": 0.86806 } }
                if (isset($data['rates'][$toCurrency]) && $data['rates'][$toCurrency] > 0) {
                    $rate = (float) $data['rates'][$toCurrency];
                    Log::info('Exchange rate fetched from Frankfurter.app', [
                        'from' => $fromCurrency,
                        'to' => $toCurrency,
                        'rate' => $rate,
                    ]);
                    return ['rate' => $rate, 'source' => 'frankfurter.app'];
                }
            }
        } catch (\Exception $e) {
            Log::debug('Frankfurter.app API call failed, trying fallback', [
                'from' => $fromCurrency,
                'to' => $toCurrency,
                'error' => $e->getMessage(),
            ]);
        }

        // Fallback: Try exchangerate-api.com (free tier, no API key for basic usage)
        try {
            $url = "https://api.exchangerate-api.com/v4/latest/{$fromCurrency}";
            $response = Http::timeout(10)->get($url);

            if ($response->successful()) {
                $data = $response->json();
                
                // Response format: { "base": "USD", "date": "2025-11-23", "rates": { "EUR": 0.868, ... } }
                if (isset($data['rates'][$toCurrency]) && $data['rates'][$toCurrency] > 0) {
                    $rate = (float) $data['rates'][$toCurrency];
                    Log::info('Exchange rate fetched from exchangerate-api.com', [
                        'from' => $fromCurrency,
                        'to' => $toCurrency,
                        'rate' => $rate,
                    ]);
                    return ['rate' => $rate, 'source' => 'exchangerate-api.com'];
                }
            }
        } catch (\Exception $e) {
            Log::warning('Exchange rate API call failed', [
                'from' => $fromCurrency,
                'to' => $toCurrency,
                'api' => 'exchangerate-api.com',
                'error' => $e->getMessage(),
            ]);
        }

        return null;
    }

    /**
     * Refresh exchange rate from API (force fetch, bypass cache).
     */
    public function refreshRate(string $fromCurrency, string $toCurrency, ?string $date = null): ?float
    {
        $date = $date ?? now()->toDateString();
        
        // Clear cache
        $cacheKey = "exchange_rate_{$fromCurrency}_{$toCurrency}_{$date}";
        Cache::forget($cacheKey);
        
        // Fetch fresh rate from API
        $apiResult = $this->fetchFromAPI($fromCurrency, $toCurrency);
        
        if ($apiResult && isset($apiResult['rate'])) {
            $rate = $apiResult['rate'];
            $source = $apiResult['source'] ?? 'api';
            
            // Store in database - use whereDate to match existing records
            $existing = ExchangeRate::where('from_currency', $fromCurrency)
                ->where('to_currency', $toCurrency)
                ->whereDate('effective_date', $date)
                ->first();
            
            if ($existing) {
                $existing->update([
                    'rate' => $rate,
                    'source' => $source,
                    'is_active' => true,
                ]);
            } else {
                ExchangeRate::create([
                    'from_currency' => $fromCurrency,
                    'to_currency' => $toCurrency,
                    'effective_date' => $date,
                    'rate' => $rate,
                    'source' => $source,
                    'is_active' => true,
                ]);
            }
            
            // Cache it
            Cache::put($cacheKey, $rate, now()->addHours(24));
            
            return $rate;
        }
        
        return null;
    }

    /**
     * Update daily exchange rates (to be called by scheduled job).
     */
    public function updateDailyRates(array $currencyPairs = null): void
    {
        $defaultPairs = [
            ['USD', 'EUR'],
            ['USD', 'GBP'],
            ['USD', 'INR'],
            ['EUR', 'USD'],
            ['EUR', 'GBP'],
            ['GBP', 'USD'],
            ['GBP', 'EUR'],
        ];

        $pairs = $currencyPairs ?? $defaultPairs;
        $date = now()->toDateString();

        foreach ($pairs as $pair) {
            [$from, $to] = $pair;
            
            try {
                $rate = $this->fetchFromAPI($from, $to);
                
                if ($rate) {
                    ExchangeRate::updateOrCreate(
                        [
                            'from_currency' => $from,
                            'to_currency' => $to,
                            'effective_date' => $date,
                        ],
                        [
                            'rate' => $rate,
                            'source' => 'api',
                            'is_active' => true,
                        ]
                    );

                    // Clear cache
                    $cacheKey = "exchange_rate_{$from}_{$to}_{$date}";
                    Cache::forget($cacheKey);
                }
            } catch (\Exception $e) {
                Log::error('Failed to update exchange rate', [
                    'from' => $from,
                    'to' => $to,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    /**
     * Set manual exchange rate.
     */
    public function setManualRate(string $fromCurrency, string $toCurrency, float $rate, string $date = null): void
    {
        $date = $date ?? now()->toDateString();

        ExchangeRate::updateOrCreate(
            [
                'from_currency' => $fromCurrency,
                'to_currency' => $toCurrency,
                'effective_date' => $date,
            ],
            [
                'rate' => $rate,
                'source' => 'manual',
                'is_active' => true,
            ]
        );

        // Clear cache
        $cacheKey = "exchange_rate_{$fromCurrency}_{$toCurrency}_{$date}";
        Cache::forget($cacheKey);
    }
}

