<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class GenerateBookSummaryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // optional: ?force=1 regenerates an existing summary
            'force' => ['sometimes', 'boolean'],
        ];
    }
}
