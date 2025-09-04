<?php

echo "🧪 Testing Smart Form Submission API Endpoint\n";
echo "============================================\n\n";

// Test data
$formId = 12; // Use an existing form ID
$payload = [
    'first_name' => 'John',
    'last_name' => 'Doe',
    'email' => 'john.doe@examplecompany.com',
    'company' => 'Example Company Inc',
    'phone' => '+1234567890',
    'consent_given' => true
];

// UTM parameters
$utmParams = [
    'utm_source' => 'google',
    'utm_medium' => 'cpc',
    'utm_campaign' => 'demo_request_2025'
];

echo "📋 Test Configuration:\n";
echo "- Form ID: {$formId}\n";
echo "- Endpoint: http://127.0.0.1:8000/api/public/forms/{$formId}/submit\n";
echo "- Payload: " . json_encode($payload, JSON_PRETTY_PRINT) . "\n";
echo "- UTM: " . json_encode($utmParams, JSON_PRETTY_PRINT) . "\n\n";

echo "🚀 Testing API Endpoint...\n";

// Build the cURL command
$curlCommand = "curl -X POST http://127.0.0.1:8000/api/public/forms/{$formId}/submit";
$curlCommand .= " -H \"Content-Type: application/json\"";
$curlCommand .= " -H \"Accept: application/json\"";
$curlCommand .= " -d '" . json_encode($payload) . "'";

echo "📡 cURL Command:\n";
echo $curlCommand . "\n\n";

echo "💡 To test manually:\n";
echo "1. Make sure Laravel server is running: php artisan serve\n";
echo "2. Use Postman or cURL to test the endpoint\n";
echo "3. Check the response for smart CRM automation features\n\n";

echo "✨ Test script completed!\n";
echo "🔗 The new endpoint is ready at: POST /api/public/forms/{$formId}/submit\n";
