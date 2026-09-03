<?php

namespace App\Http\Requests;

use App\Support\Currency;
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

    /**
     * Map a currency symbol to the ISO code the column holds. The wizard
     * sends codes, but a browser still running the pre-ISO bundle for the
     * minutes around a deploy sends "$" — mapping it beats rejecting a save
     * for a reason the organizer can't see. Same treatment UpdateEvent gives
     * MCP clients.
     */
    protected function prepareForValidation(): void
    {
        $tickets = $this->input('tickets');

        if (! is_array($tickets)) {
            return;
        }

        foreach ($tickets as $i => $tier) {
            if (is_array($tier) && isset($tier['currency'])) {
                $tickets[$i]['currency'] = Currency::normalize($tier['currency']);
            }
        }

        $this->merge(['tickets' => $tickets]);
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
