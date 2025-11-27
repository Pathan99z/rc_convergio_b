<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Forms\StoreFormRequest;
use App\Http\Requests\Forms\UpdateFormRequest;
use App\Http\Resources\FormResource;
use App\Http\Resources\FormSubmissionResource;
use App\Models\Form;
use App\Services\FormService;
use App\Services\TeamAccessService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Log;

class FormsController extends Controller
{
    public function __construct(
        private FormService $formService,
        private TeamAccessService $teamAccessService
    ) {}

    /**
     * Display a listing of forms.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        // ✅ FIX: Use proper tenant_id instead of user_id
        $tenantId = $request->user()->tenant_id ?? $request->user()->id;
        
        $filters = [
            'tenant_id' => $tenantId,
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
        // ✅ FIX: Use proper tenant_id instead of user_id
        $tenantId = $request->user()->tenant_id ?? $request->user()->id;
        
        $data = $request->validated();
        $data['created_by'] = $request->user()->id;
        $data['tenant_id'] = $tenantId;

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

    public function getSettings(Request $request, $id): JsonResponse
    {
        $form = Form::findOrFail($id);
        $this->authorize('view', $form);

        return response()->json([
            'id' => $form->id,
            'settings' => $form->settings ?? [],
        ]);
    }

    public function updateSettings(Request $request, $id): JsonResponse
    {
        $form = Form::findOrFail($id);
        $this->authorize('update', $form);

        $settings = $request->input('settings', []);

        $form->settings = $settings;
        $form->save();

        return response()->json([
            'message' => 'Form settings updated successfully',
            'data' => [
                'id' => $form->id,
                'settings' => $form->settings ?? [],
            ]
        ]);
    }

    public function getFieldMapping(Request $request, $id): JsonResponse
    {
        $form = Form::findOrFail($id);
        $this->authorize('view', $form);

        return response()->json([
            'id' => $form->id,
            'fields' => $form->fields ?? [],
            'field_mapping' => $form->field_mapping ?? [],
        ]);
    }

    public function updateFieldMapping(Request $request, $id): JsonResponse
    {
        $form = Form::findOrFail($id);
        $this->authorize('update', $form);

        $fields = $request->input('fields');
        $fieldMapping = $request->input('field_mapping');
        if ($fieldMapping === null) {
            $fieldMapping = $request->input('mapping');
        }
        if ($fieldMapping === null) {
            $fieldMapping = $request->input('mappings');
        }

        if ($fields !== null) {
            $form->fields = $fields;
        }
        if ($fieldMapping !== null) {
            $form->field_mapping = $fieldMapping;
        }
        $form->save();

        $form->refresh(); // Ensure updated data is loaded
        
        // Debug: Log what was actually saved
        Log::info('Form field mapping after update', [
            'form_id' => $id,
            'saved_field_mapping' => $form->field_mapping,
            'saved_fields' => $form->fields,
            'fields_updated' => $fields !== null,
            'mapping_keys_present' => [
                'field_mapping' => $request->has('field_mapping'),
                'mapping' => $request->has('mapping'),
                'mappings' => $request->has('mappings'),
            ],
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

        $submission = $form->submissions()->with(['contact:id,first_name,last_name,email','company:id,name,domain'])->find($submissionId);

        if (!$submission) {
            return response()->json(['message' => 'Submission not found'], 404);
        }

        return response()->json([
            'id' => $submission->id,
            'form_id' => $submission->form_id,
            'contact_id' => $submission->contact_id,
            'company_id' => $submission->company_id,
            'status' => $submission->status,
            'payload' => $submission->payload,
            'ip_address' => $submission->ip_address,
            'user_agent' => $submission->user_agent,
            'contact' => $submission->contact ? [
                'id' => $submission->contact->id,
                'first_name' => $submission->contact->first_name,
                'last_name' => $submission->contact->last_name,
                'email' => $submission->contact->email,
            ] : null,
            'company' => $submission->company ? [
                'id' => $submission->company->id,
                'name' => $submission->company->name,
                'domain' => $submission->company->domain,
            ] : null,
            'created_at' => $submission->created_at,
            'updated_at' => $submission->updated_at
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
                null, // No referrer for reprocessing
                $submission // IMPORTANT: operate on the same submission, do not create a new one
            );

            // Only set IDs if they are not already set to avoid duplicates
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

    /**
     * Check if a form name already exists.
     */
    public function checkDuplicate(Request $request): JsonResponse
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'exclude_id' => 'nullable|integer'
        ]);

        $name = $request->input('name');
        $excludeId = $request->input('exclude_id');
        
        // Get the tenant ID for proper scoping
        $tenantId = $request->user()->tenant_id ?? $request->user()->id;

        // Query forms table to check for duplicate names
        // Scope to current tenant to prevent checking across tenants
        $query = Form::where('name', $name)
                     ->where('tenant_id', $tenantId);

        // Exclude the current form when editing
        if ($excludeId) {
            $query->where('id', '!=', $excludeId);
        }

        $exists = $query->exists();

        return response()->json([
            'success' => true,
            'exists' => $exists
        ]);
    }
}
