<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreMemberRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name'                => ['required', 'string', 'max:255'],
            'email'               => ['required', 'email', 'max:255', 'unique:members,email'],
            'password'            => ['nullable', 'string', 'min:8', 'max:255'],
            'type'                => ['required', 'in:student,teacher'],
            'subscription_status' => ['sometimes', 'in:free,premium'],
        ];
    }
}
