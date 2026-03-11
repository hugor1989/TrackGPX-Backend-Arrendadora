<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreCompanyRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Aquí puedes meter permisos más tarde
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'slug' => 'nullable|unique:companies,slug',
            'rfc' => 'required|string|max:50|unique:companies,rfc',
            'fiscal_address' => 'required|string',
            'contact_email' => 'required|email',
            'phone' => 'required|string|max:30',
        ];
    }

    public function messages()
    {
        return [
            'slug.unique' => 'El slug ya está en uso.',
            'name.required' => 'El nombre es obligatorio.',
            'rfc.required' => 'El RFC es obligatorio.',
            // agrega más si quieres
        ];
    }
}
