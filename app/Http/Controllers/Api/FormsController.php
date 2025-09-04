<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Forms\StoreFormRequest;
use App\Http\Requests\Forms\UpdateFormRequest;
use App\Http\Resources\FormResource;
use App\Http\Resources\FormSubmissionResource;
use App\Models\Form;
use App\Services\FormService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Log;

class FormsController extends Controller
{
    public function __construct(
        private FormService $formService
    ) {}

    /**
     * Display a listing of forms.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $filters = [
            'tenant_id' => $request->user()->id, // Use authenticated user as tenant
            'q' => $request->get('q'),
            'name' => $request->get('name'),
            'created_by' => $request->get('created_by'),
            'sortBy' => $request->get('sortBy', 'created_at'),
            'sortOrder' => $request->get('sortOrder', 'desc'),
        ];

        $forms = $this->formService->getForms($filters, $request->get('per_page', 15));

        return FormResource::collection($forms);
    }

    /**
     * Store a newly created form.
     */
    public function store(StoreFormRequest $request): JsonResource
    {
        $data = $request->validated();
        $data['created_by'] = $request->user()->id;
        $data['tenant_id'] = $request->user()->id; // Use authenticated user as tenant

        $form = $this->formService->createForm($data);

        return new FormResource($form);
    }

    /**
     * Display the specified form.
     */
    public function show(Form $form): JsonResource
    {
        $this->authorize('view', $form);

        $form = $this->formService->getFormWithSubmissions($form);

        return new FormResource($form);
    }

    /**
     * Update the specified form.
     */
    public function update(UpdateFormRequest $request, Form $form): JsonResource
    {
        $this->authorize('update', $form);

        $data = $request->validated();
        $this->formService->updateForm($form, $data);

        $form->refresh();
        return new FormResource($form);
    }

    /**
     * Remove the specified form.
     */
    public function destroy(Form $form): JsonResponse
    {
        $this->authorize('delete', $form);

        $this->formService->deleteForm($form);

        return response()->json(['message' => 'Form deleted successfully']);
    }

    /**
     * Get form submissions.
     */
    public function submissions(Request $request, Form $form): AnonymousResourceCollection
    {
        $this->authorize('view', $form);

        $submissions = $this->formService->getFormSubmissions($form, $request->get('per_page', 15));

        return FormSubmissionResource::collection($submissions);
    }

