<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreEventRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'timezone_id' => 'nullable|exists:timezones,id',
            'category_id' => 'nullable|exists:categories,id',
            'interactive_level_id' => 'nullable|exists:interactive_levels,id',
            'description' => 'sometimes|required|string|min:1|max:30000',
            'name' => 'sometimes|required|string|max:100',
            'closingDate' => 'nullable|date',
            'websiteUrl' => 'nullable|url|max:255',
            'ticketUrl' => 'nullable|url|max:255',
            'show_times' => 'nullable|string|max:500',
            'tag_line' => 'sometimes|required|string|max:255',
            'hasLocation' => 'sometimes|required|boolean',
            'showtype' => 'nullable|string|max:255',
            'embargo_date' => 'nullable|date',
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
            'location.postal_code' => 'sometimes|string|max:20',
            'location.hiddenLocation' => 'nullable|string|max:255',
            'location.hiddenLocationToggle' => 'boolean',
            'location.venue' => 'nullable|string|max:255',
            // Add validation for remotelocations
            'remotelocations' => 'nullable|array',
            'remotelocations.*.name' => 'required|string|max:255',
            // Add validation for dateArray
            'timezone' => 'nullable|string|max:255',
            'dateArray' => 'sometimes|required|array',
            'dateArray.*' => 'sometimes|required|date_format:Y-m-d H:i:s',
            // Add validation for tickets
            'tickets' => 'nullable|array',
            'tickets.*.name' => 'sometimes|required|string|max:40',
            'tickets.*.ticket_price' => 'sometimes|required|numeric|min:0',
            'tickets.*.description' => 'sometimes|nullable|string|max:200',
            'tickets.*.currency' => 'sometimes|required|string|size:1',
            // Add validation for images
            'images' => 'nullable|array',
            'images.*' => 'image|mimes:jpeg,png,jpg,gif,svg,webp|max:2048',
            'ranks' => 'nullable|array',
            'ranks.*' => 'integer|min:0|max:4',
            'currentImages' => 'nullable|json',
            // Add validation for contentAdvisories
            'contentAdvisories' => 'nullable|array',
            'contentAdvisories.*.name' => 'sometimes|string|max:100',
            // Add validation for mobilityAdvisories
            'mobilityAdvisories' => 'nullable|array',
            'mobilityAdvisories.*.name' => 'sometimes|string|max:100',
            'wheelchairReady' => 'boolean',
            // Add validation for contact and interactive levels
            'contactLevel' => 'sometimes|required|array',
            'contactLevel.id' => 'sometimes|required|exists:contact_levels,id',
            'contactLevel.name' => 'sometimes|required|string|max:255',
            
            'interactiveLevel' => 'sometimes|required|array',
            'interactiveLevel.id' => 'sometimes|required|exists:interactive_levels,id',
            'interactiveLevel.name' => 'sometimes|required|string|max:255',
            'interactiveLevel.description' => 'sometimes|required|string',

            // Add audience role validation
            'advisories' => 'sometimes|array',
            'advisories.audience' => 'sometimes|required|string|max:1000',
            'advisories.sexual' => 'sometimes|boolean',
            'advisories.sexualDescription' => 'nullable|string|max:1000',
        ];
    }
}
