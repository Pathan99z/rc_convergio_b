<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreSocialMediaPostRequest extends FormRequest
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
            // Core required fields - more flexible
            'title' => 'sometimes|string|max:255', // Changed from required to sometimes
            'content' => 'required|string|max:10000',
            'platform' => 'required|string|in:facebook,twitter,instagram,linkedin,youtube,tiktok,pinterest',
            
            // Optional fields - very flexible
            'hashtags' => 'nullable|array|max:30',
            'hashtags.*' => 'nullable|string|max:100', // Removed strict regex
            'scheduled_at' => 'nullable|date', // Removed after:now for flexibility
            'publish_now' => 'nullable|boolean',
            
            // Media and extras - flexible
            'media_urls' => 'nullable|array|max:10',
            'media_urls.*' => 'nullable|string|max:2048', // Changed from url to string for flexibility
            'target_audience' => 'nullable|string|max:1000', // Increased limit
            'call_to_action' => 'nullable|string|max:255', // Increased limit
            'location' => 'nullable|string|max:255',
            'mentions' => 'nullable|array|max:20',
            'mentions.*' => 'nullable|string|max:100',
            
            // Frontend might send these - accept but ignore
            'user_id' => 'sometimes|integer', // Frontend might send this
            'status' => 'sometimes|string', // Frontend might send this
            'id' => 'sometimes|integer', // Frontend might send this
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
            'publish_now.boolean' => 'Publish now must be true or false.',
            'media_urls.max' => 'You can add a maximum of 10 media files.',
            'media_urls.*.url' => 'Please provide valid URLs for media files.'
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        // Provide default title if not provided
        if (!$this->has('title') || empty($this->title)) {
            $this->merge([
                'title' => 'Social Media Post - ' . date('Y-m-d H:i:s')
            ]);
        }

        // Clean and format hashtags
        if ($this->has('hashtags') && is_array($this->hashtags)) {
            $hashtags = array_map(function ($hashtag) {
                if (empty($hashtag)) return null;
                $hashtag = trim($hashtag);
                return str_starts_with($hashtag, '#') ? $hashtag : '#' . $hashtag;
            }, $this->hashtags);
            
            $this->merge([
                'hashtags' => array_values(array_unique(array_filter($hashtags)))
            ]);
        }

        // Handle scheduled_at more flexibly
        if ($this->has('scheduled_at') && $this->scheduled_at) {
            try {
                $scheduledTime = is_string($this->scheduled_at) 
                    ? date('Y-m-d H:i:s', strtotime($this->scheduled_at))
                    : $this->scheduled_at;
                $this->merge(['scheduled_at' => $scheduledTime]);
            } catch (\Exception $e) {
                // If date parsing fails, remove it
                $this->merge(['scheduled_at' => null]);
            }
        }

        // Clean media URLs
        if ($this->has('media_urls') && is_array($this->media_urls)) {
            $mediaUrls = array_filter($this->media_urls, function($url) {
                return !empty($url) && is_string($url);
            });
            $this->merge(['media_urls' => array_values($mediaUrls)]);
        }

        // Remove fields that shouldn't be set by frontend
        $this->offsetUnset('user_id'); // Always set by backend
        $this->offsetUnset('id'); // Never set by frontend
    }
}
