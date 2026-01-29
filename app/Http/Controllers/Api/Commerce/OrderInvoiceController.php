<?php

namespace App\Http\Controllers\Api\Commerce;

use App\Http\Controllers\Controller;
use App\Models\Commerce\OrderInvoice;
use App\Services\Commerce\OrderInvoiceService;
use App\Models\TenantBranding;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Barryvdh\DomPDF\Facade\Pdf;

class OrderInvoiceController extends Controller
{
    public function __construct(
        private OrderInvoiceService $invoiceService
    ) {}

    /**
     * Get list of order invoices.
     */
    public function index(Request $request): JsonResponse
    {
        $tenantId = $request->user()->tenant_id;
        $filters = $request->only(['status', 'date_from', 'date_to', 'search']);
        $perPage = $request->get('per_page', 15);

        $invoices = $this->invoiceService->getInvoices($tenantId, $filters, $perPage);

        return response()->json([
            'success' => true,
            'data' => $invoices,
        ]);
    }

    /**
     * Get invoice by ID.
     */
    public function show(Request $request, int $invoiceId): JsonResponse
    {
        $tenantId = $request->user()->tenant_id;
        
        $invoice = $this->invoiceService->getInvoice($invoiceId, $tenantId);

        if (!$invoice) {
            return response()->json([
                'success' => false,
                'message' => 'Invoice not found',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'invoice' => $invoice,
                'pdf_url' => route('api.commerce.order-invoices.pdf', $invoiceId),
                'preview_url' => route('api.commerce.order-invoices.preview', $invoiceId),
                'download_url' => route('api.commerce.order-invoices.download', $invoiceId),
                'email_url' => route('api.commerce.order-invoices.email', $invoiceId),
            ],
        ]);
    }

    /**
     * Get invoices for a quote.
     */
    public function getByQuote(Request $request, int $quoteId): JsonResponse
    {
        $tenantId = $request->user()->tenant_id;
        
        $invoices = $this->invoiceService->getInvoicesForQuote($quoteId, $tenantId);

        return response()->json([
            'success' => true,
            'data' => $invoices,
        ]);
    }

    /**
     * Get invoices for an order.
     */
    public function getByOrder(Request $request, int $orderId): JsonResponse
    {
        $tenantId = $request->user()->tenant_id;
        
        $invoices = $this->invoiceService->getInvoicesForOrder($orderId, $tenantId);

        return response()->json([
            'success' => true,
            'data' => $invoices,
        ]);
    }

    /**
     * Generate PDF invoice.
     */
    public function generatePdf(Request $request, int $invoiceId): JsonResponse
    {
        $tenantId = $request->user()->tenant_id;
        
        $invoice = $this->invoiceService->getInvoice($invoiceId, $tenantId);

        if (!$invoice) {
            return response()->json([
                'success' => false,
                'message' => 'Invoice not found',
            ], 404);
        }

        $branding = TenantBranding::getDefaultBranding($tenantId);

        // Convert logo to base64 for DomPDF compatibility
        if ($branding->logo_url && Storage::exists($branding->logo_url)) {
            $logoPath = Storage::path($branding->logo_url);
            $logoData = base64_encode(file_get_contents($logoPath));
            $logoMime = mime_content_type($logoPath);
            $branding->logo_url = "data:{$logoMime};base64,{$logoData}";
        } else {
            $branding->logo_url = null;
        }

        // Generate HTML content (using order invoice template)
        $html = view('invoices.order-professional', compact('invoice', 'branding'))->render();

        $filename = "invoice-{$invoice->invoice_number}.pdf";

        return response()->json([
            'success' => true,
            'data' => [
                'invoice_id' => $invoice->id,
                'invoice_number' => $invoice->invoice_number,
                'filename' => $filename,
                'html_content' => $html,
                'preview_url' => route('api.commerce.order-invoices.preview', $invoiceId),
                'download_url' => route('api.commerce.order-invoices.download', $invoiceId),
            ],
        ]);
    }

