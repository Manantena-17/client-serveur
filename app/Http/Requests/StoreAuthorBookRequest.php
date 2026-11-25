<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreAuthorBookRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title'            => ['required', 'string', 'max:255'],
            'isbn'             => ['required', 'string', 'max:20', 'unique:books,isbn'],
            'publication_year' => ['required', 'integer', 'min:1000', 'max:' . date('Y')],
            'total_copies'     => ['required', 'integer', 'min:1'],
            'category_id'      => ['required', 'integer', 'exists:categories,id'],
        ];
    }
}
