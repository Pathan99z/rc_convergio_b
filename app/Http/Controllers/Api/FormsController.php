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
}
