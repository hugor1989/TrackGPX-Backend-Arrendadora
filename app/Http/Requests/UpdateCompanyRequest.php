<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateCompanyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => 'sometimes|string|max:255',
            'slug' => 'sometimes|string',
            'rfc' => 'sometimes|string|max:50|unique:companies,rfc,' . $this->id,
            'logo' => 'nullable|image|mimes:jpeg,png,jpg,webp|max:5120',  // ← NUEVO
            'website' => 'sometimes|string',
            'fiscal_address' => 'sometimes|string',
            'contact_email' => 'sometimes|email',
            'phone' => 'sometimes|string|max:30',
            'status' => 'sometimes|in:active,suspended'
        ];
    }
}
