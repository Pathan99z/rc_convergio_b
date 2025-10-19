<?php

namespace App\Http\Controllers\Api\Commerce;

use App\Http\Controllers\Controller;
use App\Models\Commerce\CommercePaymentLink;
use App\Models\Quote;
use App\Services\Commerce\PaymentLinkService;
use App\Services\TeamAccessService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class CommercePaymentLinkController extends Controller
{
    public function __construct(
        private PaymentLinkService $paymentLinkService,
        private TeamAccessService $teamAccessService
    ) {}

    /**
     * Display a listing of payment links.
     */
    public function index(Request $request): JsonResponse
    {
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

        $query = CommercePaymentLink::where('tenant_id', $tenantId);

        // Apply team filtering if team access is enabled
        $this->teamAccessService->applyTeamFilter($query);

        // Status filter
        if ($status = $request->query('status')) {
            $query->withStatus($status);
        }

        // Quote filter
        if ($quoteId = $request->query('quote_id')) {
            $query->where('quote_id', $quoteId);
        }

        // Pagination
        $perPage = $request->query('per_page', 15);
        $paymentLinks = $query->with(['quote', 'team'])
            ->orderBy('created_at', 'desc')
            ->paginate($perPage);

        return response()->json([
            'success' => true,
            'message' => 'Payment links retrieved successfully',
            'data' => $paymentLinks->items(),
            'meta' => [
                'current_page' => $paymentLinks->currentPage(),
                'last_page' => $paymentLinks->lastPage(),
                'per_page' => $paymentLinks->perPage(),
                'total' => $paymentLinks->total(),
                'from' => $paymentLinks->firstItem(),
                'to' => $paymentLinks->lastItem(),
            ],
        ]);
    }

    /**
     * Store a newly created payment link.
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'quote_id' => 'required|exists:quotes,id',
            'title' => 'sometimes|string|max:255',
            'description' => 'sometimes|string|max:1000',
            'expires_at' => 'sometimes|nullable|date|after:now',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

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

        $quote = Quote::where('tenant_id', $tenantId)->findOrFail($request->quote_id);

        // Check if quote already has an active payment link
        if ($this->paymentLinkService->hasActiveLink($quote)) {
            return response()->json([
                'success' => false,
                'message' => 'Quote already has an active payment link',
            ], 409);
        }

        // Use the smart createPaymentLink method that automatically detects Stripe configuration
        $paymentLink = $this->paymentLinkService->createPaymentLink($quote, [
            'title' => $request->input('title'),
            'description' => $request->input('description'),
            'expires_at' => $request->input('expires_at'),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Payment link created successfully',
            'data' => $paymentLink->load(['quote']),
        ], 201);
    }

    /**
     * Display the specified payment link.
     */
    public function show(Request $request, int $id): JsonResponse
    {
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

        $paymentLink = CommercePaymentLink::where('tenant_id', $tenantId)
            ->with(['quote', 'team'])
            ->findOrFail($id);

        return response()->json([
            'success' => true,
            'message' => 'Payment link retrieved successfully',
            'data' => $paymentLink,
        ]);
    }

    /**
     * Display the specified payment link for public checkout (no auth required).
     */
    public function publicShow(int $id): JsonResponse
    {
        try {
            $paymentLink = CommercePaymentLink::with(['quote', 'quote.items', 'order', 'order.items'])
                ->findOrFail($id);

            // Check if payment link is active and not expired
            if ($paymentLink->status !== 'active') {
                return response()->json([
                    'success' => false,
                    'message' => 'Payment link is not active.',
                ], 400);
            }

            if ($paymentLink->expires_at && $paymentLink->expires_at->isPast()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Payment link has expired.',
                ], 400);
            }

            // Get quote or order data
            $quote = $paymentLink->quote;
            $order = $paymentLink->order;

            if (!$quote && !$order) {
                return response()->json([
                    'success' => false,
                    'message' => 'No quote or order found for this payment link.',
                ], 404);
            }

            // Prepare public checkout data (no sensitive information)
            $checkoutData = [
                'payment_link' => [
                    'id' => $paymentLink->id,
                    'status' => $paymentLink->status,
                    'expires_at' => $paymentLink->expires_at,
                    'is_test_mode' => str_contains($paymentLink->stripe_session_id, 'test_session_'),
                ],
                'quote' => $quote ? [
                    'id' => $quote->id,
                    'quote_number' => $quote->quote_number,
                    'total_amount' => $quote->total ?? $quote->total_amount,
                    'subtotal' => $quote->subtotal,
                    'tax' => $quote->tax,
                    'discount' => $quote->discount,
                    'currency' => $quote->currency,
                    'status' => $quote->status,
                    'valid_until' => $quote->valid_until,
                    'contact' => null, // Contact relationship not available in Quote model
                    'items' => $quote->items->map(function ($item) {
                        return [
                            'name' => $item->name,
                            'description' => $item->description,
                            'quantity' => $item->quantity,
                            'unit_price' => $item->unit_price,
                            'subtotal' => $item->subtotal,
                        ];
                    }),
                ] : null,
                'order' => $order ? [
                    'id' => $order->id,
                    'order_number' => $order->order_number,
                    'total_amount' => $order->total_amount,
                    'subtotal' => $order->subtotal,
                    'tax' => $order->tax,
                    'discount' => $order->discount,
                    'currency' => $order->currency,
                    'status' => $order->status,
                    'contact' => null, // Contact relationship not available in Order model
                    'items' => $order->items->map(function ($item) {
                        return [
                            'name' => $item->name,
                            'description' => $item->description,
                            'quantity' => $item->quantity,
                            'unit_price' => $item->unit_price,
                            'subtotal' => $item->subtotal,
                        ];
                    }),
                ] : null,
            ];

            return response()->json([
                'success' => true,
                'message' => 'Payment link details retrieved successfully',
                'data' => $checkoutData,
            ]);

        } catch (\Exception $e) {
            Log::error('Public payment link show error', [
                'payment_link_id' => $id,
                'error' => $e->getMessage(),
            ]);

        return response()->json([
            'success' => false,
            'message' => 'Payment link not found or error occurred.',
        ], 404);
        }
    }

    /**
     * Complete a payment link for public checkout (no auth required).
     */
    public function publicComplete(Request $request, int $id): JsonResponse
    {
        try {
            $paymentLink = CommercePaymentLink::findOrFail($id);

            // Check if payment link is active and not expired
            if ($paymentLink->status !== 'active') {
                return response()->json([
                    'success' => false,
                    'message' => 'Payment link is not active.',
                ], 400);
            }

            if ($paymentLink->expires_at && $paymentLink->expires_at->isPast()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Payment link has expired.',
                ], 400);
            }

            // Complete the payment link
            $paymentLink = $this->paymentLinkService->completeLink($paymentLink);

            // Create order from quote if payment link has a quote
            $order = null;
            if ($paymentLink->quote_id) {
                // For test mode, simulate payment details
                $paymentDetails = [
                    'method' => $paymentLink->is_test_mode ? 'test_card' : 'card',
                    'reference' => $paymentLink->is_test_mode ? 'test_' . time() : 'stripe_' . time(),
                ];
                
                $order = $this->paymentLinkService->createOrderFromQuote($paymentLink->quote, $paymentDetails);
                
                // Link the order to the payment link
                $paymentLink->update(['order_id' => $order->id]);
            }

            return response()->json([
                'success' => true,
                'message' => 'Payment completed successfully',
                'data' => [
                    'payment_link_id' => $paymentLink->id,
                    'status' => $paymentLink->status,
                    'completed_at' => $paymentLink->updated_at,
                    'order' => $order ? [
                        'id' => $order->id,
                        'order_number' => $order->order_number,
                        'total_amount' => $order->total_amount,
                        'status' => $order->status,
                    ] : null,
                ],
            ]);

        } catch (\Exception $e) {
            Log::error('Public payment completion error', [
                'payment_link_id' => $id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Payment completion failed. Please try again.',
            ], 500);
        }
    }

    /**
     * Activate a payment link.
     */
    public function activate(Request $request, int $id): JsonResponse
    {
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

        $paymentLink = CommercePaymentLink::where('tenant_id', $tenantId)->findOrFail($id);

        $paymentLink = $this->paymentLinkService->activateLink($paymentLink);

        return response()->json([
            'success' => true,
            'message' => 'Payment link activated successfully',
            'data' => $paymentLink,
        ]);
    }

    /**
     * Deactivate a payment link.
     */
    public function deactivate(Request $request, int $id): JsonResponse
    {
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

        $paymentLink = CommercePaymentLink::where('tenant_id', $tenantId)->findOrFail($id);

        $paymentLink = $this->paymentLinkService->deactivateLink($paymentLink);

        return response()->json([
            'success' => true,
            'message' => 'Payment link deactivated successfully',
            'data' => $paymentLink,
        ]);
    }

    /**
     * Complete a payment link.
     */
    public function complete(Request $request, int $id): JsonResponse
    {
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

        $paymentLink = CommercePaymentLink::where('tenant_id', $tenantId)->findOrFail($id);

        $paymentLink = $this->paymentLinkService->completeLink($paymentLink);

        return response()->json([
            'success' => true,
            'message' => 'Payment link completed successfully',
            'data' => $paymentLink,
        ]);
    }

    /**
     * Get payment link statistics.
     */
    public function stats(Request $request): JsonResponse
    {
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

        $query = CommercePaymentLink::where('tenant_id', $tenantId);

        // Apply team filtering if team access is enabled
        $this->teamAccessService->applyTeamFilter($query);

        $stats = [
            'total_links' => $query->count(),
            'active_links' => $query->clone()->active()->count(),
            'draft_links' => $query->clone()->withStatus('draft')->count(),
            'completed_links' => $query->clone()->withStatus('completed')->count(),
            'expired_links' => $query->clone()->withStatus('expired')->count(),
        ];

        return response()->json([
            'success' => true,
            'message' => 'Payment link statistics retrieved successfully',
            'data' => $stats,
        ]);
    }

    /**
     * Update the specified payment link.
     */
    public function update(Request $request, int $id): JsonResponse
    {
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

        $paymentLink = CommercePaymentLink::where('tenant_id', $tenantId)->findOrFail($id);

        // Apply team filtering if team access is enabled
        $this->teamAccessService->applyTeamFilter(CommercePaymentLink::where('tenant_id', $tenantId))->findOrFail($id);

        // Validate the request data
        $validatedData = $request->validate([
            'title' => ['sometimes', 'string', 'max:255'],
            'description' => ['sometimes', 'string', 'max:1000'],
            'status' => ['sometimes', 'string', 'in:draft,active,completed,expired,cancelled'],
            'expires_at' => ['sometimes', 'nullable', 'date', 'after:now'],
            'metadata' => ['sometimes', 'array'],
        ]);

        $paymentLink = $this->paymentLinkService->updateLink($paymentLink, $validatedData);

        return response()->json([
            'success' => true,
            'message' => 'Payment link updated successfully',
            'data' => $paymentLink,
        ]);
    }

    /**
     * Remove the specified payment link from storage.
     */
    public function destroy(Request $request, int $id): JsonResponse
    {
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

        $paymentLink = CommercePaymentLink::where('tenant_id', $tenantId)->findOrFail($id);

        // Apply team filtering if team access is enabled
        $this->teamAccessService->applyTeamFilter(CommercePaymentLink::where('tenant_id', $tenantId))->findOrFail($id);

        $this->paymentLinkService->deleteLink($paymentLink);

        return response()->json([
            'success' => true,
            'message' => 'Payment link deleted successfully',
        ]);
    }

    /**
     * Send payment link via email.
     */
    public function sendEmail(Request $request, int $id): JsonResponse
    {
        try {
            // Get tenant_id from header or use user's organization as fallback
            $tenantId = optional($request->user())->tenant_id ?? $request->user()->id;
            if ($tenantId === 0) {
                $user = $request->user();
                if ($user->organization_name === 'Globex LLC') {
                    $tenantId = 4;
                } else {
                    $tenantId = 1;
                }
            }

            $paymentLink = CommercePaymentLink::where('tenant_id', $tenantId)->findOrFail($id);

            // Apply team filtering if team access is enabled
            $this->teamAccessService->applyTeamFilter(CommercePaymentLink::where('tenant_id', $tenantId))->findOrFail($id);

            // Validate the request
            $validator = Validator::make($request->all(), [
                'customer_email' => ['required', 'email'],
                'customer_name' => ['sometimes', 'string', 'max:255'],
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors(),
                ], 422);
            }

            $customerEmail = $request->input('customer_email');
            $customerName = $request->input('customer_name');

            $emailSent = $this->paymentLinkService->sendPaymentLinkEmail(
                $paymentLink,
                $customerEmail,
                $customerName
            );

            return response()->json([
                'success' => $emailSent,
                'message' => $emailSent 
                    ? 'Payment link email sent successfully'
                    : 'Failed to send payment link email',
                'data' => [
                    'payment_link_id' => $paymentLink->id,
                    'customer_email' => $customerEmail,
                    'email_sent' => $emailSent,
                ],
            ]);

        } catch (\Exception $e) {
            Log::error('Send payment link email error', [
                'payment_link_id' => $id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to send payment link email',
            ], 500);
        }
    }

    /**
     * Send payment link to multiple recipients.
     */
    public function sendBulkEmails(Request $request, int $id): JsonResponse
    {
        try {
            // Get tenant_id from header or use user's organization as fallback
            $tenantId = optional($request->user())->tenant_id ?? $request->user()->id;
            if ($tenantId === 0) {
                $user = $request->user();
                if ($user->organization_name === 'Globex LLC') {
                    $tenantId = 4;
                } else {
                    $tenantId = 1;
                }
            }

            $paymentLink = CommercePaymentLink::where('tenant_id', $tenantId)->findOrFail($id);

            // Apply team filtering if team access is enabled
            $this->teamAccessService->applyTeamFilter(CommercePaymentLink::where('tenant_id', $tenantId))->findOrFail($id);

            // Validate the request
            $validator = Validator::make($request->all(), [
                'recipients' => ['required', 'array', 'min:1'],
                'recipients.*.email' => ['required', 'email'],
                'recipients.*.name' => ['sometimes', 'string', 'max:255'],
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors(),
                ], 422);
            }

            $recipients = $request->input('recipients');

            $results = $this->paymentLinkService->sendBulkPaymentLinkEmails(
                $paymentLink,
                $recipients
            );

            return response()->json([
                'success' => true,
                'message' => 'Bulk email sending completed',
                'data' => [
                    'payment_link_id' => $paymentLink->id,
                    'total_recipients' => count($recipients),
                    'sent' => $results['sent'],
                    'failed' => $results['failed'],
                    'errors' => $results['errors'],
                ],
            ]);

        } catch (\Exception $e) {
            Log::error('Send bulk payment link emails error', [
                'payment_link_id' => $id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to send bulk payment link emails',
            ], 500);
        }
    }

    /**
     * Create payment link and send email in one operation.
     */
    public function createAndSend(Request $request): JsonResponse
    {
        try {
            // Get tenant_id from header or use user's organization as fallback
            $tenantId = optional($request->user())->tenant_id ?? $request->user()->id;
            if ($tenantId === 0) {
                $user = $request->user();
                if ($user->organization_name === 'Globex LLC') {
                    $tenantId = 4;
                } else {
                    $tenantId = 1;
                }
            }

            // Validate the request
            $validator = Validator::make($request->all(), [
                'quote_id' => ['required', 'exists:quotes,id'],
                'customer_email' => ['required', 'email'],
                'customer_name' => ['sometimes', 'string', 'max:255'],
                'title' => ['sometimes', 'string', 'max:255'],
                'description' => ['sometimes', 'string', 'max:1000'],
                'expires_at' => ['sometimes', 'nullable', 'date', 'after:now'],
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors(),
                ], 422);
            }

            $quote = Quote::where('tenant_id', $tenantId)->findOrFail($request->input('quote_id'));

            $data = [
                'title' => $request->input('title'),
                'description' => $request->input('description'),
                'expires_at' => $request->input('expires_at'),
            ];

            $result = $this->paymentLinkService->createAndSendPaymentLink(
                $quote,
                $request->input('customer_email'),
                $request->input('customer_name'),
                $data
            );

            return response()->json([
                'success' => $result['success'],
                'message' => $result['success'] ? $result['message'] : $result['error'],
                'data' => [
                    'payment_link' => $result['payment_link'],
                    'email_sent' => $result['email_sent'],
                ],
            ]);

        } catch (\Exception $e) {
            Log::error('Create and send payment link error', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to create and send payment link',
            ], 500);
        }
    }
}
