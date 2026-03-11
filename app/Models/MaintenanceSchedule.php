<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MaintenanceSchedule extends Model
{
    use HasFactory;

    protected $fillable = [
        'vehicle_id',
        'name',
        'interval_km',
        'interval_days',
        'last_service_odometer',
        'last_service_date',
        'is_active',
        'notify_driver',
        'notify_supervisor'
    ];

    // Para saber cuándo toca el siguiente (Calculado)
    public function getNextServiceOdometerAttribute()
    {
        return $this->interval_km ? ($this->last_service_odometer + $this->interval_km) : null;
    }
}