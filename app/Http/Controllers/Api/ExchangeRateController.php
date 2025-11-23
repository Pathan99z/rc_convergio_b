<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\ExchangeRateService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class ExchangeRateController extends Controller
{
    public function __construct(
        private ExchangeRateService $exchangeRateService
    ) {}

    /**
     * Refresh exchange rate from API.
     */
    public function refresh(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'from_currency' => ['required', 'string', 'size:3'],
            'to_currency' => ['required', 'string', 'size:3'],
            'date' => ['nullable', 'date'],
        ]);

        try {
            $rate = $this->exchangeRateService->refreshRate(
                $validated['from_currency'],
                $validated['to_currency'],
                $validated['date'] ?? null
            );

            if ($rate === null) {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to fetch exchange rate from API'
                ], 422);
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'rate' => $rate,
                    'from_currency' => $validated['from_currency'],
                    'to_currency' => $validated['to_currency'],
                    'date' => $validated['date'] ?? now()->toDateString(),
                    'source' => 'frankfurter.app',
                    'updated_at' => now()->toDateTimeString(),
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to refresh exchange rate', [
                'from' => $validated['from_currency'],
                'to' => $validated['to_currency'],
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to refresh exchange rate: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get current exchange rate (from cache or database).
     */
    public function getRate(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'from_currency' => ['required', 'string', 'size:3'],
            'to_currency' => ['required', 'string', 'size:3'],
            'date' => ['nullable', 'date'],
        ]);

        try {
            $rate = $this->exchangeRateService->getRate(
                $validated['from_currency'],
                $validated['to_currency'],
                $validated['date'] ?? null
            );

            if ($rate === null) {
                return response()->json([
                    'success' => false,
                    'message' => 'Exchange rate not available'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'rate' => $rate,
                    'from_currency' => $validated['from_currency'],
                    'to_currency' => $validated['to_currency'],
                    'date' => $validated['date'] ?? now()->toDateString(),
                    'source' => 'frankfurter.app',
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get exchange rate: ' . $e->getMessage()
            ], 500);
        }
    }
}
