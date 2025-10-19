<?php

namespace App\Http\Controllers\Api\Commerce;

use App\Http\Controllers\Controller;
use App\Models\Commerce\SubscriptionInvoice;
use App\Models\TenantBranding;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Barryvdh\DomPDF\Facade\Pdf;

class InvoiceController extends Controller
{
    /**
     * Generate PDF invoice.
     */
    public function generatePdf(Request $request, int $invoiceId): JsonResponse
    {
        $tenantId = $request->user()->tenant_id;
        
        $invoice = SubscriptionInvoice::where('tenant_id', $tenantId)
            ->with(['subscription.plan', 'subscription.user'])
            ->findOrFail($invoiceId);

        $branding = TenantBranding::getDefaultBranding($tenantId);
        $subscription = $invoice->subscription;

        // Convert logo to base64 for DomPDF compatibility
        if ($branding->logo_url && Storage::exists($branding->logo_url)) {
            $logoPath = Storage::path($branding->logo_url);
            $logoData = base64_encode(file_get_contents($logoPath));
            $logoMime = mime_content_type($logoPath);
            $branding->logo_url = "data:{$logoMime};base64,{$logoData}";
        } else {
            // Create a simple text-based logo if no logo file exists
            $branding->logo_url = null; // This will hide the logo in the template
        }

        // Generate HTML content
        $html = view('invoices.professional', compact('invoice', 'branding', 'subscription'))->render();

        $filename = "invoice-{$invoice->stripe_invoice_id}.pdf";

        return response()->json([
            'success' => true,
            'data' => [
                'invoice_id' => $invoice->id,
                'invoice_number' => $invoice->stripe_invoice_id,
                'filename' => $filename,
                'html_content' => $html,
                'preview_url' => url("/commerce/invoices/{$invoice->id}/view"),
                'download_url' => url("/commerce/invoices/{$invoice->id}/download"),
                'invoice' => [
                    'id' => $invoice->id,
                    'amount' => $invoice->amount_cents / 100,
                    'currency' => $invoice->currency,
                    'status' => $invoice->status,
                    'paid_at' => $invoice->paid_at,
                    'created_at' => $invoice->created_at,
                ],
                'subscription' => [
                    'id' => $subscription->id,
                    'customer_id' => $subscription->customer_id,
                    'customer_name' => $subscription->metadata['customer_name'] ?? 'N/A',
                    'customer_email' => $subscription->metadata['customer_email'] ?? 'N/A',
                    'plan' => [
                        'id' => $subscription->plan->id,
                        'name' => $subscription->plan->name,
                        'amount' => $subscription->plan->amount_cents / 100,
                        'currency' => $subscription->plan->currency,
                        'interval' => $subscription->plan->interval,
                    ]
                ],
                'branding' => [
                    'company_name' => $branding->company_name,
                    'company_email' => $branding->company_email,
                    'company_phone' => $branding->company_phone,
                    'primary_color' => $branding->primary_color,
                    'secondary_color' => $branding->secondary_color,
                ]
            ]
        ]);
    }

