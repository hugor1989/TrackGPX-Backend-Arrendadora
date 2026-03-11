<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CompanyBillingInfo extends Model
{
    use HasFactory;

    protected $table = 'company_billing_info';

    protected $fillable = [
        'company_id', 'rfc',
        'legal_name', 
        'fiscal_regime', 
        'tax_regime',
        'postal_code', 
        'email_for_invoices', 
        'phone', 
        'street',
        'exterior_number',
        'interior_number', 
        'neighborhood', 
        'city', 
        'state',
         'country',
        'cfdi_use', 
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    // Relaciones
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    // Métodos
    public function isComplete(): bool
    {
        return !empty($this->rfc) &&
               !empty($this->legal_name) &&
               !empty($this->fiscal_regime) &&
               !empty($this->postal_code) &&
               !empty($this->email_for_invoices);
    }

    public function getFullAddress(): string
    {
        $parts = array_filter([
            $this->street,
            $this->exterior_number,
            $this->interior_number ? "Int. {$this->interior_number}" : null,
            $this->neighborhood,
            $this->city,
            $this->state,
            $this->postal_code,
            $this->country,
        ]);

        return implode(', ', $parts);
    }
}