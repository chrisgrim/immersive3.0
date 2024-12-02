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
        return [
            'name' => [
                'required', 
                'max:60',
                new UniqueSlugRule($this->name, Community::class, 'slug', $this->route('community')?->id)
            ],
            'blurb' => 'required|string|min:1|max:254',
            'description' => 'max:5000',
            'image' => [
                'nullable',
                'image',
                'mimes:jpeg,png,gif,webp',
                'max:10240', // 10MB
                'dimensions:min_width=800,min_height=450'
            ],
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
            'name.required' => 'A community name is required',
            'name.max' => 'The community name cannot be longer than 60 characters',
            'blurb.required' => 'A short description is required',
            'blurb.max' => 'The short description cannot be longer than 254 characters',
            'description.max' => 'The description cannot be longer than 5000 characters',
            'image.image' => 'The file must be an image',
            'image.mimes' => 'The image must be a JPG, PNG, GIF, or WEBP file',
            'image.max' => 'The image file size cannot be larger than 10MB',
            'image.dimensions' => 'The image must be at least 800x450 pixels',
        ];
    }
}