<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class QuoteResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'quote_number' => $this->quote_number,
            'deal_id' => $this->deal_id,
            'contact_id' => $this->contact_id,
            'template_id' => $this->template_id,
            'subtotal' => $this->subtotal,
            'tax' => $this->tax,
            'discount' => $this->discount,
            'total' => $this->total,
            'currency' => $this->currency,
            'status' => $this->status,
            'valid_until' => $this->valid_until?->toISOString(),
            'pdf_path' => $this->pdf_path,
            'is_primary' => $this->is_primary,
            'quote_type' => $this->quote_type,
            'quote_type_display' => $this->quote_type_display,
            'created_at' => $this->created_at->toISOString(),
            'updated_at' => $this->updated_at->toISOString(),
            
            // Relationships
            'deal' => $this->whenLoaded('deal', function () {
                return [
                    'id' => $this->deal->id,
                    'title' => $this->deal->title ?? $this->deal->name,
                    'value' => $this->deal->value ?? $this->deal->amount,
                    'status' => $this->deal->status,
                    'stage' => $this->deal && $this->deal->stage ? [
                        'id' => $this->deal->stage->id,
                        'name' => $this->deal->stage->name,
                    ] : null,
                    'pipeline' => $this->deal && $this->deal->pipeline ? [
                        'id' => $this->deal->pipeline->id,
                        'name' => $this->deal->pipeline->name,
                    ] : null,
                    'company' => $this->deal && $this->deal->company ? [
                        'id' => $this->deal->company->id,
                        'name' => $this->deal->company->name,
                    ] : null,
                    'contact' => $this->deal && $this->deal->contact ? [
                        'id' => $this->deal->contact->id,
                        'name' => $this->deal->contact->first_name . ' ' . $this->deal->contact->last_name,
                        'email' => $this->deal->contact->email,
                        'phone' => $this->deal->contact->phone,
                    ] : null,
                    // Enhanced deal information for multiple quotes
                    'has_accepted_quotes' => $this->deal->hasAcceptedQuotes(),
                    'primary_accepted_quote' => $this->deal->getPrimaryAcceptedQuote() ? [
                        'id' => $this->deal->getPrimaryAcceptedQuote()->id,
                        'quote_number' => $this->deal->getPrimaryAcceptedQuote()->quote_number,
                        'total' => $this->deal->getPrimaryAcceptedQuote()->total,
                    ] : null,
                    'total_accepted_revenue' => $this->deal->getTotalAcceptedRevenue(),
                    'accepted_quotes_count' => $this->deal->getAcceptedQuotesCount(),
                    'follow_up_quotes_count' => $this->deal->getFollowUpQuotesCount(),
                ];
            }),
            
            'items' => $this->whenLoaded('items', function () {
                return $this->items->map(function ($item) {
                    return [
                        'id' => $item->id,
                        'product_id' => $item->product_id,
                        'name' => $item->name,
                        'description' => $item->description,
                        'quantity' => $item->quantity,
                        'unit_price' => $item->unit_price,
                        'discount' => $item->discount,
                        'tax_rate' => $item->tax_rate,
                        'total' => $item->total,
                        'subtotal' => $item->subtotal,
                        'discounted_amount' => $item->discounted_amount,
                        'tax_amount' => $item->tax_amount,
                        'sort_order' => $item->sort_order,
                        'product' => $item->product ? [
                            'id' => $item->product->id,
                            'name' => $item->product->name,
                            'sku' => $item->product->sku,
                            'unit_price' => $item->product->unit_price,
                            'tax_rate' => $item->product->tax_rate,
                        ] : null,
                    ];
                });
            }),
            
            'creator' => $this->whenLoaded('creator', function () {
                return [
                    'id' => $this->creator->id,
                    'name' => $this->creator->name,
                    'email' => $this->creator->email,
                ];
            }),
            
            'template' => $this->whenLoaded('template', function () {
                return [
                    'id' => $this->template->id,
                    'name' => $this->template->name,
                    'layout' => $this->template->layout,
                    'layout_display' => $this->template->layout_display,
                ];
            }),
            
            'contact' => $this->whenLoaded('contact', function () {
                return [
                    'id' => $this->contact->id,
                    'first_name' => $this->contact->first_name,
                    'last_name' => $this->contact->last_name,
                    'name' => $this->contact->first_name . ' ' . $this->contact->last_name,
                    'email' => $this->contact->email,
                    'phone' => $this->contact->phone,
                ];
            }),
            
            // Computed attributes
            'is_expired' => $this->isExpired(),
            'can_be_modified' => $this->canBeModified(),
            'can_be_sent' => $this->canBeSent(),
            'can_be_accepted' => $this->canBeAccepted(),
            'can_be_rejected' => $this->canBeRejected(),
            
            // Enhanced computed attributes for multiple quotes
            'is_primary_quote' => $this->isPrimary(),
            'is_follow_up_quote' => $this->isFollowUp(),
        ];
    }
}