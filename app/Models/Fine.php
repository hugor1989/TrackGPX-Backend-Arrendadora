<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Fine extends Model
{
    protected $table = 'fines';

    protected $fillable = [
        'vehicle_id',
        'company_id',
        'source',
        'reference',
        'description',
        'amount',
        'status',
        'detected_at'
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'detected_at' => 'datetime',
        'paid_at' => 'date',
    ];

    public function vehicle()
    {
        return $this->belongsTo(Vehicle::class);
    }

    public function company()
    {
        return $this->belongsTo(Company::class);
    }
}
