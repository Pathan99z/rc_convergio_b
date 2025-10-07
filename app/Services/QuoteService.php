<?php

namespace App\Services;

use App\Models\Quote;
use App\Models\QuoteItem;
use App\Models\Deal;
use App\Models\Activity;
use App\Services\QuoteNumberGenerator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class QuoteService
{
    public function __construct(
        private QuoteNumberGenerator $quoteNumberGenerator
    ) {}

    /**
     * Create a new quote with items.
     */
    public function createQuote(array $data, int $tenantId, int $createdBy): Quote
    {
        return DB::transaction(function () use ($data, $tenantId, $createdBy) {
            // Generate unique quote number
            $quoteNumber = $this->quoteNumberGenerator->generateUnique($tenantId);

            // Create the quote
            $quote = Quote::create([
                'quote_number' => $quoteNumber,
                'deal_id' => $data['deal_id'],
                'template_id' => $data['template_id'] ?? null,
                'currency' => $data['currency'] ?? 'USD',
                'valid_until' => $data['valid_until'] ?? now()->addDays(30),
                'status' => $data['status'] ?? 'draft',
                'tenant_id' => $tenantId,
                'created_by' => $createdBy,
                'is_primary' => false, // Will be set to true if this is the first accepted quote
                'quote_type' => $data['quote_type'] ?? 'primary',
            ]);

            // Create quote items
            if (isset($data['items']) && is_array($data['items'])) {
                foreach ($data['items'] as $index => $itemData) {
                    $itemData = $this->processQuoteItem($itemData, $tenantId);
                    
                    QuoteItem::create([
                        'quote_id' => $quote->id,
                        'product_id' => $itemData['product_id'] ?? null,
                        'name' => $itemData['name'],
                        'description' => $itemData['description'] ?? null,
                        'quantity' => $itemData['quantity'] ?? 1,
                        'unit_price' => $itemData['unit_price'],
                        'discount' => $itemData['discount'] ?? 0,
                        'tax_rate' => $itemData['tax_rate'] ?? 0,
                        'sort_order' => $index,
                    ]);
                }
            }

            // Calculate totals
            $this->calculateTotals($quote);

            Log::info('Quote created', [
                'quote_id' => $quote->id,
                'quote_number' => $quote->quote_number,
                'tenant_id' => $tenantId,
            ]);

            return $quote->fresh(['deal', 'items', 'creator']);
        });
    }

    /**
     * Update an existing quote.
     */
    public function updateQuote(Quote $quote, array $data, int $tenantId): Quote
    {
        // Tenant isolation is handled by HasTenantScope trait automatically
        if (!$quote->canBeModified()) {
            throw new \Exception('Quote cannot be modified in its current status');
        }

        return DB::transaction(function () use ($quote, $data, $tenantId) {
            // Update quote basic info
            $quote->update([
                'currency' => $data['currency'] ?? $quote->currency,
                'valid_until' => $data['valid_until'] ?? $quote->valid_until,
                'quote_type' => $data['quote_type'] ?? $quote->quote_type,
            ]);

            // Update quote items if provided
            if (isset($data['items']) && is_array($data['items'])) {
                // Delete existing items
                $quote->items()->delete();

                // Create new items
                foreach ($data['items'] as $index => $itemData) {
                    QuoteItem::create([
                        'quote_id' => $quote->id,
                        'name' => $itemData['name'],
                        'description' => $itemData['description'] ?? null,
                        'quantity' => $itemData['quantity'] ?? 1,
                        'unit_price' => $itemData['unit_price'],
                        'discount' => $itemData['discount'] ?? 0,
                        'tax_rate' => $itemData['tax_rate'] ?? 0,
                        'sort_order' => $index,
                    ]);
                }
            }

            // Recalculate totals
            $this->calculateTotals($quote);

            Log::info('Quote updated', [
                'quote_id' => $quote->id,
                'quote_number' => $quote->quote_number,
                'tenant_id' => $tenantId,
            ]);

            return $quote->fresh(['deal', 'items', 'creator']);
        });
    }

    /**
     * Send a quote to a contact.
     */
    public function sendQuote(Quote $quote, int $contactId): Quote
    {
        // Tenant isolation is handled by HasTenantScope trait automatically
        if (!$quote->canBeSent()) {
            throw new \Exception('Quote cannot be sent in its current status');
        }

        return DB::transaction(function () use ($quote, $contactId) {
            // Generate PDF if not exists
            if (!$quote->pdf_path) {
                $pdfPath = $this->generatePDF($quote);
                $quote->update(['pdf_path' => $pdfPath]);
            }

            // Update quote status
            $quote->update(['status' => 'sent']);

            // Log activity
            $this->logActivity($quote, 'sent', 'Quote sent to client');

            Log::info('Quote sent', [
                'quote_id' => $quote->id,
                'quote_number' => $quote->quote_number,
                'contact_id' => $contactId,
            ]);

            return $quote->fresh(['deal', 'items', 'creator']);
        });
    }

    /**
     * Accept a quote with enhanced primary/follow-up logic.
     */
    public function acceptQuote(Quote $quote, int $tenantId): Quote
    {
        // Tenant isolation is handled by HasTenantScope trait automatically
        if (!$quote->canBeAccepted()) {
            throw new \Exception('Quote cannot be accepted in its current status');
        }

        return DB::transaction(function () use ($quote) {
            // Update quote status
            $quote->update(['status' => 'accepted']);

            // Get the deal
            $deal = $quote->deal;

            // Check if this is the first accepted quote for this deal
            $isFirstAcceptedQuote = !$deal->hasAcceptedQuotes();

            if ($isFirstAcceptedQuote) {
                // This is the primary quote - mark it as primary and update deal status
                $quote->markAsPrimary();
                
                $deal->update([
                    'status' => 'won',
                    'closed_date' => now(),
                ]);

                // Log activity for deal
                $this->logActivity($deal, 'won', 'Deal won - Primary quote accepted');
            } else {
                // This is a follow-up quote - mark it as follow-up
                $quote->markAsFollowUp($quote->quote_type);
                
                // Log activity for deal (follow-up)
                $this->logActivity($deal, 'follow_up_quote_accepted', 'Follow-up quote accepted');
            }

            // Log activity for quote
            $this->logActivity($quote, 'accepted', 'Quote accepted by client');

            Log::info('Quote accepted', [
                'quote_id' => $quote->id,
                'quote_number' => $quote->quote_number,
                'is_primary' => $quote->is_primary,
                'quote_type' => $quote->quote_type,
                'deal_id' => $deal->id,
                'is_first_accepted' => $isFirstAcceptedQuote,
            ]);

            return $quote->fresh(['deal', 'items', 'creator']);
        });
    }

    /**
     * Reject a quote.
     */
    public function rejectQuote(Quote $quote, int $tenantId): Quote
    {
        // Tenant isolation is handled by HasTenantScope trait automatically
        if (!$quote->canBeRejected()) {
            throw new \Exception('Quote cannot be rejected in its current status');
        }

        return DB::transaction(function () use ($quote) {
            // Update quote status
            $quote->update(['status' => 'rejected']);

            // Log activity
            $this->logActivity($quote, 'rejected', 'Quote rejected by client');

            Log::info('Quote rejected', [
                'quote_id' => $quote->id,
                'quote_number' => $quote->quote_number,
            ]);

            return $quote->fresh(['deal', 'items', 'creator']);
        });
    }

    /**
     * Calculate totals for a quote.
     */
    public function calculateTotals(Quote $quote): void
    {
        $subtotal = 0;
        $totalTax = 0;
        $totalDiscount = 0;

        foreach ($quote->items as $item) {
            $itemSubtotal = $item->quantity * $item->unit_price;
            $itemDiscount = $itemSubtotal * ($item->discount / 100);
            $itemAfterDiscount = $itemSubtotal - $itemDiscount;
            $itemTax = $itemAfterDiscount * ($item->tax_rate / 100);
            $itemTotal = $itemAfterDiscount + $itemTax;

            $item->update([
                'total' => $itemTotal,
            ]);

            $subtotal += $itemSubtotal;
            $totalTax += $itemTax;
            $totalDiscount += $itemDiscount;
        }

        $total = $subtotal - $totalDiscount + $totalTax;

        $quote->update([
            'subtotal' => $subtotal,
            'tax' => $totalTax,
            'discount' => $totalDiscount,
            'total' => $total,
        ]);
    }

    /**
     * Generate PDF for a quote.
     */
    public function generatePDF(Quote $quote): string
    {
        $quote->load(['deal.company', 'deal.contact', 'items', 'creator', 'template']);

        // Check if DomPDF is available
        if (class_exists('Barryvdh\DomPDF\Facade\Pdf')) {
            // Determine the template to use based on quote template or default
            $templateName = 'emails.quote-pdf'; // Default fallback
            
            if ($quote->template) {
                $templateName = 'quotes.pdf.' . $quote->template->layout;
            }
            
            // Check if the specific template exists, otherwise fall back to default
            if (!view()->exists($templateName)) {
                $templateName = 'emails.quote-pdf';
            }
            
            $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView($templateName, compact('quote'));
            $filename = 'quote-' . $quote->quote_number . '.pdf';
            $path = 'quotes/' . $filename;
            \Illuminate\Support\Facades\Storage::disk('public')->put($path, $pdf->output());
            return $path;
        } else {
            // Fallback: Create a simple text file instead of PDF
            $filename = 'quote-' . $quote->quote_number . '.txt';
            $path = 'quotes/' . $filename;
            
            $content = $this->generateTextQuote($quote);
            \Illuminate\Support\Facades\Storage::disk('public')->put($path, $content);
            
            return $path;
        }
    }
    
    private function generateTextQuote(Quote $quote): string
    {
        $content = "QUOTE #{$quote->quote_number}\n";
        $content .= "================================\n\n";
        
        if ($quote->deal && $quote->deal->contact) {
            $content .= "Client: {$quote->deal->contact->first_name} {$quote->deal->contact->last_name}\n";
            $content .= "Email: {$quote->deal->contact->email}\n";
        }
        
        if ($quote->deal && $quote->deal->company) {
            $content .= "Company: {$quote->deal->company->name}\n";
        }
        
        $content .= "\nItems:\n";
        $content .= "------\n";
        
        foreach ($quote->items as $item) {
            $content .= "• {$item->name}\n";
            $content .= "  Description: {$item->description}\n";
            $content .= "  Quantity: {$item->quantity}\n";
            $content .= "  Unit Price: $" . number_format($item->unit_price, 2) . "\n";
            $content .= "  Total: $" . number_format($item->total, 2) . "\n\n";
        }
        
        $content .= "Summary:\n";
        $content .= "--------\n";
        $content .= "Subtotal: $" . number_format($quote->subtotal, 2) . "\n";
        $content .= "Tax: $" . number_format($quote->tax, 2) . "\n";
        $content .= "Discount: $" . number_format($quote->discount, 2) . "\n";
        $content .= "Total: $" . number_format($quote->total, 2) . "\n";
        
        return $content;
    }

    /**
     * Get PDF content for download.
     */
    public function getPDFContent(Quote $quote): ?string
    {
        if (!$quote->pdf_path) {
            return null;
        }

        return \Illuminate\Support\Facades\Storage::disk('public')->get($quote->pdf_path);
    }

    /**
     * Log activity for a model - following existing patterns.
     */
    /**
     * Process quote item data, auto-filling from product if product_id is provided.
     */
    private function processQuoteItem(array $itemData, int $tenantId): array
    {
        // If product_id is provided, auto-fill from product
        if (isset($itemData['product_id']) && $itemData['product_id']) {
            $product = \App\Models\Product::where('id', $itemData['product_id'])
                ->where('tenant_id', $tenantId)
                ->where('is_active', true)
                ->first();
                
            if ($product) {
                // Auto-fill from product, but allow manual overrides
                $itemData['name'] = $itemData['name'] ?? $product->name;
                $itemData['description'] = $itemData['description'] ?? $product->description;
                $itemData['unit_price'] = $itemData['unit_price'] ?? $product->unit_price;
                $itemData['tax_rate'] = $itemData['tax_rate'] ?? $product->tax_rate;
            }
        }
        
        return $itemData;
    }

    private function logActivity($model, string $action, string $description): void
    {
        // Follow existing Activity model pattern - let HasTenantScope handle tenant isolation
        Activity::create([
            'type' => 'quote_activity',
            'subject' => get_class($model) . ' #' . $model->id,
            'description' => $description,
            'tenant_id' => $model->tenant_id,
            'owner_id' => auth()->id() ?? $model->created_by ?? 1,
        ]);
    }
}