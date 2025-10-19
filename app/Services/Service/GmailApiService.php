<?php

namespace App\Services\Service;

use App\Models\Service\EmailIntegration;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GmailApiService
{
    private string $baseUrl = 'https://gmail.googleapis.com/gmail/v1';

    /**
     * Monitor Gmail inbox for new emails.
     */
    public function monitorInbox(EmailIntegration $integration): array
    {
        try {
            if (!$integration->credentials || !$integration->is_active) {
                return [
                    'success' => false,
                    'message' => 'Integration not properly configured',
                ];
            }

            $credentials = $integration->credentials;
            $accessToken = $credentials['access_token'] ?? null;

            if (!$accessToken) {
                return [
                    'success' => false,
                    'message' => 'No access token available',
                ];
            }

            // Get list of messages
            $messages = $this->getMessages($accessToken, $integration->email_address);
            
            if (!$messages['success']) {
                return $messages;
            }

            $newEmails = [];
            foreach ($messages['data'] as $messageId) {
                // Get message details
                $message = $this->getMessage($accessToken, $messageId);
                
                if ($message['success']) {
                    $newEmails[] = $message['data'];
                }
            }

            // Update last sync time
            $integration->updateLastSync();

            return [
                'success' => true,
                'message' => 'Inbox monitored successfully',
                'data' => $newEmails,
                'count' => count($newEmails),
            ];

        } catch (\Exception $e) {
            Log::error('Gmail API monitoring failed', [
                'integration_id' => $integration->id,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'message' => 'Failed to monitor inbox: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Get list of messages from Gmail.
     */
    private function getMessages(string $accessToken, string $emailAddress): array
    {
        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $accessToken,
                'Accept' => 'application/json',
            ])->get($this->baseUrl . '/users/me/messages', [
                'q' => 'is:unread',
                'maxResults' => 10,
            ]);

            if (!$response->successful()) {
                return [
                    'success' => false,
                    'message' => 'Failed to fetch messages: ' . $response->body(),
                ];
            }

            $data = $response->json();
            $messageIds = array_column($data['messages'] ?? [], 'id');

            return [
                'success' => true,
                'data' => $messageIds,
            ];

        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'Exception while fetching messages: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Get specific message details.
     */
    private function getMessage(string $accessToken, string $messageId): array
    {
        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $accessToken,
                'Accept' => 'application/json',
            ])->get($this->baseUrl . "/users/me/messages/{$messageId}");

            if (!$response->successful()) {
                return [
                    'success' => false,
                    'message' => 'Failed to fetch message: ' . $response->body(),
                ];
            }

            $message = $response->json();
            $headers = $this->extractHeaders($message['payload']['headers'] ?? []);
            
            $body = $this->extractBody($message['payload'] ?? []);

            return [
                'success' => true,
                'data' => [
                    'message_id' => $messageId,
                    'from_email' => $headers['From'] ?? '',
                    'to_email' => $headers['To'] ?? '',
                    'subject' => $headers['Subject'] ?? '',
                    'body' => $body,
                    'date' => $headers['Date'] ?? null,
                ],
            ];

        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'Exception while fetching message: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Extract headers from Gmail message.
     */
    private function extractHeaders(array $headers): array
    {
        $extracted = [];
        
        foreach ($headers as $header) {
            $name = $header['name'] ?? '';
            $value = $header['value'] ?? '';
            
            switch (strtolower($name)) {
                case 'from':
                    $extracted['From'] = $this->extractEmailFromHeader($value);
                    break;
                case 'to':
                    $extracted['To'] = $this->extractEmailFromHeader($value);
                    break;
                case 'subject':
                    $extracted['Subject'] = $value;
                    break;
                case 'date':
                    $extracted['Date'] = $value;
                    break;
            }
        }
        
        return $extracted;
    }

    /**
     * Extract email address from header value.
     */
    private function extractEmailFromHeader(string $headerValue): string
    {
        // Extract email from "Name <email@domain.com>" format
        if (preg_match('/<([^>]+)>/', $headerValue, $matches)) {
            return $matches[1];
        }
        
        // Return as-is if no angle brackets
        return trim($headerValue);
    }

    /**
     * Extract body from Gmail message payload.
     */
    private function extractBody(array $payload): string
    {
        // Try to get text/plain first
        if (isset($payload['body']['data'])) {
            return base64_decode(str_replace(['-', '_'], ['+', '/'], $payload['body']['data']));
        }
        
        // Try to get from parts
        if (isset($payload['parts'])) {
            foreach ($payload['parts'] as $part) {
                if (isset($part['mimeType']) && $part['mimeType'] === 'text/plain') {
                    if (isset($part['body']['data'])) {
                        return base64_decode(str_replace(['-', '_'], ['+', '/'], $part['body']['data']));
                    }
                }
            }
        }
        
        return '';
    }

    /**
     * Refresh access token using refresh token.
     */
    public function refreshAccessToken(EmailIntegration $integration): bool
    {
        try {
            $credentials = $integration->credentials;
            $refreshToken = $credentials['refresh_token'] ?? null;

            if (!$refreshToken) {
                return false;
            }

            $response = Http::post('https://oauth2.googleapis.com/token', [
                'client_id' => config('services.google.client_id'),
                'client_secret' => config('services.google.client_secret'),
                'refresh_token' => $refreshToken,
                'grant_type' => 'refresh_token',
            ]);

            if (!$response->successful()) {
                Log::error('Failed to refresh Gmail access token', [
                    'integration_id' => $integration->id,
                    'response' => $response->body(),
                ]);
                return false;
            }

            $data = $response->json();
            $newCredentials = array_merge($credentials, [
                'access_token' => $data['access_token'],
                'expires_at' => now()->addSeconds($data['expires_in'])->timestamp,
            ]);

            $integration->update(['credentials' => $newCredentials]);

            return true;

        } catch (\Exception $e) {
            Log::error('Exception while refreshing Gmail access token', [
                'integration_id' => $integration->id,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Test Gmail API connection.
     */
    public function testConnection(EmailIntegration $integration): array
    {
        try {
            if (!$integration->credentials) {
                return [
                    'success' => false,
                    'message' => 'No credentials configured',
                ];
            }

            $credentials = $integration->credentials;
            $accessToken = $credentials['access_token'] ?? null;

            if (!$accessToken) {
                return [
                    'success' => false,
                    'message' => 'No access token available',
                ];
            }

            // Test by getting user profile
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $accessToken,
                'Accept' => 'application/json',
            ])->get($this->baseUrl . '/users/me/profile');

            if (!$response->successful()) {
                // Try to refresh token if it's expired
                if ($response->status() === 401) {
                    if ($this->refreshAccessToken($integration)) {
                        return $this->testConnection($integration);
                    }
                }

                return [
                    'success' => false,
                    'message' => 'Connection test failed: ' . $response->body(),
                ];
            }

            $profile = $response->json();

            return [
                'success' => true,
                'message' => 'Connection test successful',
                'data' => [
                    'email_address' => $profile['emailAddress'] ?? '',
                    'messages_total' => $profile['messagesTotal'] ?? 0,
                    'threads_total' => $profile['threadsTotal'] ?? 0,
                ],
            ];

        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'Connection test failed: ' . $e->getMessage(),
            ];
        }
    }
}
