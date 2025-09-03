<?php

require_once 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\Form;

// Test direct model access
$form = Form::find(2);
echo "Direct find - Form ID: " . ($form ? $form->id : 'null') . "\n";
echo "Direct find - Form fields: " . ($form ? json_encode($form->fields) : 'null') . "\n";

// Test route model binding simulation
$form2 = Form::where('id', 2)->first();
echo "Where first - Form ID: " . ($form2 ? $form2->id : 'null') . "\n";
echo "Where first - Form fields: " . ($form2 ? json_encode($form2->fields) : 'null') . "\n";

// Test fresh load
if ($form) {
    $form->refresh();
    echo "After refresh - Form ID: " . $form->id . "\n";
    echo "After refresh - Form fields: " . json_encode($form->fields) . "\n";
}
