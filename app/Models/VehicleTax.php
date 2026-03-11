<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VehicleTax extends Model
{
    protected $table = 'vehicle_taxes';

    protected $fillable = [
        'vehicle_id',
        'year',
        'state',
        'amount',
        'due_date',
        'paid',
        'last_checked_at'
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'due_date' => 'date',
        'paid' => 'boolean',
        'last_checked_at' => 'datetime'
    ];

    public function vehicle()
    {
        return $this->belongsTo(Vehicle::class);
    }
}
