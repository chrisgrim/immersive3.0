<?php

namespace App\Http\Requests;

use App\Models\Organizer;
use Illuminate\Foundation\Http\FormRequest;

class StoreOrganizerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $rules = [];

        // Only apply name rules if name is being updated
        if ($this->has('name')) {
            $rules['name'] = [
                'required', 
                'string', 
                'max:80'
            ];
        }

        // Only apply description rules if description is being updated
        if ($this->has('description')) {
            $rules['description'] = 'required|string|min:1|max:2000';
        }

        // Only apply image rules if image is being updated
        if ($this->hasFile('image')) {
            $rules['image'] = 'required|image|mimes:jpeg,png,jpg,webp|max:8192';
        }

        // Social media and contact rules - always validate if present
        $rules += [
            'email' => 'nullable|email',
            'website' => ['nullable', 'url', 'regex:/^https:\/\//'],
            'instagramHandle' => 'nullable|string|max:30',
            'twitterHandle' => 'nullable|string|max:15',
            'facebookHandle' => 'nullable|string|max:50',
            'patreon' => 'nullable|string|max:30',
        ];

        return $rules;
    }

    public function messages(): array
    {
        return [
            'name.required' => 'The name is required.',
            'name.max' => 'The name may not be greater than 80 characters.',
            'description.required' => 'The description is required.',
            'description.min' => 'The description must be at least 1 character.',
            'description.max' => 'The description may not be greater than 2000 characters.',
            'email.email' => 'The email must be a valid email address.',
            'website.url' => 'The website must be a valid URL.',
            'website.regex' => 'The website must start with https://',
            'instagramHandle.max' => 'The Instagram handle may not be greater than 30 characters.',
            'twitterHandle.max' => 'The Twitter handle may not be greater than 15 characters.',
            'facebookHandle.max' => 'The Facebook handle may not be greater than 50 characters.',
            'patreon.max' => 'The Patreon handle may not be greater than 30 characters.',
            'image.required' => 'An image file is required.',
            'image.image' => 'The file must be an image.',
            'image.mimes' => 'The image must be a file of type: jpeg, png, jpg, webp.',
            'image.max' => 'The image may not be greater than 8MB.',
        ];
    }
}