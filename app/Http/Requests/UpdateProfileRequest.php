<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name'  => ['required_without_all:email,password', 'string', 'max:255'],
            'email' => [
                'required_without_all:name,password',
                'email', 'max:255',
                Rule::unique('members', 'email')->ignore($this->user()->id),
            ],
            'password' => [
                'required_without_all:name,email',
                'string', 'min:8', 'max:255', 'confirmed',
            ],
        ];
    }
}
