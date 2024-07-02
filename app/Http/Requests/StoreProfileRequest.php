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
            // Add any other validation rules as needed
        ];
    }
}
