<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class EnrichmentController extends Controller
{
    public function enrich(Request $request): JsonResponse
    {
        try {
            // Validate the domain parameter
            $request->validate([
                'domain' => 'required|string|max:255',
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'error' => 'Domain parameter is required',
                'message' => 'Please provide a valid domain name',
            ], 400);
        }

        $domain = $request->query('domain');
        $clearbitApiKey = config('services.clearbit.key');

        // If no Clearbit API key is configured, return mock data
        if (!$clearbitApiKey) {
            Log::info('Clearbit API key not configured, returning mock enrichment data', ['domain' => $domain]);
            return response()->json($this->getMockEnrichmentData($domain));
        }

        try {
            // Call Clearbit API
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $clearbitApiKey,
                'Accept' => 'application/json',
            ])->get('https://company.clearbit.com/v2/companies/find', [
                'domain' => $domain,
            ]);

            if ($response->successful()) {
                $companyData = $response->json();
                return response()->json($this->normalizeClearbitResponse($companyData));
            }

            if ($response->status() === 404) {
                return response()->json([
                    'error' => 'Company not found',
                    'message' => 'No company information found for the provided domain',
                ], 404);
            }

            // Log the error and return a safe response
            Log::error('Clearbit API error', [
                'domain' => $domain,
                'status' => $response->status(),
                'response' => $response->body(),
            ]);

            return response()->json([
                'error' => 'External service error',
                'message' => 'Unable to fetch company information at this time',
            ], 500);

        } catch (\Exception $e) {
            Log::error('Enrichment API exception', [
                'domain' => $domain,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'error' => 'Service unavailable',
                'message' => 'Unable to process enrichment request',
            ], 500);
        }
    }

    private function normalizeClearbitResponse(array $data): array
    {
        return [
            'company_name' => $data['name'] ?? null,
            'website' => $data['domain'] ? "https://{$data['domain']}" : null,
            'industry' => $data['category']['industry'] ?? null,
            'company_type' => $this->normalizeCompanyType($data),
            'employees' => $data['metrics']['employees'] ?? null,
            'revenue' => $this->formatRevenue($data['metrics']['annualRevenue'] ?? null),
            'phone' => $data['phone'] ?? null,
            'email' => $data['email'] ?? null,
            'linkedin_url' => $data['linkedin']['handle'] ? "https://linkedin.com/company/{$data['linkedin']['handle']}" : null,
            'address' => $this->buildAddress($data),
            'city' => $data['geo']['city'] ?? null,
            'state' => $data['geo']['state'] ?? null,
            'postal_code' => $data['geo']['postalCode'] ?? null,
            'country' => $data['geo']['country'] ?? null,
            'timezone' => $data['timeZone'] ?? null,
        ];
    }

    private function normalizeCompanyType(array $data): ?string
    {
        $type = $data['type'] ?? null;
        
        if (!$type) {
            // Try to determine from other fields
            if (isset($data['metrics']['raised'])) {
                $type = 'Private';
            } else {
                return 'Other';
            }
        }

        // Normalize company type values to match frontend dropdown
        $type = strtolower(trim($type));
        
        switch ($type) {
            case 'private':
            case 'public':
            case 'ltd':
            case 'limited':
            case 'corp':
            case 'corporation':
                return 'Corporation';
            case 'llc':
            case 'limited liability company':
                return 'LLC';
            case 'partnership':
            case 'lp':
            case 'limited partnership':
                return 'Partnership';
            case 'non-profit':
            case 'nonprofit':
            case 'charity':
                return 'Non-Profit';
            default:
                return 'Other';
        }
    }

    private function buildAddress(array $data): ?string
    {
        $streetNumber = $data['geo']['streetNumber'] ?? null;
        $streetName = $data['geo']['streetName'] ?? null;
        
        if ($streetNumber && $streetName) {
            return "{$streetNumber} {$streetName}";
        }
        
        if ($streetName) {
            return $streetName;
        }
        
        // Return null if no address information is available
        return null;
    }

    private function formatRevenue(?int $revenue): ?string
    {
        if (!$revenue) {
            return null;
        }

        if ($revenue >= 1000000000) {
            return round($revenue / 1000000000, 1) . 'B';
        }

        if ($revenue >= 1000000) {
            return round($revenue / 1000000, 1) . 'M';
        }

        if ($revenue >= 1000) {
            return round($revenue / 1000, 1) . 'K';
        }

        return (string) $revenue;
    }

    private function getMockEnrichmentData(string $domain): array
    {
        // Extract company name from domain for mock data
        $companyName = ucfirst(explode('.', $domain)[0]);
        
        // Generate random address data for mock
        $addresses = [
            ['street' => '123 Business Street', 'city' => 'San Francisco', 'state' => 'CA', 'postal_code' => '94105'],
            ['street' => '456 Innovation Drive', 'city' => 'Austin', 'state' => 'TX', 'postal_code' => '73301'],
            ['street' => '789 Tech Boulevard', 'city' => 'Seattle', 'state' => 'WA', 'postal_code' => '98101'],
            ['street' => '321 Startup Lane', 'city' => 'New York', 'state' => 'NY', 'postal_code' => '10001'],
            ['street' => '654 Digital Way', 'city' => 'Boston', 'state' => 'MA', 'postal_code' => '02101'],
        ];
        
        $randomAddress = $addresses[array_rand($addresses)];
        
        return [
            'company_name' => $companyName,
            'website' => "https://{$domain}",
            'industry' => 'Technology',
            'company_type' => 'Corporation',
            'employees' => rand(50, 5000),
            'revenue' => rand(1, 100) . 'M',
            'phone' => '+1-555-' . str_pad(rand(100, 999), 3, '0', STR_PAD_LEFT) . '-' . str_pad(rand(1000, 9999), 4, '0', STR_PAD_LEFT),
            'email' => "info@{$domain}",
            'linkedin_url' => "https://linkedin.com/company/" . strtolower($companyName),
            'address' => $randomAddress['street'],
            'city' => $randomAddress['city'],
            'state' => $randomAddress['state'],
            'postal_code' => $randomAddress['postal_code'],
            'country' => 'USA',
            'timezone' => 'America/Los_Angeles',
        ];
    }
}