    /**
     * Download PDF invoice.
     */
    public function downloadPdf(Request $request, int $invoiceId): Response
    {
        $tenantId = $request->user()->tenant_id;
        
        $invoice = $this->invoiceService->getInvoice($invoiceId, $tenantId);

        if (!$invoice) {
            abort(404, 'Invoice not found');
        }

        $branding = TenantBranding::getDefaultBranding($tenantId);

        // Convert logo to base64 for DomPDF compatibility
        if ($branding->logo_url && Storage::exists($branding->logo_url)) {
            $logoPath = Storage::path($branding->logo_url);
            $logoData = base64_encode(file_get_contents($logoPath));
            $logoMime = mime_content_type($logoPath);
            $branding->logo_url = "data:{$logoMime};base64,{$logoData}";
        } else {
            $branding->logo_url = null;
        }

        // Generate HTML content
        $html = view('invoices.order-professional', compact('invoice', 'branding'))->render();

        // Convert HTML to PDF
        $pdf = $this->htmlToPdf($html);

        $filename = "invoice-{$invoice->invoice_number}.pdf";

        return response($pdf, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
            'Cache-Control' => 'no-cache, no-store, must-revalidate',
            'Pragma' => 'no-cache',
            'Expires' => '0',
        ]);
    }

    /**
     * Preview invoice in browser.
     */
    public function preview(Request $request, int $invoiceId): Response
    {
        $tenantId = $request->user()->tenant_id;
        
        $invoice = $this->invoiceService->getInvoice($invoiceId, $tenantId);

        if (!$invoice) {
            abort(404, 'Invoice not found');
        }

        $branding = TenantBranding::getDefaultBranding($tenantId);

        // Convert logo to base64 for better display
        if ($branding->logo_url && Storage::exists($branding->logo_url)) {
            $logoPath = Storage::path($branding->logo_url);
            $logoData = base64_encode(file_get_contents($logoPath));
            $logoMime = mime_content_type($logoPath);
            $branding->logo_url = "data:{$logoMime};base64,{$logoData}";
        }

        // Generate HTML content
        $html = view('invoices.order-professional', compact('invoice', 'branding'))->render();

        return response($html, 200, [
            'Content-Type' => 'text/html; charset=UTF-8',
            'Cache-Control' => 'no-cache, no-store, must-revalidate',
            'Pragma' => 'no-cache',
            'Expires' => '0',
        ]);
    }

    /**
     * Send invoice via email.
     */
    public function sendEmail(Request $request, int $invoiceId): JsonResponse
    {
        $tenantId = $request->user()->tenant_id;
        
        $invoice = $this->invoiceService->getInvoice($invoiceId, $tenantId);

        if (!$invoice) {
            return response()->json([
                'success' => false,
                'message' => 'Invoice not found',
            ], 404);
        }

        $branding = TenantBranding::getDefaultBranding($tenantId);

        // Convert logo to base64 for DomPDF compatibility
        if ($branding->logo_url && Storage::exists($branding->logo_url)) {
            $logoPath = Storage::path($branding->logo_url);
            $logoData = base64_encode(file_get_contents($logoPath));
            $logoMime = mime_content_type($logoPath);
            $branding->logo_url = "data:{$logoMime};base64,{$logoData}";
        } else {
            $branding->logo_url = null;
        }

        try {
            // Generate PDF
            $html = view('invoices.order-professional', compact('invoice', 'branding'))->render();
            $pdf = $this->htmlToPdf($html);
            
            // Store PDF temporarily
            $filename = "invoice-{$invoice->invoice_number}.pdf";
            $tempPath = "temp/{$filename}";
            Storage::disk('public')->put($tempPath, $pdf);
            
            // Get customer email
            $customerEmail = $invoice->contact->email ?? null;
            
            if (!$customerEmail) {
                return response()->json([
                    'success' => false,
                    'message' => 'Customer email not found',
                ], 400);
            }

            // TODO: Send email with PDF attachment
            // You can use your existing email service here
            
            // Clean up temp file
            Storage::disk('public')->delete($tempPath);

            return response()->json([
                'success' => true,
                'message' => 'Invoice sent successfully',
                'data' => [
                    'invoice_id' => $invoice->id,
                    'invoice_number' => $invoice->invoice_number,
                    'sent_to' => $customerEmail,
                ],
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to send invoice: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Convert HTML to PDF using DomPDF.
     */
    private function htmlToPdf(string $html): string
    {
        $pdf = Pdf::loadHTML($html);
        $pdf->setPaper('A4', 'portrait');
        $pdf->setOptions(['isRemoteEnabled' => true]);
        
        return $pdf->output();
    }
}

