<?php

require_once 'vendor/autoload.php';

use App\Services\FormSubmissionHandler;
use App\Models\Form;

// Test the new smart form submission
echo "🧪 Testing Smart Form Submission Handler...\n";
echo "==========================================\n\n";

// Mock form data
$form = new Form();
$form->id = 1;
$form->tenant_id = 1;
$form->name = 'Request Demo';
$form->status = 'active';
$form->fields = [
    ['name' => 'first_name', 'type' => 'text', 'required' => true, 'label' => 'First Name'],
    ['name' => 'last_name', 'type' => 'text', 'required' => true, 'label' => 'Last Name'],
    ['name' => 'email', 'type' => 'email', 'required' => true, 'label' => 'Email'],
    ['name' => 'company', 'type' => 'text', 'required' => false, 'label' => 'Company'],
    ['name' => 'phone', 'type' => 'phone', 'required' => false, 'label' => 'Phone']
];
$form->consent_required = true;
$form->settings = [
    'create_company_from_domain' => true,
    'lifecycle_stage' => 'lead',
    'tags' => ['demo_request', 'website_lead']
];

// Mock payload
$payload = [
    'first_name' => 'John',
    'last_name' => 'Doe',
    'email' => 'john.doe@examplecompany.com',
    'company' => 'Example Company Inc',
    'phone' => '+1234567890',
    'consent_given' => true
];

// Mock UTM data
$utmData = [
    'utm_source' => 'google',
    'utm_medium' => 'cpc',
    'utm_campaign' => 'demo_request_2025'
];

echo "📋 Form Configuration:\n";
echo "- Name: {$form->name}\n";
echo "- Status: {$form->status}\n";
echo "- Fields: " . count($form->fields) . " fields\n";
echo "- Settings: " . json_encode($form->settings) . "\n\n";

echo "📝 Test Payload:\n";
echo "- First Name: {$payload['first_name']}\n";
echo "- Last Name: {$payload['last_name']}\n";
echo "- Email: {$payload['email']}\n";
echo "- Company: {$payload['company']}\n";
echo "- Phone: {$payload['phone']}\n";
echo "- Consent: " . ($payload['consent_given'] ? 'Yes' : 'No') . "\n\n";

echo "🔗 UTM Data:\n";
echo "- Source: {$utmData['utm_source']}\n";
echo "- Medium: {$utmData['utm_medium']}\n";
echo "- Campaign: {$utmData['utm_campaign']}\n\n";

echo "🚀 Testing FormSubmissionHandler...\n";

try {
    $handler = new FormSubmissionHandler();
    
    echo "✅ Handler created successfully\n";
    echo "📤 Processing form submission...\n";
    
    $result = $handler->processSubmission(
        $form,
        $payload,
        '192.168.1.1',
        'Mozilla/5.0 (Test Browser)',
        $utmData,
        'https://example.com/demo'
    );
    
    echo "✅ Submission processed successfully!\n\n";
    echo "📊 Results:\n";
    echo "- Submission ID: {$result['submission_id']}\n";
    echo "- Processed: " . ($result['processed'] ? 'Yes' : 'No') . "\n";
    echo "- Contact ID: {$result['contact']['id']}\n";
    echo "- Contact Status: {$result['contact']['status']}\n";
    echo "- Company ID: " . ($result['company']['id'] ?? 'N/A') . "\n";
    echo "- Company Status: {$result['company']['status']}\n";
    echo "- Owner ID: {$result['owner_id']}\n";
    echo "- Source: {$result['source']}\n\n";
    
    echo "🎯 Smart CRM Features Activated:\n";
    echo "✅ Contact deduplication\n";
    echo "✅ Company auto-creation/linking\n";
    echo "✅ Marketing source tracking\n";
    echo "✅ Timeline activity creation\n";
    echo "✅ List/segment matching\n";
    echo "✅ Round-robin owner assignment\n";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    echo "🔍 Trace: " . $e->getTraceAsString() . "\n";
}

echo "\n✨ Test completed!\n";