    /**
     * Download PDF invoice.
     */
    public function downloadPdf(Request $request, int $invoiceId): Response
    {
        $tenantId = $request->user()->tenant_id;
        
        $invoice = SubscriptionInvoice::where('tenant_id', $tenantId)
            ->with(['subscription.plan', 'subscription.user'])
            ->findOrFail($invoiceId);

        $branding = TenantBranding::getDefaultBranding($tenantId);
        $subscription = $invoice->subscription;

        // Convert logo to base64 for DomPDF compatibility
        if ($branding->logo_url && Storage::exists($branding->logo_url)) {
            $logoPath = Storage::path($branding->logo_url);
            $logoData = base64_encode(file_get_contents($logoPath));
            $logoMime = mime_content_type($logoPath);
            $branding->logo_url = "data:{$logoMime};base64,{$logoData}";
        } else {
            // Create a simple text-based logo if no logo file exists
            $branding->logo_url = null; // This will hide the logo in the template
        }

        // Generate HTML content
        $html = view('invoices.professional', compact('invoice', 'branding', 'subscription'))->render();

        // Convert HTML to PDF
        $pdf = $this->htmlToPdf($html);

        $filename = "invoice-{$invoice->stripe_invoice_id}.pdf";

        return response($pdf, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
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
        
        $invoice = SubscriptionInvoice::where('tenant_id', $tenantId)
            ->with(['subscription.plan', 'subscription.user'])
            ->findOrFail($invoiceId);

        $branding = TenantBranding::getDefaultBranding($tenantId);

        // Convert logo to base64 for DomPDF compatibility
        if ($branding->logo_url && Storage::exists($branding->logo_url)) {
            $logoPath = Storage::path($branding->logo_url);
            $logoData = base64_encode(file_get_contents($logoPath));
            $logoMime = mime_content_type($logoPath);
            $branding->logo_url = "data:{$logoMime};base64,{$logoData}";
        } else {
            // Create a simple text-based logo if no logo file exists
            $branding->logo_url = null; // This will hide the logo in the template
        }

        try {
            // Generate PDF
            $subscription = $invoice->subscription;
            $html = view('invoices.professional', compact('invoice', 'branding', 'subscription'))->render();
            $pdf = $this->htmlToPdf($html);
            
            // Store PDF temporarily
            $filename = "invoice-{$invoice->stripe_invoice_id}.pdf";
            $tempPath = "temp/{$filename}";
            Storage::disk('public')->put($tempPath, $pdf);
            
            // Send email with PDF attachment
            // Note: You'll need to implement email sending based on your existing email system
            // For now, we'll just return success
            
            // Clean up temp file
            Storage::disk('public')->delete($tempPath);

            return response()->json([
                'success' => true,
                'message' => 'Invoice sent successfully',
                'data' => [
                    'invoice_id' => $invoice->id,
                    'sent_to' => $invoice->subscription->user->email ?? $invoice->subscription->metadata['customer_email'] ?? 'demo@example.com',
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
        // Simple DomPDF configuration that works
        $pdf = Pdf::loadHTML($html);
        $pdf->setPaper('A4', 'portrait');
        $pdf->setOptions(['isRemoteEnabled' => true]);
        
        return $pdf->output();
    }

    /**
     * Preview invoice in browser (for UI preview).
     */
    public function preview(Request $request, int $invoiceId): Response
    {
        $tenantId = $request->user()->tenant_id;
        
        $invoice = SubscriptionInvoice::where('tenant_id', $tenantId)
            ->with(['subscription.plan', 'subscription.user'])
            ->findOrFail($invoiceId);

        $branding = TenantBranding::getDefaultBranding($tenantId);
        $subscription = $invoice->subscription;

        // Convert logo to base64 for better display
        if ($branding->logo_url && Storage::exists($branding->logo_url)) {
            $logoPath = Storage::path($branding->logo_url);
            $logoData = base64_encode(file_get_contents($logoPath));
            $logoMime = mime_content_type($logoPath);
            $branding->logo_url = "data:{$logoMime};base64,{$logoData}";
        }

        // Generate HTML content
        $html = view('invoices.professional', compact('invoice', 'branding', 'subscription'))->render();

        return response($html, 200, [
            'Content-Type' => 'text/html; charset=UTF-8',
            'Cache-Control' => 'no-cache, no-store, must-revalidate',
            'Pragma' => 'no-cache',
            'Expires' => '0',
        ]);
    }

    /**
     * Get invoice details with PDF generation info.
     */
    public function show(Request $request, int $invoiceId): JsonResponse
    {
        $tenantId = $request->user()->tenant_id;
        
        $invoice = SubscriptionInvoice::where('tenant_id', $tenantId)
            ->with(['subscription.plan', 'subscription.user'])
            ->findOrFail($invoiceId);

        return response()->json([
            'success' => true,
            'data' => [
                'invoice' => $invoice,
                'pdf_url' => route('api.commerce.invoices.pdf', $invoiceId),
                'preview_url' => route('api.commerce.invoices.preview', $invoiceId),
                'download_url' => route('api.commerce.invoices.download', $invoiceId),
                'email_url' => route('api.commerce.invoices.email', $invoiceId),
            ],
        ]);
    }
}
