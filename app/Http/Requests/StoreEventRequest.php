<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;

class StoreEventRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'category_id' => 'nullable|exists:categories,id',
            'interactive_level_id' => 'nullable|exists:interactive_levels,id',
            'description' => 'sometimes|string|min:1|max:2000',
            'name' => 'sometimes|string|max:100',
            'closingDate' => 'nullable|date',
            'websiteUrl' => 'sometimes|url|max:255',
            'ticketUrl' => 'nullable|url|max:255',
            'show_times' => 'nullable|string|max:500',
            'tag_line' => 'sometimes|string|max:255',
            'hasLocation' => 'sometimes|boolean',
            'embargo_date' => 'nullable|date_format:Y-m-d H:i:s|after:now',
            'remote_description' => 'nullable|string|max:3000',
            'call_to_action' => 'nullable|string|max:255',
            'video' => 'nullable|string|max:255',
            'archived' => 'nullable|boolean',
            // Add nested validation rules for location
            'location.latitude' => 'sometimes|numeric',
            'location.longitude' => 'sometimes|numeric',
            'location.home' => 'sometimes|string|max:255',
            'location.street' => 'sometimes|string|max:255',
            'location.city' => 'sometimes|string|max:255',
            'location.region' => 'sometimes|string|max:255',
            'location.country' => 'sometimes|string|max:255',
            'location.postal_code' => 'sometimes|nullable|string|max:20',
            'location.hiddenLocation' => 'nullable|string|max:255',
            'location.hiddenLocationToggle' => 'sometimes|boolean',
            'location.venue' => 'nullable|string|max:255',
            // Add validation for remotelocations
            'remotelocations' => 'nullable|array',
            'remotelocations.*.name' => 'required_with:remotelocations|string|max:255',
            // Add validation for dateArray
            'timezone' => 'sometimes|string|max:255',
            'showtype' => 'sometimes|string|in:s,a,o',
            'dateArray' => [
                'required_if:showtype,s',
                'array',
            ],
            'dateArray.*' => 'required_if:showtype,s|date_format:Y-m-d H:i:s',
            // Add validation for tickets
            'tickets' => 'nullable|array',
            'tickets.*.name' => 'required_with:tickets|string|max:40',
            'tickets.*.ticket_price' => 'sometimes|required|numeric|min:0',
            'tickets.*.description' => 'sometimes|nullable|string|max:200',
            'tickets.*.currency' => 'sometimes|required|string|max:3',
            // Add validation for images
            'images' => 'nullable|array',
            'images.*' => [
                'image',
                'mimes:jpeg,png,jpg,webp',
                'max:5120',
                'dimensions:min_width=400,min_height=400,max_width=10000,max_height=10000'
            ],
            'ranks' => 'nullable|array',
            'ranks.*' => 'integer|min:0|max:4',
            'currentImages' => 'nullable|json',
            'deletedImages' => 'nullable|json',
            // Add validation for contentAdvisories
            'contentAdvisories' => 'nullable|array',
            'contentAdvisories.*.name' => 'sometimes|string|max:100',
            // Add validation for mobilityAdvisories
            'mobilityAdvisories' => 'nullable|array',
            'mobilityAdvisories.*.name' => 'sometimes|string|max:100',
            'wheelchairReady' => 'sometimes|boolean',
            // Add validation for contact and interactive levels
            'contactLevel' => 'sometimes|array',
            'contactLevel.id' => 'required_with:contactLevel|exists:contact_levels,id',
            'contactLevel.name' => 'required_with:contactLevel|string|max:255',
            
            'interactiveLevel' => 'sometimes|array',
            'interactiveLevel.id' => 'required_with:interactiveLevel|exists:interactive_levels,id',
            'interactiveLevel.name' => 'required_with:interactiveLevel|string|max:255',
            'interactiveLevel.description' => 'required_with:interactiveLevel|string',

            // Add audience role validation
            'advisories' => 'sometimes|array',
            'advisories.sexual' => 'sometimes|boolean',
            'advisories.sexualDescription' => 'nullable|string|max:1000|required_if:advisories.sexual,true',
            'advisories.audience' => 'sometimes|string|max:1000',

            // Add validation for genres
            'genres' => 'sometimes|array|max:10',
            'genres.*.id' => 'sometimes|required',
            'genres.*.name' => 'required|string|max:50',

            'status' => 'sometimes|string',
        ];
    }

    public function attributes()
    {
        return [
            'location.postal_code' => 'postal code',
        ];
    }

    public function messages()
    {
        return [
            'images.*.dimensions' => 'Images must be at least 400x400 pixels and no larger than 10000x10000 pixels.',
        ];
    }

    protected function failedValidation(Validator $validator)
    {
        // Log the image details if it exists
        if ($this->hasFile('images')) {
            foreach ($this->file('images') as $index => $image) {
                $size = getimagesize($image->getPathname());
                \Log::info("Image validation failed for index {$index}", [
                    'width' => $size[0] ?? 'unknown',
                    'height' => $size[1] ?? 'unknown',
                    'mime' => $image->getMimeType(),
                    'size' => $image->getSize(),
                    'original_name' => $image->getClientOriginalName()
                ]);
            }
        }

        throw new HttpResponseException(response()->json([
            'message' => 'The given data was invalid.',
            'errors' => $validator->errors(),
        ], 422));
    }
}
