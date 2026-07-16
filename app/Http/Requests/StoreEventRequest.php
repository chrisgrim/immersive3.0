<?php

namespace App\Http\Requests;

use App\Support\Validation\EventUpdateRules;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class StoreEventRequest extends FormRequest
{
    public function authorize(): bool
    {
        // This request is only used for event updates
        // Verify user can manage the event
        $event = $this->route('event');

        return $event && $this->user() && $this->user()->can('manage', $event);
    }

    public function rules(): array
    {
        return EventUpdateRules::rules();
    }

    public function attributes()
    {
        return EventUpdateRules::attributes();
    }

    public function messages()
    {
        return EventUpdateRules::messages();
    }

    protected function failedValidation(Validator $validator)
    {
        throw new HttpResponseException(response()->json([
            'message' => 'The given data was invalid.',
            'errors' => $validator->errors(),
        ], 422));
    }
}
