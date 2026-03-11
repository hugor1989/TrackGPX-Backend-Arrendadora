<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class VehicleExpense extends Model
{
    use HasFactory;

    protected $fillable = ['vehicle_id', 'date', 'type', 'amount', 'description', 'odometer', 'liters', 'price_per_liter', 'attachment'];

    protected $casts = [
        'date' => 'date',
        'amount' => 'decimal:2'
    ];

    public function vehicle()
    {
        return $this->belongsTo(Vehicle::class);
    }
}