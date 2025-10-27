<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class CacheServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        // Smart cache driver detection
        $this->detectBestCacheDriver();
    }

    /**
     * Automatically detect and configure the best cache driver
     */
    private function detectBestCacheDriver(): void
    {
        $cacheStore = config('cache.default');
        
        // If Redis is configured, test if it's available
        if ($cacheStore === 'redis') {
            try {
                // Test Redis connection
                Cache::store('redis')->get('connection_test');
                Log::info('Redis cache is available and working');
            } catch (\Exception $e) {
                // Redis not available, fallback to file cache
                Log::warning('Redis not available, falling back to file cache: ' . $e->getMessage());
                config(['cache.default' => 'file']);
                
                // Also update queue and session to safe defaults
                config(['queue.default' => 'sync']);
                config(['session.driver' => 'file']);
            }
        }
        
        // Log the final cache configuration
        Log::info('Cache driver configured: ' . config('cache.default'));
    }
}

