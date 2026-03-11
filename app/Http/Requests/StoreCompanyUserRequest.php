<?php
namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreCompanyUserRequest extends FormRequest
{
    public function authorize()
    {
        // Se controla con policy/role en controller o route middleware
        return true;
    }

    public function rules()
    {
        return [
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:accounts,email',
            'phone' => 'nullable|string',
            'position' => 'nullable|string|max:120',
            'timezone' => 'nullable|string|max:100',
            'role' => 'nullable|string' // slug del role a asignar
        ];
    }
}
