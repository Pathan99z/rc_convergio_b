<?php

namespace App\Http\Controllers\Api\Service;

use App\Http\Controllers\Controller;
use App\Models\Service\EmailIntegration;
use App\Services\Service\EmailToTicketService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class EmailWebhookController extends Controller
{
    public function __construct(
        private EmailToTicketService $emailToTicketService
    ) {}

    /**
     * Handle incoming email webhook from email service providers.
     */
    public function handleIncomingEmail(Request $request): JsonResponse
    {
        try {
            // Log the incoming webhook for debugging
            Log::info('Email webhook received', [
                'headers' => $request->headers->all(),
                'body' => $request->all(),
            ]);

            // Parse email data based on provider
            $emailData = $this->parseEmailData($request);
            
            if (!$emailData) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid email data format',
                ], 400);
            }

            // Find the email integration
            $integration = $this->findEmailIntegration($emailData['to_email']);
            
            if (!$integration) {
                Log::warning('No email integration found', [
                    'to_email' => $emailData['to_email'],
                ]);
                
                return response()->json([
                    'success' => false,
                    'message' => 'No email integration found for this address',
                ], 404);
            }

            // Check if integration is active
            if (!$integration->is_active || !$integration->auto_create_tickets) {
                Log::info('Email integration is inactive', [
                    'integration_id' => $integration->id,
                    'is_active' => $integration->is_active,
                    'auto_create_tickets' => $integration->auto_create_tickets,
                ]);
                
                return response()->json([
                    'success' => false,
                    'message' => 'Email integration is inactive',
                ], 200);
            }

            // Process the email and create ticket
            $ticket = $this->emailToTicketService->processIncomingEmail($emailData, $integration);
            
            if (!$ticket) {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to create ticket from email',
                ], 500);
            }

            return response()->json([
                'success' => true,
                'message' => 'Ticket created successfully',
                'ticket_id' => $ticket->id,
                'integration_id' => $integration->id,
            ], 201);

        } catch (\Exception $e) {
            Log::error('Email webhook processing failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'request_data' => $request->all(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Internal server error',
            ], 500);
        }
    }

    /**
     * Parse email data from different providers.
     */
    private function parseEmailData(Request $request): ?array
    {
        // Try different email service provider formats
        
        // Gmail API format
        if ($request->has('message')) {
            return $this->parseGmailFormat($request);
        }
        
        // SendGrid format
        if ($request->has('from') && $request->has('to')) {
            return $this->parseSendGridFormat($request);
        }
        
        // Mailgun format
        if ($request->has('sender') && $request->has('recipient')) {
            return $this->parseMailgunFormat($request);
        }
        
        // Generic format
        if ($request->has('from_email') && $request->has('to_email')) {
            return $this->parseGenericFormat($request);
        }
        
        return null;
    }

    /**
     * Parse Gmail API format.
     */
    private function parseGmailFormat(Request $request): array
    {
        $message = $request->input('message');
        
        return [
            'from_email' => $message['from'] ?? '',
            'to_email' => $message['to'] ?? '',
            'subject' => $message['subject'] ?? '',
            'body' => $message['body'] ?? '',
            'message_id' => $message['message_id'] ?? null,
            'date' => $message['date'] ?? null,
            'attachments' => $message['attachments'] ?? [],
        ];
    }

    /**
     * Parse SendGrid format.
     */
    private function parseSendGridFormat(Request $request): array
    {
        return [
            'from_email' => $request->input('from'),
            'to_email' => $request->input('to'),
            'subject' => $request->input('subject'),
            'body' => $request->input('text') ?? $request->input('html'),
            'message_id' => $request->input('message_id'),
            'date' => $request->input('date'),
            'attachments' => $request->input('attachments', []),
        ];
    }

    /**
     * Parse Mailgun format.
     */
    private function parseMailgunFormat(Request $request): array
    {
        return [
            'from_email' => $request->input('sender'),
            'to_email' => $request->input('recipient'),
            'subject' => $request->input('subject'),
            'body' => $request->input('body-plain') ?? $request->input('body-html'),
            'message_id' => $request->input('Message-Id'),
            'date' => $request->input('Date'),
            'attachments' => $request->input('attachment-count', 0) > 0 ? [] : [],
        ];
    }

    /**
     * Parse generic format.
     */
    private function parseGenericFormat(Request $request): array
    {
        return [
            'from_email' => $request->input('from_email'),
            'to_email' => $request->input('to_email'),
            'subject' => $request->input('subject'),
            'body' => $request->input('body'),
            'message_id' => $request->input('message_id'),
            'date' => $request->input('date'),
            'attachments' => $request->input('attachments', []),
        ];
    }

    /**
     * Find email integration by email address.
     */
    private function findEmailIntegration(string $emailAddress): ?EmailIntegration
    {
        return EmailIntegration::where('email_address', $emailAddress)
            ->where('is_active', true)
            ->first();
    }

    /**
     * Handle Gmail push notifications.
     */
    public function handleGmailPush(Request $request): JsonResponse
    {
        try {
            // Gmail push notifications come with minimal data
            // We need to fetch the actual email content using Gmail API
            
            $data = $request->all();
            
            Log::info('Gmail push notification received', [
                'data' => $data,
            ]);
            
            // For now, return success - actual email processing would happen
            // through Gmail API polling or webhook processing
            
            return response()->json([
                'success' => true,
                'message' => 'Gmail push notification received',
            ]);

        } catch (\Exception $e) {
            Log::error('Gmail push notification failed', [
                'error' => $e->getMessage(),
                'data' => $request->all(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to process Gmail push notification',
            ], 500);
        }
    }

    /**
     * Test webhook endpoint.
     */
    public function test(Request $request): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => 'Email webhook endpoint is working',
            'timestamp' => now()->toISOString(),
            'request_data' => $request->all(),
        ]);
    }

    /**
     * Handle email service provider verification.
     */
    public function verify(Request $request): JsonResponse
    {
        // Different providers have different verification methods
        
        // SendGrid verification
        if ($request->has('sg_message_id')) {
            return response()->json(['success' => true]);
        }
        
        // Mailgun verification
        if ($request->has('signature')) {
            // Verify signature here
            return response()->json(['success' => true]);
        }
        
        // Generic verification
        return response()->json([
            'success' => true,
            'message' => 'Webhook verified',
        ]);
    }
}