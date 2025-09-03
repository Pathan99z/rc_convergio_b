<?php

require_once 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Services\FormService;

$formService = new FormService();

$formData = [
    'name' => 'Test Lead Form',
    'fields' => [
        [
            'name' => 'first_name',
            'type' => 'text',
            'label' => 'First Name',
            'required' => true
        ],
        [
            'name' => 'email',
            'type' => 'email',
            'label' => 'Email Address',
            'required' => true
        ]
    ],
    'consent_required' => true,
    'created_by' => 1,
    'tenant_id' => 1
];

$form = $formService->createForm($formData);
echo "Form created with ID: " . $form->id . "\n";
echo "Form fields: ";
var_dump($form->fields);
