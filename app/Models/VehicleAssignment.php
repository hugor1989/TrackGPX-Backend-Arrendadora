<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VehicleAssignment extends Model
{
    protected $table = 'vehicle_assignments';

    protected $fillable = [
        'vehicle_id',
        'driver_id',
        'assigned_from',
        'assigned_to',
        'active'
    ];

    protected $casts = [
        'assigned_from' => 'date',
        'assigned_to' => 'date',
        'active' => 'boolean'
    ];

    public function vehicle()
    {
        return $this->belongsTo(Vehicle::class);
    }

    public function driver()
    {
        return $this->belongsTo(DriverProfile::class);
    }
}
