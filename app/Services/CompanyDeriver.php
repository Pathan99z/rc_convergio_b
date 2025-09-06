<?php

namespace App\Services;

class CompanyDeriver
{
    /**
     * Derive normalized domain and name from payload and form context.
     */
    public function derive(array $payload): array
    {
        $email = $payload['email'] ?? $payload['email_address'] ?? null;
        $rawCompany = $payload['company'] ?? $payload['company_name'] ?? null;

        $domain = $this->extractDomainFromEmail($email);
        $normalizedDomain = $domain ? strtolower($this->stripWww($domain)) : null;

        $name = null;
        if (is_string($rawCompany)) {
            $trim = trim($rawCompany);
            if ($trim !== '') {
                $name = $trim;
            }
        }
        if ($name === null && $normalizedDomain) {
            $name = $this->prettifyDomain($normalizedDomain);
        }
        if ($name === null || $name === '') {
            $name = 'Unknown Company';
        }

        return [
            'normalized_domain' => $normalizedDomain,
            'normalized_name' => $name,
        ];
    }

    private function extractDomainFromEmail(?string $email): ?string
    {
        if (!$email || !str_contains($email, '@')) {
            return null;
        }
        [$local, $host] = explode('@', $email, 2);
        return $host ?: null;
    }

    private function stripWww(string $domain): string
    {
        return preg_replace('/^www\./i', '', $domain) ?: $domain;
    }

    private function prettifyDomain(string $domain): string
    {
        // Take last two labels if possible: sub.acme.com -> acme.com -> Acme
        $parts = explode('.', $domain);
        if (count($parts) >= 2) {
            $base = $parts[count($parts) - 2];
        } else {
            $base = $parts[0];
        }
        $base = preg_replace('/[^a-z0-9]+/i', '', $base) ?: $domain;
        return ucfirst($base);
    }
}


