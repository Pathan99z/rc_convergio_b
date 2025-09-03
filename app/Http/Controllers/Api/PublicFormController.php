<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\FormResource;
use App\Http\Resources\FormSubmissionResource;
use App\Models\Form;
use App\Services\FormService;
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

        // Add consent validation if required
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
                'errors' => $validator->errors()->toArray()
            ]);
            
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            // Extract payload data
            $payload = $request->input('payload', []);
            $consentGiven = $request->input('consent_given', false);
            
            // Create submission using FormService
            $submission = $this->formService->submitForm(
                $form,
                $payload,
                $request->ip(),
                $request->userAgent()
            );
            
            // Update submission with consent information
            $submission->update([
                'consent_given' => $consentGiven
            ]);
            
            Log::info('Form submission successful', [
                'form_id' => $form->id,
                'submission_id' => $submission->id,
                'ip_address' => $request->ip()
            ]);
            
            return response()->json([
                'message' => 'Form submitted successfully',
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
