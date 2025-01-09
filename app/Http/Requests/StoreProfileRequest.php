<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;


class StoreProfileRequest extends FormRequest
{
    
    public function authorize(): bool
    {
         $user = $this->route('user');
         return Auth::user()->can('update', $user);
    }

    public function rules(): array
    {
        return [
            'name' => 'nullable|string|max:255',
            'email' => 'sometimes|string|email|max:255|unique:users,email,' . $this->user()->id,
            'newsletter_type' => 'sometimes|string|in:a,m,u,n',
            'silence' => 'sometimes|string|in:y,n',
            'image' => [
                'sometimes',
                'file',
                'mimes:jpeg,png,webp',
                'max:5120', // 5MB max
            ],
            // Add any other validation rules as needed
        ];
    }

    public function messages(): array
    {
        return [
            'image.mimes' => 'The image must be a JPEG, PNG, or WebP file.',
            'image.max' => 'The image must be less than 5MB.',
        ];
    }

    protected function prepareForValidation(): void
    {
        // If only an image is being uploaded, don't validate other fields
        if ($this->hasFile('image') && count($this->allFiles()) === 1 && count($this->all()) === 1) {
            $this->only(['image']);
        }
    }
}
