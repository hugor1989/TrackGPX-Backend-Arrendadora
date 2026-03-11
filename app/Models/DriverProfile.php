<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DriverProfile extends Model
{
    protected $table = 'drivers';

    protected $fillable = [
        'account_id',
        'company_id',
        'name',
        'license_number',
        'phone',
        'emergency_contact',
        'mobile_uuid',
    ];

    public $timestamps = true;

    // Relaciones
    public function account()
    {
        return $this->belongsTo(Account::class);
    }

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function assignments()
    {
        return $this->hasMany(VehicleAssignment::class);
    }

    public function trips()
    {
        return $this->hasMany(Trip::class);
    }

    // ✅ AGREGA ESTO: Relación "Tiene un Vehículo Actual"
    public function currentVehicle()
    {
        return $this->hasOneThrough(
            Vehicle::class,           // Modelo Final (Lo que queremos)
            VehicleAssignment::class, // Modelo Intermedio (Donde buscamos)
            'driver_id',              // FK en Asignaciones (hacia Driver)
            'id',                     // FK en Vehículos (match con vehicle_id de asignación)
            'id',                     // PK en Driver
            'vehicle_id'              // FK en Asignaciones (hacia Vehicle)
        )->where('vehicle_assignments.active', true);
    }
    public function vehicleHistory()
    {
        return $this->hasMany(VehicleAssignment::class, 'driver_id');
    }
}
