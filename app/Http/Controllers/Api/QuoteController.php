<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Quotes\StoreQuoteRequest;
use App\Http\Requests\Quotes\UpdateQuoteRequest;
use App\Http\Resources\QuoteResource;
use App\Models\Quote;
use App\Models\Contact;
use App\Services\QuoteService;
use App\Services\QuoteMailer;
use App\Services\TeamAccessService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class QuoteController extends Controller
{
    public function __construct(
        private QuoteService $quoteService,
        private QuoteMailer $quoteMailer,
        private TeamAccessService $teamAccessService
    ) {}

    /**
     * Display a listing of quotes.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Quote::class);

        // Get tenant_id from header or use user's organization as fallback
        $tenantId = optional($request->user())->tenant_id ?? $request->user()->id;
        if ($tenantId === 0) {
            // Use organization_name to determine tenant_id
            $user = $request->user();
            if ($user->organization_name === 'Globex LLC') {
                $tenantId = 4; // chitti's organization
            } else {
                $tenantId = 1; // default tenant
            }
        }
        $userId = $request->user()->id;

        $query = Quote::query()->where('tenant_id', $tenantId);

        // Apply creator-based filtering (quotes are creator-specific)
        $query->where('created_by', $userId);
        
        // Apply team filtering if team access is enabled
        $this->teamAccessService->applyTeamFilter($query);

        // Apply filters
        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }
        if ($dealId = $request->query('deal_id')) {
            $query->where('deal_id', $dealId);
        }
        if ($createdBy = $request->query('created_by')) {
            $query->where('created_by', $createdBy);
        }
        if ($fromDate = $request->query('date_from')) {
            $query->whereDate('created_at', '>=', $fromDate);
        }
        if ($toDate = $request->query('date_to')) {
            $query->whereDate('created_at', '<=', $toDate);
        }

        // Apply search
        if ($search = $request->query('q')) {
            $query->where(function ($q) use ($search) {
                $q->where('quote_number', 'like', "%{$search}%")
                  ->orWhereHas('deal', function ($dealQuery) use ($search) {
                      $dealQuery->where('title', 'like', "%{$search}%");
                  })
                  ->orWhereHas('deal.contact', function ($contactQuery) use ($search) {
                      $contactQuery->where('first_name', 'like', "%{$search}%")
                                   ->orWhere('last_name', 'like', "%{$search}%")
                                   ->orWhere('email', 'like', "%{$search}%");
                  });
            });
        }

        // Apply sorting
        $sortBy = $request->query('sortBy', 'created_at');
        $sortOrder = $request->query('sortOrder', 'desc');
        $query->orderBy($sortBy, $sortOrder);

        // Load relationships
        $query->with(['deal', 'items', 'creator']);

        // Paginate
        $perPage = $request->query('per_page', 15);
        $quotes = $query->paginate($perPage);

        return QuoteResource::collection($quotes);
    }

    /**
     * Store a newly created quote.
     */
    public function store(StoreQuoteRequest $request): QuoteResource
    {
        $this->authorize('create', Quote::class);
        
        $data = $request->validated();
        $tenantId = $request->user()->id;
        $createdBy = $request->user()->id;

        $quote = $this->quoteService->createQuote($data, $tenantId, $createdBy);

        return new QuoteResource($quote);
    }

    /**
     * Display the specified quote.
     */
    public function show(Request $request, Quote $quote): QuoteResource
    {
        $this->authorize('view', $quote);
        $quote->load(['deal.company', 'deal.contact', 'deal.stage', 'deal.pipeline', 'items', 'creator']);
        
        // Get linked documents for this quote
        $user = $request->user();
        $tenantId = $user ? ($user->tenant_id ?? $user->id) : 1;
        
        // Get linked documents for this quote using the new relationship approach
        $documentIds = \App\Models\DocumentRelationship::where('tenant_id', $tenantId)
            ->where('related_type', 'App\\Models\\Quote')
            ->where('related_id', $quote->id)
            ->pluck('document_id');
            
        $documents = \App\Models\Document::where('tenant_id', $tenantId)
            ->whereIn('id', $documentIds)
            ->whereNull('deleted_at')
            ->get();

        $resource = new QuoteResource($quote);
        $resource->additional(['documents' => $documents]);
        
        return $resource;
    }

    /**
     * Update the specified quote.
     */
    public function update(UpdateQuoteRequest $request, Quote $quote): QuoteResource
    {
        $this->authorize('update', $quote);
        $data = $request->validated();
        $tenantId = $request->user()->id;

        $quote = $this->quoteService->updateQuote($quote, $data, $tenantId);

        return new QuoteResource($quote);
    }

    /**
     * Remove the specified quote.
     */
    public function destroy(Quote $quote): JsonResponse
    {
        $this->authorize('delete', $quote);
        $quote->delete();

        return response()->json(['message' => 'Quote deleted successfully']);
    }

    /**
     * Send a quote to a contact.
     */
    public function send(Request $request, Quote $quote): JsonResponse
    {
        $this->authorize('update', $quote);
        $validated = $request->validate([
            'contact_id' => ['required', 'exists:contacts,id'],
            'custom_message' => ['nullable', 'string', 'max:1000'],
        ]);
        $tenantId = $request->user()->id;

        try {
            // Get the contact first
            $contact = Contact::where('id', $request->contact_id)
                ->where('tenant_id', $tenantId)
                ->firstOrFail();

            // Send the quote
            $quote = $this->quoteService->sendQuote($quote, $contact->id);

            // Send email
            $success = $this->quoteMailer->sendQuoteEmail(
                $quote, 
                $contact, 
                $validated['custom_message'] ?? null
            );

            if ($success) {
                return response()->json([
                    'message' => 'Quote sent successfully',
                    'quote' => new QuoteResource($quote)
                ]);
            } else {
                return response()->json([
                    'message' => 'Quote sent but email delivery failed',
                    'quote' => new QuoteResource($quote)
                ], 202);
            }

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to send quote: ' . $e->getMessage()
            ], 422);
        }
    }

    /**
     * Accept a quote.
     */
    public function accept(Request $request, Quote $quote): JsonResponse
    {
        $this->authorize('update', $quote);
        $tenantId = $request->user()->id;

        try {
            $quote = $this->quoteService->acceptQuote($quote, $tenantId);

            return response()->json([
                'message' => 'Quote accepted successfully',
                'quote' => new QuoteResource($quote)
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to accept quote: ' . $e->getMessage()
            ], 422);
        }
    }

    /**
     * Reject a quote.
     */
    public function reject(Request $request, Quote $quote): JsonResponse
    {
        $this->authorize('update', $quote);
        $tenantId = $request->user()->id;

        try {
            $quote = $this->quoteService->rejectQuote($quote, $tenantId);

            return response()->json([
                'message' => 'Quote rejected successfully',
                'quote' => new QuoteResource($quote)
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to reject quote: ' . $e->getMessage()
            ], 422);
        }
    }

    /**
     * Preview prices for products in target currency.
     */
    public function previewPrices(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'products' => ['required', 'array', 'min:1'],
            'products.*.product_id' => ['required', 'integer', 'exists:products,id'],
            'products.*.quantity' => ['nullable', 'integer', 'min:1'],
            'products.*.discount' => ['nullable', 'numeric', 'min:0'],
            'target_currency' => ['required', 'string', 'size:3'],
            'deal_id' => ['nullable', 'integer', 'exists:deals,id'],
        ]);

        $tenantId = $request->user()->id;
        
        // Verify products belong to tenant
        $productIds = collect($validated['products'])->pluck('product_id');
        $productsCount = \App\Models\Product::where('tenant_id', $tenantId)
            ->whereIn('id', $productIds)
            ->count();
            
        if ($productsCount !== count($productIds)) {
            return response()->json([
                'message' => 'Some products not found or do not belong to your organization'
            ], 422);
        }

        try {
            $preview = $this->quoteService->previewPrices(
                $validated['products'],
                $validated['target_currency'],
                $validated['deal_id'] ?? null
            );

            return response()->json([
                'success' => true,
                'data' => $preview
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to preview prices: ' . $e->getMessage()
            ], 422);
        }
    }

    /**
     * Get deals for a customer.
     */
    public function getCustomerDeals(Request $request, int $contactId): JsonResponse
    {
        $tenantId = $request->user()->id;
        
        // Verify contact belongs to tenant
        $contact = \App\Models\Contact::where('id', $contactId)
            ->where('tenant_id', $tenantId)
            ->firstOrFail();

        $deals = \App\Models\Deal::where('contact_id', $contactId)
            ->where('tenant_id', $tenantId)
            ->whereIn('status', ['open', 'qualified', 'proposal'])
            ->with(['pipeline', 'stage', 'owner'])
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $deals->map(function ($deal) {
                return [
                    'id' => $deal->id,
                    'title' => $deal->title,
                    'value' => $deal->value,
                    'currency' => $deal->currency,
                    'status' => $deal->status,
                    'pipeline' => $deal->pipeline ? $deal->pipeline->name : null,
                    'stage' => $deal->stage ? $deal->stage->name : null,
                ];
            })
        ]);
    }

    /**
     * Download quote PDF.
     */
    public function pdf(Quote $quote): Response|JsonResponse
    {
        $this->authorize('view', $quote);

        try {
            // Check if PDF exists in storage, regenerate if missing
            // This ensures PDFs are always available and include latest branding
            $needsRegeneration = false;
            
            if (!$quote->pdf_path) {
                $needsRegeneration = true;
            } else {
                // Verify the file actually exists in storage
                if (!\Illuminate\Support\Facades\Storage::disk('public')->exists($quote->pdf_path)) {
                    $needsRegeneration = true;
                }
            }
            
            if ($needsRegeneration) {
                $pdfPath = $this->quoteService->generatePDF($quote);
                $quote->update(['pdf_path' => $pdfPath]);
            }

            $pdfContent = $this->quoteService->getPDFContent($quote);

            if (!$pdfContent) {
                return response()->json(['message' => 'Failed to generate PDF'], 500);
            }

            // Default to 'attachment' for downloads, but allow 'inline' if view parameter is set
            // This ensures proper download behavior while maintaining flexibility
            $disposition = request()->has('view') ? 'inline' : 'attachment';
            $filename = 'quote-' . $quote->quote_number . '.pdf';

            return response($pdfContent, 200, [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => $disposition . '; filename="' . $filename . '"'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to generate PDF: ' . $e->getMessage()
            ], 500);
        }
    }
}