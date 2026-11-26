<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SmartSearchRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'description' => ['required', 'string', 'min:3', 'max:500'],
            'limit'       => ['sometimes', 'integer', 'min:1', 'max:20'],
        ];
    }
}
