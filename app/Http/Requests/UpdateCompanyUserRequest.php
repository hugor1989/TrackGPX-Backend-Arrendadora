<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateCompanyUserRequest extends FormRequest
{
    public function authorize()
    {
        return true; // ya se valida en middleware o controller
    }

    public function rules()
    {
        return [
            'name' => 'sometimes|string|max:255',
            'email' => 'sometimes|email|unique:accounts,email,' . $this->input('account_id'),
            'phone' => 'nullable|string|max:50',
            'position' => 'nullable|string|max:120',
            'timezone' => 'nullable|string|max:100',
            'role' => 'nullable|string' // slug del rol
        ];
    }
}