    /**
     * Check if a form name already exists.
     */
    public function checkDuplicate(Request $request): JsonResponse
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'exclude_id' => 'nullable|integer|exists:forms,id'
        ]);

        $name = $request->get('name');
        $excludeId = $request->get('exclude_id');
        $tenantId = $request->user()->id;

        $query = Form::where('tenant_id', $tenantId)
                    ->whereRaw('LOWER(name) = ?', [strtolower($name)]);

        if ($excludeId) {
            $query->where('id', '!=', $excludeId);
        }

        $exists = $query->exists();

        return response()->json(['exists' => $exists]);
    }

    /**
     * Get a specific form submission.
     */

    /**
     * Get form settings.
     */
    public function getSettings($id): JsonResponse
    {
        $form = Form::findOrFail($id);
        $this->authorize('view', $form);

        return response()->json([
            'data' => [
                'id' => $form->id,
                'settings' => $form->settings ?? [],
                'consent_required' => $form->consent_required,
                'created_at' => $form->created_at,
                'updated_at' => $form->updated_at
            ]
        ]);
    }

    /**
     * Update form settings.
     */
    public function updateSettings(Request $request, $id): JsonResponse
    {
        $form = Form::findOrFail($id);
        $this->authorize('update', $form);

        $request->validate([
            'settings' => 'nullable|array',
            'consent_required' => 'nullable|boolean'
        ]);

        $data = $request->only(['settings', 'consent_required']);
        
        // Debug: Log what we're receiving and updating
        Log::info('Form settings update request', [
            'form_id' => $id,
            'request_data' => $request->all(),
            'filtered_data' => $data,
            'current_form_settings' => $form->settings
        ]);
        
        $form->update($data);
        $form->refresh(); // Ensure updated data is loaded
        
        // Debug: Log what was actually saved
        Log::info('Form settings after update', [
            'form_id' => $id,
            'saved_settings' => $form->settings,
            'form_attributes' => $form->getAttributes()
        ]);

        return response()->json([
            'message' => 'Form settings updated successfully',
            'data' => [
                'id' => $form->id,
                'settings' => $form->settings ?? [],
                'consent_required' => $form->consent_required,
                'updated_at' => $form->updated_at
            ]
        ]);
    }

    /**
     * Get form field mapping.
     */
    public function getFieldMapping($id): JsonResponse
    {
        $form = Form::findOrFail($id);
        $this->authorize('view', $form);

        return response()->json([
            'data' => [
                'id' => $form->id,
                'fields' => $form->fields ?? [],
                'field_mapping' => $form->field_mapping ?? [],
                'created_at' => $form->created_at,
                'updated_at' => $form->updated_at
            ]
        ]);
    }

    /**
     * Update form field mapping.
     */
    public function updateFieldMapping(Request $request, $id): JsonResponse
    {
        $form = Form::findOrFail($id);
        $this->authorize('update', $form);

        $request->validate([
            'fields' => 'nullable|array',
            'mappings' => 'nullable|array' // Changed from 'field_mapping' to 'mappings'
        ]);

        // Extract the mappings data from the request
        $mappings = $request->input('mappings', []);
        $fields = $request->input('fields', null); // Changed to null to check if fields were sent
        
        // Debug: Log what we're receiving and updating
        Log::info('Form field mapping update request', [
            'form_id' => $id,
            'request_data' => $request->all(),
            'mappings' => $mappings,
            'fields' => $fields,
            'current_field_mapping' => $form->field_mapping,
            'current_fields' => $form->fields
        ]);
        
        // CRITICAL FIX: Only update field_mapping, preserve existing fields unless explicitly sent
        $form->field_mapping = $mappings;
        
        // Only update fields if they were explicitly sent in the request
        if ($fields !== null) {
            $form->fields = $fields;
        }
        // If fields is null, keep existing fields unchanged
        
        $form->save();
        
        $form->refresh(); // Ensure updated data is loaded
        
        // Debug: Log what was actually saved
        Log::info('Form field mapping after update', [
            'form_id' => $id,
            'saved_field_mapping' => $form->field_mapping,
            'saved_fields' => $form->fields,
            'fields_updated' => $fields !== null,
            'form_attributes' => $form->getAttributes()
        ]);

        return response()->json([
            'message' => 'Form field mapping updated successfully',
            'data' => [
                'id' => $form->id,
                'fields' => $form->fields ?? [],
                'field_mapping' => $form->field_mapping ?? [],
                'updated_at' => $form->updated_at
            ]
        ]);
    }

    public function showSubmission(Request $request, Form $form, $submissionId): JsonResponse
    {
        $this->authorize('view', $form);

        $submission = $form->submissions()->find($submissionId);

        if (!$submission) {
            return response()->json(['message' => 'Submission not found'], 404);
        }

        return response()->json([
            'id' => $submission->id,
            'form_id' => $submission->form_id,
            'contact_id' => $submission->contact_id,
            'payload' => $submission->payload,
            'ip_address' => $submission->ip_address,
            'user_agent' => $submission->user_agent,
            'created_at' => $submission->created_at
        ]);
    }

    /**
     * Reprocess a form submission.
     */
    public function reprocessSubmission(Request $request, $id, $submissionId): JsonResponse
    {
        $form = Form::findOrFail($id);
        $this->authorize('view', $form);

        $submission = $form->submissions()->find($submissionId);

        if (!$submission) {
            return response()->json(['message' => 'Submission not found'], 404);
        }

        try {
            // Get the submission payload
            $payload = $submission->payload;
            
            // Use the FormSubmissionHandler to reprocess
            $handler = app(\App\Services\FormSubmissionHandler::class);
            
            $result = $handler->processSubmission(
                $form,
                $payload,
                $submission->ip_address,
                $submission->user_agent,
                [], // No UTM data for reprocessing
                null // No referrer for reprocessing
            );

            // Update the submission record with the processed contact and company IDs
            $submission->update([
                'contact_id' => $result['contact']['id'],
                'company_id' => $result['company']['id'] ?? null,
                'status' => 'processed'
            ]);

            return response()->json([
                'message' => 'Submission reprocessed successfully',
                'data' => $result
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to reprocess submission', [
                'submission_id' => $submissionId,
                'form_id' => $id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'message' => 'Failed to reprocess submission: ' . $e->getMessage()
            ], 500);
        }
    }
}
