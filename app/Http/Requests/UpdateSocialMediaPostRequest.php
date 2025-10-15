<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateSocialMediaPostRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'title' => 'sometimes|required|string|max:255',
            'content' => 'sometimes|required|string|max:10000',
            'platform' => 'sometimes|required|string|in:facebook,twitter,instagram,linkedin,youtube,tiktok,pinterest',
            'hashtags' => 'nullable|array|max:30',
            'hashtags.*' => 'string|max:100|regex:/^#?[a-zA-Z0-9_]+$/',
            'scheduled_at' => 'nullable|date|after:now',
            'media_urls' => 'nullable|array|max:10',
            'media_urls.*' => 'url|max:2048',
            'target_audience' => 'nullable|string|max:500',
            'call_to_action' => 'nullable|string|max:100',
            'location' => 'nullable|string|max:255',
            'mentions' => 'nullable|array|max:20',
            'mentions.*' => 'string|max:100',
            'status' => 'sometimes|string|in:draft,scheduled'
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'title.required' => 'Post title is required.',
            'content.required' => 'Post content is required.',
            'content.max' => 'Post content cannot exceed 10,000 characters.',
            'platform.required' => 'Please select a social media platform.',
            'platform.in' => 'Invalid social media platform selected.',
            'hashtags.max' => 'You can add a maximum of 30 hashtags.',
            'hashtags.*.regex' => 'Hashtags can only contain letters, numbers, and underscores.',
            'scheduled_at.after' => 'Scheduled time must be in the future.',
            'media_urls.max' => 'You can add a maximum of 10 media files.',
            'media_urls.*.url' => 'Please provide valid URLs for media files.'
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        // Clean and format hashtags
        if ($this->has('hashtags') && is_array($this->hashtags)) {
            $hashtags = array_map(function ($hashtag) {
                $hashtag = trim($hashtag);
                return str_starts_with($hashtag, '#') ? $hashtag : '#' . $hashtag;
            }, $this->hashtags);
            
            $this->merge([
                'hashtags' => array_unique(array_filter($hashtags))
            ]);
        }

        // Ensure scheduled_at is properly formatted
        if ($this->has('scheduled_at') && $this->scheduled_at) {
            $this->merge([
                'scheduled_at' => date('Y-m-d H:i:s', strtotime($this->scheduled_at))
            ]);
        }
    }
}
