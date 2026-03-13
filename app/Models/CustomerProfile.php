<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CustomerProfile extends Model
{
    use HasFactory;

    // Campos que se pueden llenar masivamente
    protected $fillable = [
        'account_id',
        'rfc',
        'birth_date',
        'gender',
        'phone_primary',
        'phone_secondary',
        'address_home',
        'address_office',
        'emergency_contact_name',
        'emergency_contact_phone',
        'emergency_contact_relationship',
        'job_title',
        'company_name',
    ];

    // Casting de tipos de datos
    protected $casts = [
        'birth_date' => 'date',
    ];

    /**
     * Obtener la cuenta (usuario) a la que pertenece este perfil.
     */
    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'account_id');
    }
}