<?php

namespace App\Http\Middleware;

use App\Models\Cms\Domain;
use App\Models\Cms\Language;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class DetectDomainAndLanguage
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Detect domain
        $host = $request->getHost();
        $domain = Domain::where('name', $host)->active()->first();
        
        if (!$domain) {
            $domain = Domain::where('is_primary', true)->active()->first();
        }

        // Store domain in request for later use
        $request->attributes->set('cms_domain', $domain);

        // Detect language from URL path or Accept-Language header
        $language = $this->detectLanguage($request);
        $request->attributes->set('cms_language', $language);

        // Add headers for frontend
        $response = $next($request);
        
        if ($domain) {
            $response->headers->set('X-CMS-Domain', $domain->name);
        }
        
        if ($language) {
            $response->headers->set('X-CMS-Language', $language->code);
        }

        return $response;
    }

    /**
     * Detect language from request.
     */
    protected function detectLanguage(Request $request): ?Language
    {
        // 1. Try to get language from URL path (e.g., /es/page-slug)
        $pathSegments = explode('/', trim($request->getPathInfo(), '/'));
        if (!empty($pathSegments[0]) && strlen($pathSegments[0]) <= 3) {
            $language = Language::where('code', $pathSegments[0])->active()->first();
            if ($language) {
                return $language;
            }
        }

        // 2. Try to get language from query parameter
        if ($request->has('lang')) {
            $language = Language::where('code', $request->lang)->active()->first();
            if ($language) {
                return $language;
            }
        }

        // 3. Try to get language from Accept-Language header
        $acceptLanguage = $request->header('Accept-Language');
        if ($acceptLanguage) {
            $preferredLanguages = $this->parseAcceptLanguage($acceptLanguage);
            foreach ($preferredLanguages as $langCode) {
                $language = Language::where('code', $langCode)->active()->first();
                if ($language) {
                    return $language;
                }
            }
        }

        // 4. Fall back to default language
        return Language::where('is_default', true)->active()->first();
    }

    /**
     * Parse Accept-Language header.
     */
    protected function parseAcceptLanguage(string $acceptLanguage): array
    {
        $languages = [];
        $parts = explode(',', $acceptLanguage);
        
        foreach ($parts as $part) {
            $part = trim($part);
            if (preg_match('/^([a-z]{2}(-[A-Z]{2})?)(;q=([0-9.]+))?$/', $part, $matches)) {
                $langCode = substr($matches[1], 0, 2); // Get just the language code (e.g., 'en' from 'en-US')
                $languages[] = $langCode;
            }
        }
        
        return array_unique($languages);
    }
}



