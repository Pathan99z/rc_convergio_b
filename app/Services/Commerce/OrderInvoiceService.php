<?php

namespace App\Services\Commerce;

use App\Models\Commerce\OrderInvoice;
use App\Models\Commerce\OrderInvoiceItem;
use App\Models\Commerce\CommerceOrder;
use App\Models\Quote;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class OrderInvoiceService
{
    /**
     * Create an invoice from a quote and order after payment.
     */
    public function createFromQuoteAndOrder(
        Quote $quote,
        CommerceOrder $order,
        array $paymentDetails = []
    ): OrderInvoice {
        return DB::transaction(function () use ($quote, $order, $paymentDetails) {
            // Generate invoice number
            $invoiceNumber = OrderInvoice::generateInvoiceNumber($quote->tenant_id);
            
            // Get payment details
            $paymentMethod = $paymentDetails['method'] ?? $order->payment_method ?? 'payfast';
            $paymentReference = $paymentDetails['reference'] ?? $order->payment_reference ?? null;
            
            // Create invoice
            $invoice = OrderInvoice::create([
                'tenant_id' => $quote->tenant_id,
                'quote_id' => $quote->id,
                'order_id' => $order->id,
                'contact_id' => $quote->contact_id ?? $order->contact_id,
                'deal_id' => $quote->deal_id ?? $order->deal_id,
                'invoice_number' => $invoiceNumber,
                'invoice_date' => Carbon::now(),
                'due_date' => Carbon::now(), // Same as invoice date for paid invoices
                'subtotal' => $quote->subtotal ?? $order->subtotal ?? 0,
                'tax' => $quote->tax ?? $order->tax ?? 0,
                'discount' => $quote->discount ?? $order->discount ?? 0,
                'total' => $quote->total ?? $order->total_amount ?? 0,
                'currency' => $quote->currency ?? $order->currency ?? 'ZAR',
                'status' => 'paid', // Payment already completed
                'payment_method' => $paymentMethod,
                'payment_reference' => $paymentReference,
                'paid_at' => Carbon::now(),
                'raw_payload' => $paymentDetails['raw_payload'] ?? null,
            ]);

            // Create invoice items from quote items
            $this->createInvoiceItemsFromQuote($invoice, $quote);

            Log::channel('commerce')->info('Order invoice created from quote and order', [
                'invoice_id' => $invoice->id,
                'invoice_number' => $invoice->invoice_number,
                'quote_id' => $quote->id,
                'order_id' => $order->id,
                'total' => $invoice->total,
                'currency' => $invoice->currency,
                'tenant_id' => $invoice->tenant_id,
            ]);

            return $invoice->fresh(['items']);
        });
    }

    /**
     * Create invoice items from quote items.
     */
    private function createInvoiceItemsFromQuote(OrderInvoice $invoice, Quote $quote): void
    {
        $sortOrder = 0;
        
        foreach ($quote->items as $quoteItem) {
            // Calculate line item totals
            $subtotal = $quoteItem->quantity * $quoteItem->unit_price;
            $discountedAmount = $subtotal - ($quoteItem->discount ?? 0);
            $taxAmount = $discountedAmount * (($quoteItem->tax_rate ?? 0) / 100);
            $lineTotal = $discountedAmount + $taxAmount;

            OrderInvoiceItem::create([
                'invoice_id' => $invoice->id,
                'product_id' => $quoteItem->product_id,
                'name' => $quoteItem->name,
                'description' => $quoteItem->description,
                'quantity' => $quoteItem->quantity,
                'unit_price' => $quoteItem->unit_price,
                'discount' => $quoteItem->discount ?? 0,
                'tax_rate' => $quoteItem->tax_rate ?? 0,
                'tax_amount' => $taxAmount,
                'line_total' => $lineTotal,
                'sort_order' => $sortOrder++,
            ]);
        }
    }

    /**
     * Get invoice by ID with relationships.
     */
    public function getInvoice(int $invoiceId, int $tenantId): ?OrderInvoice
    {
        return OrderInvoice::where('tenant_id', $tenantId)
            ->with(['quote', 'order', 'contact', 'deal', 'items.product'])
            ->find($invoiceId);
    }

    /**
     * Get invoices for a quote.
     */
    public function getInvoicesForQuote(int $quoteId, int $tenantId)
    {
        return OrderInvoice::where('tenant_id', $tenantId)
            ->where('quote_id', $quoteId)
            ->with(['order', 'contact', 'deal', 'items'])
            ->orderBy('created_at', 'desc')
            ->get();
    }

    /**
     * Get invoices for an order.
     */
    public function getInvoicesForOrder(int $orderId, int $tenantId)
    {
        return OrderInvoice::where('tenant_id', $tenantId)
            ->where('order_id', $orderId)
            ->with(['quote', 'contact', 'deal', 'items'])
            ->orderBy('created_at', 'desc')
            ->get();
    }

    /**
     * Get all invoices for a tenant with pagination.
     */
    public function getInvoices(int $tenantId, array $filters = [], int $perPage = 15)
    {
        $query = OrderInvoice::where('tenant_id', $tenantId)
            ->with(['quote', 'order', 'contact', 'deal', 'items']);

        // Apply filters
        if (isset($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (isset($filters['date_from'])) {
            $query->where('invoice_date', '>=', $filters['date_from']);
        }

        if (isset($filters['date_to'])) {
            $query->where('invoice_date', '<=', $filters['date_to']);
        }

        if (isset($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('invoice_number', 'like', "%{$search}%")
                  ->orWhereHas('contact', function ($q) use ($search) {
                      $q->where('first_name', 'like', "%{$search}%")
                        ->orWhere('last_name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                  });
            });
        }

        return $query->orderBy('created_at', 'desc')->paginate($perPage);
    }

    /**
     * Update invoice PDF path.
     */
    public function updatePdfPath(int $invoiceId, string $pdfPath): OrderInvoice
    {
        $invoice = OrderInvoice::findOrFail($invoiceId);
        $invoice->update(['pdf_path' => $pdfPath]);

        return $invoice->fresh();
    }
}

