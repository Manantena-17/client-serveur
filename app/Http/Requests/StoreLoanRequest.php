<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreLoanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'member_id'      => ['required', 'integer', 'exists:members,id'],
            'book_id'        => ['required', 'integer', 'exists:books,id'],
            'due_at'         => ['nullable', 'date', 'after:today'],
            'payment_status' => ['sometimes', 'in:pending,paid,free'],
        ];
    }
}
