<?php

namespace App\Http\Requests;

use App\Models\Organizer;
use App\Rules\UniqueSlugRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreOrganizerRequest extends FormRequest
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
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array|string>
     */
    public function rules(): array
    {
        return [
            'name' => [
                'required', 
                'string', 
                'max:60', 
                new UniqueSlugRule($this->name, Organizer::class, 'slug', $this->route('organizer')?->id)
            ],
            'description' => 'required|string|min:1|max:2000',
            'email' => 'nullable|email',
            'website' => 'nullable|url',
            'instagramHandle' => 'nullable|string|max:255',
            'twitterHandle' => 'nullable|string|max:255',
            'facebookHandle' => 'nullable|string|max:255',
            'patreon' => 'nullable|string|max:255',
        ];
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array
     */
    public function messages(): array
    {
        return [
            'name.required' => 'The name is required.',
            'name.max' => 'The name may not be greater than 120 characters.',
            'description.required' => 'The description is required.',
            'description.min' => 'The description must be at least 1 character.',
            'description.max' => 'The description may not be greater than 2000 characters.',
            'email.email' => 'The email must be a valid email address.',
            'website.url' => 'The website must be a valid URL.',
            'instagramHandle.max' => 'The Instagram handle may not be greater than 255 characters.',
            'twitterHandle.max' => 'The Twitter handle may not be greater than 255 characters.',
            'facebookHandle.max' => 'The Facebook handle may not be greater than 255 characters.',
            'patreon.max' => 'The Patreon handle may not be greater than 255 characters.',
        ];
    }
}
