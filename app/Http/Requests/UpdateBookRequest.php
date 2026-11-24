<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateBookRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title'            => ['sometimes', 'required', 'string', 'max:255'],
            'author_id'        => ['sometimes', 'required', 'integer', 'exists:authors,id'],
            'isbn'             => [
                'sometimes', 'required', 'string', 'max:20',
                Rule::unique('books', 'isbn')->ignore($this->route('book')),
            ],
            'publication_year' => ['sometimes', 'required', 'integer', 'min:1000', 'max:' . date('Y')],
            'summary'          => ['sometimes', 'nullable', 'string', 'max:5000'],
            'total_copies'     => ['sometimes', 'required', 'integer', 'min:1'],
            'category_id'      => ['sometimes', 'required', 'integer', 'exists:categories,id'],
        ];
    }
}
