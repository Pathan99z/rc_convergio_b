<?php

    namespace App\Jobs;

    use App\Models\FormSubmission;
    use App\Services\FormSubmissionHandler;
    use Illuminate\Bus\Queueable;
    use Illuminate\Contracts\Queue\ShouldQueue;
    use Illuminate\Foundation\Bus\Dispatchable;
    use Illuminate\Queue\InteractsWithQueue;
    use Illuminate\Queue\SerializesModels;
    use Illuminate\Support\Facades\Log;

    class ProcessFormSubmissionJob implements ShouldQueue
    {
        use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

        public $tries = 3;
        public $timeout = 120;

        protected $submissionId;

        /**
         * Create a new job instance.
         */
        public function __construct(int $submissionId)
        {
            $this->submissionId = $submissionId;
        }

        /**
         * Execute the job.
         */
        public function handle(): void
        {
            try {
                Log::info('Starting automated form submission processing', [
                    'submission_id' => $this->submissionId
                ]);

                // Find the submission
                $submission = FormSubmission::find($this->submissionId);
                if (!$submission) {
                    Log::error('Form submission not found for processing', [
                        'submission_id' => $this->submissionId
                    ]);
                    return;
                }

                // Get the form
                $form = $submission->form;
                if (!$form) {
                    Log::error('Form not found for submission processing', [
                        'submission_id' => $this->submissionId,
                        'form_id' => $submission->form_id
                    ]);
                    $this->markSubmissionAsFailed($submission, 'Form not found');
                    return;
                }

                // Use the existing FormSubmissionHandler to process
                $handler = app(FormSubmissionHandler::class);
                
                $result = $handler->processSubmission(
                    $form,
                    $submission->payload,
                    $submission->ip_address,
                    $submission->user_agent,
                    [], // No UTM data for automated processing
                    null, // No referrer for automated processing
                    $submission // pass existing submission to avoid duplicate row
                );

                // Update IDs only if not already set to prevent duplicates on retries
                $updates = [];
                if (empty($submission->contact_id)) {
                    $updates['contact_id'] = $result['contact']['id'];
                }
                if (empty($submission->company_id) && isset($result['company']['id'])) {
                    $updates['company_id'] = $result['company']['id'];
                }
                // Mark as processed regardless, but keep IDs if already present
                $updates['status'] = 'processed';
                
                if (!empty($updates)) {
                    $submission->update($updates);
                }

                Log::info('Form submission processed successfully', [
                    'submission_id' => $this->submissionId,
                    'contact_id' => $result['contact']['id'],
                    'company_id' => $result['company']['id'] ?? null,
                    'contact_status' => $result['contact']['status'],
                    'company_status' => $result['company']['status']
                ]);

            } catch (\Exception $e) {
                Log::error('Failed to process form submission automatically', [
                    'submission_id' => $this->submissionId,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]);

                // Mark submission as failed
                if (isset($submission)) {
                    $this->markSubmissionAsFailed($submission, $e->getMessage());
                }

                // Re-throw to trigger retry logic
                throw $e;
            }
        }

        /**
         * Mark submission as failed with error details
         */
        private function markSubmissionAsFailed(FormSubmission $submission, string $errorMessage): void
        {
            $submission->update([
                'status' => 'failed',
                'payload' => array_merge($submission->payload ?? [], [
                    'processing_error' => $errorMessage,
                    'failed_at' => now()->toISOString()
                ])
            ]);

            Log::warning('Form submission marked as failed', [
                'submission_id' => $submission->id,
                'error' => $errorMessage
            ]);
        }

        /**
         * Handle a job failure.
         */
        public function failed(\Throwable $exception): void
        {
            Log::error('ProcessFormSubmissionJob failed permanently', [
                'submission_id' => $this->submissionId,
                'error' => $exception->getMessage(),
                'trace' => $exception->getTraceAsString()
            ]);

            // Mark submission as failed if we can find it
            try {
                $submission = FormSubmission::find($this->submissionId);
                if ($submission) {
                    $this->markSubmissionAsFailed($submission, 'Job failed permanently: ' . $exception->getMessage());
                }
            } catch (\Exception $e) {
                Log::error('Failed to mark submission as failed after job failure', [
                    'submission_id' => $this->submissionId,
                    'error' => $e->getMessage()
                ]);
            }
        }
    }
