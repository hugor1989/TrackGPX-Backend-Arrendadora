<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LeasePayment extends Model
{
    use HasFactory;

    protected $fillable = [
        'lease_contract_id',
        'amount',
        'payment_date',
        'reference',
        'month_paid',
        'evidence_path',
        'created_by'
    ];

    /**
     * El contrato al que pertenece este pago.
     */
    public function contract(): BelongsTo
    {
        return $this->belongsTo(LeaseContract::class, 'lease_contract_id');
    }

    public function leaseContract()
    {
        // Verifica que el nombre de la función coincida con lo que pusiste en el controlador
        return $this->belongsTo(LeaseContract::class, 'lease_contract_id');
    }

    /**
     * El usuario (staff de la arrendadora) que registró el pago.
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
