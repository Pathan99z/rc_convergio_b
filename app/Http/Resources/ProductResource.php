<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        // Get currency (default to ZAR for PayFast compatibility)
        $currency = $this->currency ?? 'ZAR';
        
        // Map currency code to symbol
        $currencySymbol = match(strtoupper($currency)) {
            'ZAR' => 'R',
            'USD' => '$',
            'EUR' => '€',
            'GBP' => '£',
            default => $currency, // Use currency code if symbol not mapped
        };
        
        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'sku' => $this->sku,
            'unit_price' => $this->unit_price,
            'currency' => $currency, // Include currency in API response
            'tax_rate' => $this->tax_rate,
            'is_active' => $this->is_active,
            'created_at' => $this->created_at->toISOString(),
            'updated_at' => $this->updated_at->toISOString(),
            
            // Relationships
            'creator' => $this->whenLoaded('creator', function () {
                return [
                    'id' => $this->creator->id,
                    'name' => $this->creator->name,
                    'email' => $this->creator->email,
                ];
            }),
            
            // Computed attributes
            'formatted_price' => $currencySymbol . ' ' . number_format($this->unit_price, 2), // Currency-aware formatting
            'formatted_tax_rate' => $this->tax_rate . '%',
        ];
    }
}
