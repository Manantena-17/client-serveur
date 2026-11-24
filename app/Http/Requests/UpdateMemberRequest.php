<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateMemberRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => [
                'required_without_all:email,password,type,subscription_status',
                'string', 'max:255',
            ],
            'email' => [
                'required_without_all:name,password,type,subscription_status',
                'email', 'max:255',
                Rule::unique('members', 'email')->ignore($this->route('member')),
            ],
            'password' => [
                'required_without_all:name,email,type,subscription_status',
                'string', 'min:8', 'max:255',
            ],
            'type' => [
                'required_without_all:name,email,password,subscription_status',
                'in:student,teacher',
            ],
            'subscription_status' => [
                'required_without_all:name,email,password,type',
                'in:free,premium',
            ],
        ];
    }
}
