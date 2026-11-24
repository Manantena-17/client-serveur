<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateAuthorRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'first_name'  => ['sometimes', 'required', 'string', 'max:100'],
            'last_name'   => ['sometimes', 'required', 'string', 'max:100'],
            'nationality' => ['sometimes', 'nullable', 'string', 'max:100'],
        ];
    }
}
