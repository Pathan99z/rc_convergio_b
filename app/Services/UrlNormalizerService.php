<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;

class UrlNormalizerService
{
    /**
     * Normalize a URL for consistent analytics tracking.
     */
    public function normalize(string $url): string
    {
        try {
            // Parse the URL
            $parsed = parse_url($url);
            
            if (!$parsed || !isset($parsed['scheme']) || !isset($parsed['host'])) {
                Log::warning('Invalid URL provided for normalization', ['url' => $url]);
                return 'Unknown Page';
            }

            // Build normalized URL
            $normalized = $this->buildNormalizedUrl($parsed);
            
            // Validate the result
            if (!$this->isValidUrl($normalized)) {
                Log::warning('Normalized URL is invalid', [
                    'original' => $url,
                    'normalized' => $normalized
                ]);
                return 'Unknown Page';
            }

            return $normalized;

        } catch (\Exception $e) {
            Log::error('Failed to normalize URL', [
                'url' => $url,
                'error' => $e->getMessage()
            ]);
            return 'Unknown Page';
        }
    }

    /**
     * Build normalized URL from parsed components.
     */
    private function buildNormalizedUrl(array $parsed): string
    {
        // Start with scheme and lowercase host
        $normalized = $parsed['scheme'] . '://' . strtolower($parsed['host']);
        
        // Add port if present and not default
        if (isset($parsed['port'])) {
            $defaultPorts = ['http' => 80, 'https' => 443];
            if (!isset($defaultPorts[$parsed['scheme']]) || $parsed['port'] != $defaultPorts[$parsed['scheme']]) {
                $normalized .= ':' . $parsed['port'];
            }
        }
        
        // Add normalized path
        if (isset($parsed['path'])) {
            $normalized .= $this->normalizePath($parsed['path']);
        }
        
        // Add filtered query parameters (only UTM params)
        if (isset($parsed['query'])) {
            $filteredQuery = $this->filterQueryParams($parsed['query']);
            if (!empty($filteredQuery)) {
                $normalized .= '?' . $filteredQuery;
            }
        }
        
        return $normalized;
    }

    /**
     * Normalize the path component.
     */
    private function normalizePath(string $path): string
    {
        // Remove trailing slash except for root
        $path = rtrim($path, '/');
        
        // Ensure path starts with /
        if (empty($path) || $path[0] !== '/') {
            $path = '/' . $path;
        }
        
        // Decode URL encoding
        $path = urldecode($path);
        
        // Remove duplicate slashes
        $path = preg_replace('#/+#', '/', $path);
        
        return $path;
    }

    /**
     * Filter query parameters to keep only UTM and essential tracking params.
     */
    private function filterQueryParams(string $query): string
    {
        parse_str($query, $params);
        
        // Allowed parameters
        $allowedParams = [
            'utm_source',
            'utm_medium', 
            'utm_campaign',
            'utm_content',
            'utm_term',
            'utm_id',
            'gclid',        // Google Ads
            'fbclid',       // Facebook
            'msclkid',      // Microsoft
            'ref',          // Referral
            'source',       // Generic source
        ];
        
        // Filter parameters
        $filteredParams = array_intersect_key($params, array_flip($allowedParams));
        
        // Remove empty values
        $filteredParams = array_filter($filteredParams, function($value) {
            return !empty($value) && $value !== '';
        });
        
        return http_build_query($filteredParams);
    }

    /**
     * Check if a URL is valid.
     */
    private function isValidUrl(string $url): bool
    {
        return filter_var($url, FILTER_VALIDATE_URL) !== false;
    }

    /**
     * Extract domain from URL.
     */
    public function extractDomain(string $url): ?string
    {
        try {
            $parsed = parse_url($url);
            return isset($parsed['host']) ? strtolower($parsed['host']) : null;
        } catch (\Exception $e) {
            Log::error('Failed to extract domain from URL', [
                'url' => $url,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * Extract path from URL.
     */
    public function extractPath(string $url): ?string
    {
        try {
            $parsed = parse_url($url);
            return $parsed['path'] ?? '/';
        } catch (\Exception $e) {
            Log::error('Failed to extract path from URL', [
                'url' => $url,
                'error' => $e->getMessage()
            ]);
            return '/';
        }
    }

    /**
     * Check if URL is a high-value page.
     */
    public function isHighValuePage(string $url): bool
    {
        $highValuePatterns = [
            '/pricing',
            '/contact',
            '/demo',
            '/trial',
            '/signup',
            '/register',
            '/buy',
            '/purchase',
            '/checkout',
            '/download',
            '/whitepaper',
            '/case-study',
            '/product',
            '/features',
        ];
        
        $path = $this->extractPath($url);
        
        foreach ($highValuePatterns as $pattern) {
            if (strpos($path, $pattern) !== false) {
                return true;
            }
        }
        
        return false;
    }

    /**
     * Get page category based on URL.
     */
    public function getPageCategory(string $url): string
    {
        $path = $this->extractPath($url);
        
        if (strpos($path, '/pricing') !== false) return 'pricing';
        if (strpos($path, '/contact') !== false) return 'contact';
        if (strpos($path, '/demo') !== false) return 'demo';
        if (strpos($path, '/trial') !== false) return 'trial';
        if (strpos($path, '/signup') !== false || strpos($path, '/register') !== false) return 'signup';
        if (strpos($path, '/buy') !== false || strpos($path, '/purchase') !== false) return 'purchase';
        if (strpos($path, '/checkout') !== false) return 'checkout';
        if (strpos($path, '/download') !== false) return 'download';
        if (strpos($path, '/whitepaper') !== false) return 'whitepaper';
        if (strpos($path, '/case-study') !== false) return 'case-study';
        if (strpos($path, '/product') !== false) return 'product';
        if (strpos($path, '/features') !== false) return 'features';
        if (strpos($path, '/about') !== false) return 'about';
        if (strpos($path, '/blog') !== false) return 'blog';
        if (strpos($path, '/news') !== false) return 'news';
        
        return 'other';
    }

    /**
     * Batch normalize multiple URLs.
     */
    public function normalizeBatch(array $urls): array
    {
        $normalized = [];
        
        foreach ($urls as $url) {
            $normalized[] = $this->normalize($url);
        }
        
        return $normalized;
    }

    /**
     * Get URL statistics for analytics.
     */
    public function getUrlStats(array $urls): array
    {
        $stats = [
            'total_urls' => count($urls),
            'unique_domains' => 0,
            'high_value_pages' => 0,
            'categories' => [],
            'normalized_urls' => [],
        ];
        
        $domains = [];
        $categories = [];
        
        foreach ($urls as $url) {
            $normalized = $this->normalize($url);
            $stats['normalized_urls'][] = $normalized;
            
            $domain = $this->extractDomain($normalized);
            if ($domain) {
                $domains[$domain] = true;
            }
            
            if ($this->isHighValuePage($normalized)) {
                $stats['high_value_pages']++;
            }
            
            $category = $this->getPageCategory($normalized);
            $categories[$category] = ($categories[$category] ?? 0) + 1;
        }
        
        $stats['unique_domains'] = count($domains);
        $stats['categories'] = $categories;
        
        return $stats;
    }
}
