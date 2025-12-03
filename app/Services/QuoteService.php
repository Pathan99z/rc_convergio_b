<?php

namespace App\Services;

use App\Models\Quote;
use App\Models\QuoteItem;
use App\Models\Deal;
use App\Models\Activity;
use App\Services\QuoteNumberGenerator;
use App\Services\ExchangeRateService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class QuoteService
{
    public function __construct(
        private QuoteNumberGenerator $quoteNumberGenerator,
        private ExchangeRateService $exchangeRateService
    ) {}

    /**
     * Create a new quote with items.
     */
    public function createQuote(array $data, int $tenantId, int $createdBy): Quote
    {
        return DB::transaction(function () use ($data, $tenantId, $createdBy) {
            // Generate unique quote number
            $quoteNumber = $this->quoteNumberGenerator->generateUnique($tenantId);

            // Determine quote currency: from deal if deal selected, otherwise from data or default
            $quoteCurrency = $this->determineQuoteCurrency($data);

            // Get contact_id from data or from deal
            $contactId = $data['contact_id'] ?? null;
            if (!$contactId && isset($data['deal_id']) && $data['deal_id']) {
                $deal = Deal::find($data['deal_id']);
                $contactId = $deal ? $deal->contact_id : null;
            }

            // Create the quote
            $quote = Quote::create([
                'quote_number' => $quoteNumber,
                'deal_id' => $data['deal_id'] ?? null,
                'contact_id' => $contactId,
                'template_id' => $data['template_id'] ?? null,
                'currency' => $quoteCurrency,
                'valid_until' => $data['valid_until'] ?? now()->addDays(30),
                'status' => $data['status'] ?? 'draft',
                'tenant_id' => $tenantId,
                'created_by' => $createdBy,
                'is_primary' => false, // Will be set to true if this is the first accepted quote
                'quote_type' => $data['quote_type'] ?? 'primary',
            ]);

            // Create quote items with currency conversion
            if (isset($data['items']) && is_array($data['items'])) {
                foreach ($data['items'] as $index => $itemData) {
                    $processedItem = $this->processQuoteItem($itemData, $tenantId, $quoteCurrency);
                    
                    QuoteItem::create([
                        'quote_id' => $quote->id,
                        'product_id' => $processedItem['product_id'] ?? null,
                        'name' => $processedItem['name'],
                        'description' => $processedItem['description'] ?? null,
                        'quantity' => $processedItem['quantity'] ?? 1,
                        'unit_price' => $processedItem['unit_price'], // Converted price
                        'original_unit_price' => $processedItem['original_unit_price'] ?? null,
                        'original_currency' => $processedItem['original_currency'] ?? null,
                        'exchange_rate_used' => $processedItem['exchange_rate_used'] ?? null,
                        'converted_at' => $processedItem['converted_at'] ?? null,
                        'discount' => $processedItem['discount'] ?? 0,
                        'tax_rate' => $processedItem['tax_rate'] ?? 0,
                        'sort_order' => $index,
                    ]);
                }
            }

            // Calculate totals
            $this->calculateTotals($quote);

            // Sync deal value if deal exists and quote total is greater
            if ($quote->deal_id) {
                $this->syncDealValue($quote);
            }

            Log::info('Quote created', [
                'quote_id' => $quote->id,
                'quote_number' => $quote->quote_number,
                'tenant_id' => $tenantId,
                'currency' => $quoteCurrency,
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
            // If quote doesn't have a deal, create one automatically when sending
            if (!$quote->deal_id) {
                $deal = $this->createDealFromQuote($quote, $contactId);
                $quote->update(['deal_id' => $deal->id]);
                $quote->refresh();
                
                Log::info('Deal created automatically when sending quote', [
                    'quote_id' => $quote->id,
                    'quote_number' => $quote->quote_number,
                    'deal_id' => $deal->id,
                    'contact_id' => $contactId,
                ]);
            }

            // Generate PDF if not exists
            // Wrap in try-catch to handle permission errors gracefully
            if (!$quote->pdf_path) {
                try {
                    $pdfPath = $this->generatePDF($quote);
                    $quote->update(['pdf_path' => $pdfPath]);
                } catch (\Exception $e) {
                    $errorMessage = $e->getMessage();
                    
                    // Check if it's a permission error and try one more time with forced temp directory
                    if (strpos($errorMessage, 'Permission denied') !== false || 
                        strpos($errorMessage, 'Failed to open stream') !== false ||
                        strpos($errorMessage, 'storage/framework/views') !== false ||
                        strpos($errorMessage, 'file_put_contents') !== false) {
                        
                        // Force use temp directory and retry
                        try {
                            $tempViewPath = sys_get_temp_dir() . '/laravel_views_' . md5(base_path());
                            if (!is_dir($tempViewPath)) {
                                @mkdir($tempViewPath, 0755, true);
                            }
                            
                            if (is_dir($tempViewPath) && is_writable($tempViewPath)) {
                                // Force config change and clear cache
                                $originalPath = config('view.compiled');
                                config(['view.compiled' => $tempViewPath]);
                                
                                try {
                                    \Illuminate\Support\Facades\Artisan::call('view:clear');
                                } catch (\Exception $clearException) {
                                    // Ignore
                                }
                                
                                // Get fresh compiler instance
                                $view = app('view');
                                $compiler = $view->getEngineResolver()->resolve('blade')->getCompiler();
                                if (method_exists($compiler, 'setCompiledPath')) {
                                    $compiler->setCompiledPath($tempViewPath);
                                }
                                
                                // Retry PDF generation
                                $pdfPath = $this->generatePDF($quote);
                                $quote->update(['pdf_path' => $pdfPath]);
                                
                                // Restore original path
                                config(['view.compiled' => $originalPath]);
                                
                                \Illuminate\Support\Facades\Log::info('Quote send: Used temp directory for PDF generation', [
                                    'quote_id' => $quote->id
                                ]);
                            } else {
                                throw $e; // Re-throw if temp directory not available
                            }
                        } catch (\Exception $e2) {
                            // If temp directory approach also fails, throw original error
                            throw new \Exception('Failed to generate PDF due to storage permission issues. Please ensure storage/framework/views is writable or contact administrator.');
                        }
                    } else {
                        // Not a permission error, re-throw original
                        throw $e;
                    }
                }
            }

            // Update quote status
            $quote->update(['status' => 'sent']);

            // Sync deal value if deal exists and quote total is greater
            if ($quote->deal_id) {
                $this->syncDealValue($quote);
            }

            // Log activity
            $this->logActivity($quote, 'sent', 'Quote sent to client');

            Log::info('Quote sent', [
                'quote_id' => $quote->id,
                'quote_number' => $quote->quote_number,
                'contact_id' => $contactId,
                'deal_id' => $quote->deal_id,
            ]);

            return $quote->fresh(['deal', 'items', 'creator']);
        });
    }

    /**
     * Create a deal automatically from a quote when sending without a deal.
     */
    private function createDealFromQuote(Quote $quote, int $contactId): Deal
    {
        // Get the contact to determine company
        $contact = \App\Models\Contact::find($contactId);
        if (!$contact) {
            throw new \Exception('Contact not found');
        }

        // Get default pipeline for tenant
        $pipeline = \App\Models\Pipeline::where('tenant_id', $quote->tenant_id)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->first();

        if (!$pipeline) {
            throw new \Exception('No active pipeline found for tenant');
        }

        // Get first active stage for the pipeline
        $stage = \App\Models\Stage::where('pipeline_id', $pipeline->id)
            ->where('tenant_id', $quote->tenant_id)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->first();

        if (!$stage) {
            throw new \Exception('No active stage found for pipeline');
        }

        // Create deal title from quote number
        $dealTitle = 'Quote ' . $quote->quote_number;

        // Create the deal
        $deal = Deal::create([
            'title' => $dealTitle,
            'description' => 'Deal created automatically from quote ' . $quote->quote_number,
            'value' => $quote->total,
            'currency' => $quote->currency,
            'status' => 'open',
            'pipeline_id' => $pipeline->id,
            'stage_id' => $stage->id,
            'owner_id' => $quote->created_by,
            'contact_id' => $contactId,
            'company_id' => $contact->company_id,
            'tenant_id' => $quote->tenant_id,
        ]);

        Log::info('Deal created from quote', [
            'deal_id' => $deal->id,
            'quote_id' => $quote->id,
            'quote_number' => $quote->quote_number,
            'contact_id' => $contactId,
            'value' => $deal->value,
            'currency' => $deal->currency,
        ]);

        return $deal;
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

            // Get the deal (if exists)
            $deal = $quote->deal;

            // Only process deal updates if deal exists
            if (!$deal) {
                // Log activity for quote
                $this->logActivity($quote, 'accepted', 'Quote accepted by client');
                return $quote->fresh(['deal', 'items', 'creator']);
            }

            // Check if this is the first accepted quote for this deal
            // Count quotes with status 'accepted' excluding the current quote
            $existingAcceptedCount = Quote::where('deal_id', $deal->id)
                ->where('status', 'accepted')
                ->where('id', '!=', $quote->id)
                ->count();
            $isFirstAcceptedQuote = $existingAcceptedCount === 0;

            if ($isFirstAcceptedQuote) {
                // This is the primary quote - mark it as primary and update deal status
                $quote->markAsPrimary();
                
                // Sync deal value with quote total (if quote total > deal value)
                $this->syncDealValueOnAccept($quote, $deal);
                
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
            $templateName = 'quotes.pdf.default'; // Default fallback
            
            // If quote has a template_id, use that template's layout
            if ($quote->template_id && $quote->template) {
                $layout = $quote->template->layout;
                $templateName = 'quotes.pdf.' . $layout;
            }
            // If no template_id but there's a default template for tenant, use it
            elseif (!$quote->template_id) {
                $defaultTemplate = \App\Models\QuoteTemplate::where('tenant_id', $quote->tenant_id)
                    ->where('is_default', true)
                    ->first();
                
                if ($defaultTemplate) {
                    $templateName = 'quotes.pdf.' . $defaultTemplate->layout;
                }
            }
            
            // Check if the specific template exists, otherwise fall back to default
            if (!view()->exists($templateName)) {
                $templateName = 'quotes.pdf.default';
            }
            
            // Try normal render() first (works fine when storage is writable - LOCAL)
            // Only use in-memory compilation when permission errors occur (SERVER)
            try {
                $html = view($templateName, compact('quote'))->render();
                $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadHTML($html);
                $pdf->setPaper('A4', 'portrait');
                $pdf->setOptions(['isRemoteEnabled' => true]);
            } catch (\Exception $e) {
                $errorMessage = $e->getMessage();
                
                // Check if error is related to storage permissions
                $isPermissionError = strpos($errorMessage, 'Permission denied') !== false || 
                    strpos($errorMessage, 'Failed to open stream') !== false ||
                    strpos($errorMessage, 'storage/framework/views') !== false ||
                    strpos($errorMessage, 'file_put_contents') !== false;
                
                if ($isPermissionError) {
                    // Permission error - try in-memory compilation (bypasses storage)
                    try {
                        $html = $this->compileBladeInMemory($templateName, compact('quote'));
                        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadHTML($html);
                        $pdf->setPaper('A4', 'portrait');
                        $pdf->setOptions(['isRemoteEnabled' => true]);
                        
                        \Illuminate\Support\Facades\Log::info('PDF generation: Used in-memory compilation due to permission error', [
                            'template' => $templateName,
                            'quote_id' => $quote->id
                        ]);
                    } catch (\Exception $e2) {
                        // In-memory also failed, try temp directory
                        try {
                            $tempViewPath = sys_get_temp_dir() . '/laravel_views_' . md5(base_path());
                            if (!is_dir($tempViewPath)) {
                                @mkdir($tempViewPath, 0755, true);
                            }
                            
                            if (is_dir($tempViewPath) && is_writable($tempViewPath)) {
                                $originalPath = config('view.compiled');
                                config(['view.compiled' => $tempViewPath]);
                                
                                try {
                                    \Illuminate\Support\Facades\Artisan::call('view:clear');
                                } catch (\Exception $clearException) {
                                    // Ignore
                                }
                                
                                $view = app('view');
                                $compiler = $view->getEngineResolver()->resolve('blade')->getCompiler();
                                if (method_exists($compiler, 'setCompiledPath')) {
                                    $compiler->setCompiledPath($tempViewPath);
                                }
                                
                                $html = view($templateName, compact('quote'))->render();
                                $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadHTML($html);
                                $pdf->setPaper('A4', 'portrait');
                                $pdf->setOptions(['isRemoteEnabled' => true]);
                                
                                config(['view.compiled' => $originalPath]);
                                
                                \Illuminate\Support\Facades\Log::info('PDF generation: Used temp directory', [
                                    'template' => $templateName,
                                    'quote_id' => $quote->id
                                ]);
                            } else {
                                throw $e2;
                            }
                        } catch (\Exception $e3) {
                            // Last resort: use loadView
                            \Illuminate\Support\Facades\Log::warning('PDF generation: All methods failed, using loadView', [
                                'error' => $e3->getMessage(),
                                'template' => $templateName,
                                'quote_id' => $quote->id
                            ]);
                            $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView($templateName, compact('quote'));
                        }
                    }
                } else {
                    // Not a permission error, use loadView
                    \Illuminate\Support\Facades\Log::warning('PDF generation: render() failed, using loadView', [
                        'error' => $errorMessage,
                        'template' => $templateName,
                        'quote_id' => $quote->id
                    ]);
                    $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView($templateName, compact('quote'));
                }
            }
            
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
     * Compile Blade template to HTML in memory without writing to storage
     * This completely bypasses storage permission issues
     * 
     * @param string $templateName The view name (e.g., 'quotes.pdf.classic')
     * @param array $data Data to pass to the view
     * @return string Compiled HTML
     * @throws \Exception If compilation fails
     */
    private function compileBladeInMemory(string $templateName, array $data): string
    {
        try {
            // Get the Blade compiler and view finder
            $view = app('view');
            $compiler = $view->getEngineResolver()->resolve('blade')->getCompiler();
            $viewFinder = $view->getFinder();
            
            // Find the Blade template file
            $path = $viewFinder->find($templateName);
            $bladeContent = file_get_contents($path);
            
            // Compile Blade to PHP code (in memory, no file write)
            $phpCode = $compiler->compileString($bladeContent);
            
            // Extract variables for the view
            extract($data);
            
            // Execute the compiled PHP code and capture output
            ob_start();
            eval('?>' . $phpCode);
            $html = ob_get_clean();
            
            return $html;
        } catch (\Exception $e) {
            // If in-memory compilation fails, throw to trigger fallback
            \Illuminate\Support\Facades\Log::warning('In-memory Blade compilation failed', [
                'template' => $templateName,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
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
     * Handles currency conversion if product currency differs from quote currency.
     */
    private function processQuoteItem(array $itemData, int $tenantId, string $quoteCurrency): array
    {
        $originalPrice = $itemData['unit_price'] ?? 0;
        $originalCurrency = $quoteCurrency;
        $exchangeRate = 1.0;
        $convertedAt = null;

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
                
                // Get product price and currency
                $productPrice = $itemData['unit_price'] ?? $product->unit_price;
                $productCurrency = $product->currency ?? 'USD';
                
                // Store original values
                $originalPrice = $productPrice;
                $originalCurrency = $productCurrency;
                
                // Convert if currencies differ
                if ($productCurrency !== $quoteCurrency) {
                    try {
                        // Get exchange rate for today
                        $today = now()->toDateString();
                        $exchangeRate = $this->exchangeRateService->getRate(
                            $productCurrency,
                            $quoteCurrency,
                            $today
                        );
                        
                        if ($exchangeRate !== null && $exchangeRate > 0) {
                            $convertedPrice = round($productPrice * $exchangeRate, 2);
                            $itemData['unit_price'] = $convertedPrice;
                            $convertedAt = now();
                            
                            Log::info('Product price converted', [
                                'product_id' => $product->id,
                                'original_price' => $productPrice,
                                'original_currency' => $productCurrency,
                                'converted_price' => $convertedPrice,
                                'quote_currency' => $quoteCurrency,
                                'exchange_rate' => $exchangeRate,
                            ]);
                        } else {
                            // Rate not found, use original price
                            Log::warning('Exchange rate not found for conversion', [
                                'product_id' => $product->id,
                                'from' => $productCurrency,
                                'to' => $quoteCurrency,
                                'date' => $today,
                            ]);
                            $itemData['unit_price'] = $productPrice;
                            $exchangeRate = 1.0;
                            $convertedAt = now();
                        }
                    } catch (\Exception $e) {
                        // If conversion fails, use original price and log error
                        Log::warning('Currency conversion failed', [
                            'product_id' => $product->id,
                            'from' => $productCurrency,
                            'to' => $quoteCurrency,
                            'error' => $e->getMessage(),
                        ]);
                        $itemData['unit_price'] = $productPrice;
                        $exchangeRate = 1.0;
                        $convertedAt = now();
                    }
                } else {
                    // Same currency, no conversion needed
                    $itemData['unit_price'] = $productPrice;
                    $exchangeRate = 1.0;
                    $convertedAt = now();
                }
                
                $itemData['tax_rate'] = $itemData['tax_rate'] ?? $product->tax_rate;
            }
        } else {
            // Manual item (no product), use provided price
            $itemData['unit_price'] = $itemData['unit_price'] ?? 0;
        }
        
        // Store audit fields
        $itemData['original_unit_price'] = $originalPrice;
        $itemData['original_currency'] = $originalCurrency;
        $itemData['exchange_rate_used'] = $exchangeRate;
        $itemData['converted_at'] = $convertedAt;
        
        return $itemData;
    }

    /**
     * Determine quote currency from deal or data.
     */
    private function determineQuoteCurrency(array $data): string
    {
        // If currency explicitly provided, use it
        if (isset($data['currency']) && !empty($data['currency'])) {
            return $data['currency'];
        }

        // If deal_id provided, get currency from deal
        if (isset($data['deal_id']) && $data['deal_id']) {
            $deal = Deal::find($data['deal_id']);
            if ($deal && $deal->currency) {
                return $deal->currency;
            }
        }

        // Default to USD
        return 'USD';
    }

    /**
     * Sync deal value with quote total (if quote total > deal value).
     */
    private function syncDealValue(Quote $quote): void
    {
        if (!$quote->deal_id) {
            return;
        }

        $deal = $quote->deal;
        
        // Convert quote total to deal currency if different
        $quoteTotalInDealCurrency = $quote->total;
        
        if ($quote->currency !== $deal->currency) {
            try {
                $quoteTotalInDealCurrency = $this->exchangeRateService->convert(
                    $quote->total,
                    $quote->currency,
                    $deal->currency
                );
            } catch (\Exception $e) {
                Log::warning('Failed to convert quote total to deal currency', [
                    'quote_id' => $quote->id,
                    'deal_id' => $deal->id,
                    'error' => $e->getMessage(),
                ]);
                return;
            }
        }

        // Only update if quote total is greater than deal value
        if ($quoteTotalInDealCurrency > $deal->value) {
            $oldValue = $deal->value;
            
            $deal->update(['value' => $quoteTotalInDealCurrency]);
            
            Log::info('Deal value synced with quote', [
                'deal_id' => $deal->id,
                'old_value' => $oldValue,
                'new_value' => $quoteTotalInDealCurrency,
                'quote_id' => $quote->id,
                'quote_total' => $quote->total,
                'quote_currency' => $quote->currency,
                'deal_currency' => $deal->currency,
            ]);
        }
    }

    /**
     * Sync deal value when quote is accepted.
     */
    private function syncDealValueOnAccept(Quote $quote, Deal $deal): void
    {
        // Convert quote total to deal currency if different
        $quoteTotalInDealCurrency = $quote->total;
        
        if ($quote->currency !== $deal->currency) {
            try {
                $quoteTotalInDealCurrency = $this->exchangeRateService->convert(
                    $quote->total,
                    $quote->currency,
                    $deal->currency
                );
            } catch (\Exception $e) {
                Log::warning('Failed to convert quote total to deal currency on accept', [
                    'quote_id' => $quote->id,
                    'deal_id' => $deal->id,
                    'error' => $e->getMessage(),
                ]);
                return;
            }
        }

        // Only update if quote total is greater than deal value (don't reduce)
        if ($quoteTotalInDealCurrency > $deal->value) {
            $oldValue = $deal->value;
            
            $deal->update(['value' => $quoteTotalInDealCurrency]);
            
            Log::info('Deal value updated on quote acceptance', [
                'deal_id' => $deal->id,
                'old_value' => $oldValue,
                'new_value' => $quoteTotalInDealCurrency,
                'quote_id' => $quote->id,
                'quote_total' => $quote->total,
                'quote_currency' => $quote->currency,
                'deal_currency' => $deal->currency,
            ]);
        } elseif ($quoteTotalInDealCurrency < $deal->value) {
            // Log warning but don't reduce deal value
            Log::info('Quote total is less than deal value, keeping deal value', [
                'deal_id' => $deal->id,
                'deal_value' => $deal->value,
                'quote_total' => $quoteTotalInDealCurrency,
                'quote_id' => $quote->id,
            ]);
        }
    }

    /**
     * Preview prices for products in target currency.
     */
    public function previewPrices(array $products, string $targetCurrency, ?int $dealId = null): array
    {
        $items = [];
        $subtotal = 0;
        $totalTax = 0;
        $totalDiscount = 0;

        foreach ($products as $productData) {
            $productId = $productData['product_id'] ?? null;
            $quantity = $productData['quantity'] ?? 1;
            $discount = $productData['discount'] ?? 0;
            
            if (!$productId) {
                continue;
            }

            $product = \App\Models\Product::find($productId);
            if (!$product || !$product->is_active) {
                continue;
            }

            $productPrice = $product->unit_price;
            $productCurrency = $product->currency ?? 'USD';
            $originalPrice = $productPrice;
            $originalCurrency = $productCurrency;
            $exchangeRate = 1.0;
            $convertedPrice = $productPrice;

            // Convert if currencies differ
            if ($productCurrency !== $targetCurrency) {
                try {
                    // Get rate for today
                    $exchangeRate = $this->exchangeRateService->getRate(
                        $productCurrency,
                        $targetCurrency,
                        now()->toDateString()
                    );
                    
                    if ($exchangeRate !== null) {
                        $convertedPrice = $productPrice * $exchangeRate;
                    } else {
                        // If rate not found, use original price and log warning
                        Log::warning('Exchange rate not found for preview', [
                            'product_id' => $productId,
                            'from' => $productCurrency,
                            'to' => $targetCurrency,
                        ]);
                        $convertedPrice = $productPrice;
                        $exchangeRate = 1.0;
                    }
                } catch (\Exception $e) {
                    Log::warning('Failed to convert product price in preview', [
                        'product_id' => $productId,
                        'from' => $productCurrency,
                        'to' => $targetCurrency,
                        'error' => $e->getMessage(),
                    ]);
                    // Use original price if conversion fails
                    $convertedPrice = $productPrice;
                    $exchangeRate = 1.0;
                }
            }

            // Calculate line totals
            $lineSubtotal = $convertedPrice * $quantity;
            $lineDiscount = $discount;
            $lineAfterDiscount = $lineSubtotal - $lineDiscount;
            $taxRate = $product->tax_rate ?? 0;
            $lineTax = $lineAfterDiscount * ($taxRate / 100);
            $lineTotal = $lineAfterDiscount + $lineTax;

            $subtotal += $lineSubtotal;
            $totalTax += $lineTax;
            $totalDiscount += $lineDiscount;

            $items[] = [
                'product_id' => $product->id,
                'product_name' => $product->name,
                'product_currency' => $productCurrency,
                'original_unit_price' => $originalPrice,
                'converted_unit_price' => $convertedPrice,
                'target_currency' => $targetCurrency,
                'exchange_rate' => $exchangeRate,
                'rate_date' => now()->toDateString(),
                'quantity' => $quantity,
                'subtotal' => $lineSubtotal,
                'discount' => $lineDiscount,
                'tax_rate' => $taxRate,
                'tax_amount' => $lineTax,
                'total' => $lineTotal,
            ];
        }

        return [
            'target_currency' => $targetCurrency,
            'deal_currency' => $dealId ? (Deal::find($dealId)->currency ?? null) : null,
            'items' => $items,
            'summary' => [
                'subtotal' => round($subtotal, 2),
                'discount' => round($totalDiscount, 2),
                'tax' => round($totalTax, 2),
                'total' => round($subtotal - $totalDiscount + $totalTax, 2),
                'currency' => $targetCurrency,
            ],
        ];
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