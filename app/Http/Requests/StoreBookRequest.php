<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreBookRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title'            => ['required', 'string', 'max:255'],
            'author_id'        => ['required', 'integer', 'exists:authors,id'],
            'isbn'             => ['required', 'string', 'max:20', 'unique:books,isbn'],
            'publication_year' => ['required', 'integer', 'min:1000', 'max:' . date('Y')],
            'summary'          => ['nullable', 'string', 'max:5000'],
            'total_copies'     => ['required', 'integer', 'min:1'],
            'category_id'      => ['required', 'integer', 'exists:categories,id'],
        ];
    }
}
