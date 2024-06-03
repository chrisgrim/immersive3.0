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
            'name' => 'sometimes|required|string|max:255',
            'closingDate' => 'nullable|date',
            'websiteUrl' => 'nullable|url|max:255',
            'ticketUrl' => 'nullable|url|max:255',
            'show_times' => 'nullable|string',
            'tag_line' => 'nullable|string|max:255',
            'hasLocation' => 'sometimes|required|boolean',
            'showtype' => 'nullable|string|max:255',
            'embargo_date' => 'nullable|date',
            'remote_description' => 'nullable|string|max:3000',
            'call_to_action' => 'nullable|string|max:255',
            'video' => 'nullable|string|max:255',
            'archived' => 'nullable|boolean'
        ];
    }
}
