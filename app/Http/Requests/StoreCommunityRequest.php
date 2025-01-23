<?php

namespace App\Http\Requests;

use App\Models\Curated\Community;
use App\Rules\UniqueSlugRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreCommunityRequest extends FormRequest
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
        $rules = [];

        // Only apply name/blurb rules if they are being updated
        if ($this->has('name')) {
            $rules['name'] = [
                'required', 
                'string',
                'max:100',
                new UniqueSlugRule($this->name, Community::class, 'slug', $this->route('community')?->id)
            ];
            // If name is present, blurb is required
            $rules['blurb'] = 'required|string|min:1|max:254';
        }

        // Only apply description rules if description is being updated
        if ($this->has('description')) {
            $rules['description'] = 'required|string|max:5000';
        }

        // Only apply image rules if image is being updated
        if ($this->hasFile('image')) {
            $rules['image'] = [
                'required',
                'image',
                'mimes:jpeg,png,webp',
                'max:10240', // 10MB
                'dimensions:min_width=800,min_height=450'
            ];
        }

        return $rules;
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array
     */
    public function messages(): array
    {
        return [
            'name.required' => 'A community name is required',
            'name.string' => 'The name must be text',
            'name.max' => 'The community name cannot be longer than 100 characters',
            
            'blurb.required' => 'A short description is required',
            'blurb.string' => 'The short description must be text',
            'blurb.min' => 'The short description must not be empty',
            'blurb.max' => 'The short description cannot be longer than 254 characters',
            
            'description.required' => 'A description is required',
            'description.string' => 'The description must be text',
            'description.max' => 'The description cannot be longer than 5000 characters',
            
            'image.required' => 'An image file is required',
            'image.image' => 'The file must be an image',
            'image.mimes' => 'The image must be a JPG, PNG, or WebP file',
            'image.max' => 'The image file size cannot be larger than 10MB',
            'image.dimensions' => 'The image must be at least 800x450 pixels'
        ];
    }
}