<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\FormResource;
use App\Http\Resources\FormSubmissionResource;
use App\Models\Form;
use App\Services\FormService;
use App\Jobs\ProcessFormSubmissionJob;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class PublicFormController extends Controller
{
    public function __construct(
        private FormService $formService
    ) {}

    /**
     * Show a form for public preview (no auth required).
     */
    public function show($id): JsonResource
    {
        // Manually find the form instead of relying on route model binding
        $form = Form::find($id);
        
        if (!$form) {
            Log::error('Form not found for public preview', ['requested_id' => $id]);
            abort(404, 'Form not found or unavailable');
        }
        
        // Check if form is active for public access
        if ($form->status !== 'active') {
            Log::warning('Inactive form accessed publicly', ['form_id' => $form->id, 'status' => $form->status]);
            abort(404, 'Form not found or unavailable');
        }
        
        Log::info('Public form preview accessed', ['form_id' => $form->id, 'form_name' => $form->name]);
        
        // Load only the form data without sensitive information
        $form->load(['creator:id,name,email']);
        
        return new FormResource($form);
    }

    /**
     * Submit a form (public endpoint, no auth required).
     */
    public function submit(Request $request, $id): JsonResponse
    {
        // Manually find the form instead of relying on route model binding
        $form = Form::find($id);
        
        if (!$form) {
            Log::error('Form not found', ['requested_id' => $id]);
            return response()->json(['message' => 'Form not found'], 404);
        }
        
        // Check if form is active for public submission
        if ($form->status !== 'active') {
            Log::warning('Inactive form submission attempted', ['form_id' => $form->id, 'status' => $form->status]);
            return response()->json(['message' => 'Form is not available for submissions.'], 403);
        }
        
        Log::info('Form submission attempted', ['form_id' => $form->id, 'form_name' => $form->name]);
        
        // DEBUG: Log form structure and request data
        Log::info('Form structure debug', [
            'form_id' => $form->id,
            'form_fields' => $form->fields,
            'form_fields_count' => $form->fields ? count($form->fields) : 0,
            'form_fields_type' => gettype($form->fields),
            'request_all' => $request->all(),
            'request_keys' => array_keys($request->all()),
            'request_method' => $request->method(),
            'request_url' => $request->url(),
            'request_path' => $request->path(),
            'user_agent' => $request->userAgent()
        ]);
        
        // Build validation rules based on form fields
        $rules = [];
        $messages = [];
        
        if ($form->fields) {
            foreach ($form->fields as $field) {
                $fieldRules = [];
                
                if ($field['required'] ?? false) {
                    $fieldRules[] = 'required';
                } else {
                    $fieldRules[] = 'nullable';
                }

                // Add type-specific validation
                switch ($field['type']) {
                    case 'email':
                        $fieldRules[] = 'email';
                        break;
                    case 'phone':
                        $fieldRules[] = 'regex:/^\+?[1-9]\d{1,14}$/';
                        break;
                    case 'select':
                    case 'radio':
                        if (isset($field['options']) && is_array($field['options'])) {
                            $fieldRules[] = 'in:' . implode(',', $field['options']);
                        }
                        break;
                    default:
                        $fieldRules[] = 'string';
                        $fieldRules[] = 'max:1000';
                }

                // CRITICAL FIX: Expect fields under payload object (payload.first_name, payload.last_name, etc.)
                $rules["payload.{$field['name']}"] = $fieldRules;
                
                // Add custom messages
                $fieldLabel = $field['label'] ?? $field['name'];
                if ($field['required'] ?? false) {
                    $messages["payload.{$field['name']}.required"] = "The {$fieldLabel} field is required.";
                }
                
                switch ($field['type']) {
                    case 'email':
                        $messages["payload.{$field['name']}.email"] = "The {$fieldLabel} must be a valid email address.";
                        break;
                    case 'phone':
                        $messages["payload.{$field['name']}.regex"] = "The {$fieldLabel} must be a valid phone number.";
                        break;
                    case 'select':
                    case 'radio':
                        if (isset($field['options']) && is_array($field['options'])) {
                            $options = implode(', ', $field['options']);
                            $messages["payload.{$field['name']}.in"] = "The {$fieldLabel} must be one of: {$options}.";
                        }
                        break;
                }
            }
        }
        
        // DEBUG: Log validation rules and messages
        Log::info('Validation rules debug', [
            'form_id' => $form->id,
            'generated_rules' => $rules,
            'generated_messages' => $messages,
            'rules_count' => count($rules)
        ]);

        // Add consent validation if required (consent_given stays at top level)
        if ($form->consent_required) {
            $rules['consent_given'] = ['required', 'accepted'];
            $messages['consent_given.required'] = 'You must accept the terms and conditions.';
            $messages['consent_given.accepted'] = 'You must accept the terms and conditions.';
        }

        // Validate the request
        $validator = Validator::make($request->all(), $rules, $messages);
        
        if ($validator->fails()) {
            Log::warning('Form validation failed', [
                'form_id' => $form->id,
                'errors' => $validator->errors()->toArray(),
                'request_data' => $request->all() // Add request data for debugging
            ]);
            
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            // CRITICAL FIX: Extract form fields directly from payload object
            $payload = [];
            
            // Extract all form fields from the payload object
            if ($form->fields) {
                foreach ($form->fields as $field) {
                    $fieldName = $field['name'];
                    
                    // Get field value directly from payload object
                    $fieldValue = $request->input("payload.{$fieldName}");
                    
                    // Only add non-null values to payload
                    if ($fieldValue !== null) {
                        $payload[$fieldName] = $fieldValue;
                    }
                }
            }
            
            $consentGiven = $request->input('consent_given', false);
            $payload['consent_given'] = $consentGiven;
            
            // DEBUG: Log payload extraction
            Log::info('Payload extraction debug', [
                'form_id' => $form->id,
                'extracted_payload' => $payload,
                'request_all' => $request->all(),
                'has_payload_object' => $request->has('payload'),
                'payload_object' => $request->input('payload'),
                'extraction_method' => 'direct_from_payload'
            ]);

            // Normalize keys to ensure downstream services always have email/company
            if (isset($payload['email_address']) && !isset($payload['email'])) {
                $payload['email'] = $payload['email_address'];
            }
            if (isset($payload['company_name']) && !isset($payload['company'])) {
                $payload['company'] = $payload['company_name'];
            }

            Log::info('Payload normalization', [
                'form_id' => $form->id,
                'original' => $request->all(),
                'normalized' => $payload
            ]);
            
            // TEMP DEBUG: Log key submission details before saving
            Log::info('Form submission debug: before submit', [
                'form_id' => $form->id,
                'tenant_id' => $form->tenant_id,
                'created_by' => $form->created_by,
                'payload_contact' => [
                    'first_name' => $payload['first_name'] ?? null,
                    'last_name' => $payload['last_name'] ?? null,
                    'email' => $payload['email'] ?? null,
                    'phone' => $payload['phone'] ?? null,
                    'company' => $payload['company'] ?? null,
                ],
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);
            
            // Create submission using FormService (synchronous company upsert + linking inside)
            $submission = $this->formService->submitForm(
                $form,
                $payload,
                $request->ip(),
                $request->userAgent()
            );
            
            // Mark initial submission pending; background job will set to processed
            $submission->update([
                'consent_given' => $consentGiven,
                'status' => 'pending'
            ]);
            
            // TEMP DEBUG: Log after submission created
            Log::info('Form submission debug: after submit', [
                'form_id' => $form->id,
                'submission_id' => $submission->id,
                'contact_id' => $submission->contact_id,
                'company_id' => $submission->company_id,
                'status' => $submission->status,
                'consent_given' => $consentGiven,
            ]);
            
            // AUTOMATED PROCESSING: Dispatch background job; if it does not run immediately, fall back to inline processing
            try {
                ProcessFormSubmissionJob::dispatch($submission->id);
            } catch (\Throwable $e) {
                // Ignore and try sync below
            }

            // Fallback: if still pending after dispatch, process inline to ensure immediate UX
            try {
                $submission->refresh();
                if ($submission->status !== 'processed') {
                    $handler = app(\App\Services\FormSubmissionHandler::class);
                    $result = $handler->processSubmission(
                        $form,
                        $payload,
                        $request->ip(),
                        $request->userAgent(),
                        [],
                        null,
                        $submission
                    );

                    $updates = [];
                    if (empty($submission->contact_id)) {
                        $updates['contact_id'] = $result['contact']['id'];
                    }
                    if (empty($submission->company_id) && isset($result['company']['id'])) {
                        $updates['company_id'] = $result['company']['id'];
                    }
                    $updates['status'] = 'processed';
                    if (!empty($updates)) {
                        $submission->update($updates);
                    }
                }
            } catch (\Throwable $e) {
                // Leave as pending; reprocess remains available
            }
            
            // Eager load relations so UI can immediately render created contact/company
            $submission->load([
                'contact:id,first_name,last_name,email,company_id',
                'company:id,name,domain'
            ]);

            Log::info('Form submission successful and processing job dispatched', [
                'form_id' => $form->id,
                'submission_id' => $submission->id,
                'ip_address' => $request->ip(),
                'job_dispatched' => true
            ]);
            
            return response()->json([
                'message' => 'Form submitted successfully. Processing in background.',
                'data' => new FormSubmissionResource($submission)
            ], 201);
            
        } catch (\Exception $e) {
            Log::error('Form submission failed', [
                'form_id' => $form->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'message' => 'Form submission failed. Please try again.'
            ], 500);
        }
    }
}
