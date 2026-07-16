<?php

namespace App\Http\Requests;

use App\Support\Validation\OrganizerRules;
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

        // On create there is no bound {organizer}, so name + description are required.
        // On update we only validate the fields actually being changed (partial update).
        $isCreating = $this->route('organizer') === null;

        if ($isCreating || $this->has('name')) {
            $rules['name'] = OrganizerRules::name();
        }

        if ($isCreating || $this->has('description')) {
            $rules['description'] = OrganizerRules::description();
        }

        // Only apply image rules if image is being updated
        if ($this->hasFile('image')) {
            $rules['image'] = 'required|image|mimes:jpeg,png,jpg,webp|max:8192';
        }

        // Social media and contact rules - always validate if present
        $rules += OrganizerRules::contact();

        return $rules;
    }

    public function messages(): array
    {
        return OrganizerRules::messages();
    }
}
