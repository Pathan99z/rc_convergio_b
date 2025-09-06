<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Contact;
use App\Models\Company;
// Deal model may be optional in some deployments; use FQN conditionally
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SearchController extends Controller
{
    /**
     * Global search across contacts, companies, and deals.
     */
    public function search(Request $request): JsonResponse
    {
        $query = $request->get('q', '');
        $types = $request->get('types', 'contacts,companies,deals');
        $types = explode(',', $types);
        $tenantId = $request->user()->id; // Use authenticated user as tenant
        $limit = min((int) $request->get('limit', 10), 50);

        $results = [];

        if (in_array('contacts', $types)) {
            $contacts = Contact::where('tenant_id', $tenantId)
                ->where(function ($q) use ($query) {
                    $q->where('first_name', 'like', "%{$query}%")
                      ->orWhere('last_name', 'like', "%{$query}%")
                      ->orWhere('email', 'like', "%{$query}%")
                      ->orWhere('phone', 'like', "%{$query}%");
                })
                ->with(['company:id,name', 'owner:id,name'])
                ->limit($limit)
                ->get()
                ->map(function ($contact) use ($query) {
                    return [
                        'id' => $contact->id,
                        'type' => 'contact',
                        'title' => $contact->first_name . ' ' . $contact->last_name,
                        'subtitle' => $contact->email,
                        'company' => $contact->company?->name,
                        'owner' => $contact->owner?->name,
                        'created_at' => $contact->created_at,
                        'highlight' => $this->highlightText($contact->first_name . ' ' . $contact->last_name . ' ' . $contact->email, $query),
                    ];
                });

            $results['contacts'] = $contacts;
        }

        if (in_array('companies', $types)) {
            $companies = Company::where('tenant_id', $tenantId)
                ->where(function ($q) use ($query) {
                    $q->where('name', 'like', "%{$query}%")
                      ->orWhere('domain', 'like', "%{$query}%")
                      ->orWhere('website', 'like', "%{$query}%")
                      ->orWhere('industry', 'like', "%{$query}%");
                })
                ->with(['owner:id,name'])
                ->limit($limit)
                ->get()
                ->map(function ($company) use ($query) {
                    return [
                        'id' => $company->id,
                        'type' => 'company',
                        'title' => $company->name,
                        'subtitle' => $company->industry,
                        'domain' => $company->domain,
                        'owner' => $company->owner?->name,
                        'created_at' => $company->created_at,
                        'highlight' => $this->highlightText($company->name . ' ' . $company->industry . ' ' . $company->domain, $query),
                    ];
                });

            $results['companies'] = $companies;
        }

        if (in_array('deals', $types) && class_exists(\App\Models\Deal::class)) {
            $deals = \App\Models\Deal::where('tenant_id', $tenantId)
                ->where(function ($q) use ($query) {
                    $q->where('title', 'like', "%{$query}%")
                      ->orWhere('description', 'like', "%{$query}%");
                })
                ->with(['company:id,name', 'owner:id,name', 'stage:id,name'])
                ->limit($limit)
                ->get()
                ->map(function ($deal) use ($query) {
                    return [
                        'id' => $deal->id,
                        'type' => 'deal',
                        'title' => $deal->title,
                        'subtitle' => $deal->company?->name,
                        'value' => $deal->value,
                        'stage' => $deal->stage?->name,
                        'owner' => $deal->owner?->name,
                        'created_at' => $deal->created_at,
                        'highlight' => $this->highlightText($deal->title . ' ' . $deal->description, $query),
                    ];
                });

            $results['deals'] = $deals;
        } else {
            // Ensure key exists even when Deal model is unavailable
            $results['deals'] = collect();
        }

        return response()->json([
            'data' => $results,
            'meta' => [
                'query' => $query,
                'types' => $types,
                'total_results' => array_sum(array_map('count', $results)),
            ],
        ]);
    }

    /**
     * Highlight search terms in text.
     */
    private function highlightText(string $text, string $query): string
    {
        if (empty($query)) {
            return $text;
        }

        $highlighted = preg_replace(
            '/(' . preg_quote($query, '/') . ')/i',
            '<mark>$1</mark>',
            $text
        );

        return $highlighted;
    }
}






