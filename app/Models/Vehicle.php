<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Traits\BelongsToCompany;
use App\Models\DriverProfile;
use App\Models\VehicleAssignment;
use App\Models\Device;
use App\Models\Trip;
use App\Models\Geofence;

class Vehicle extends Model
{
    use BelongsToCompany;

    protected $fillable = [
        'company_id',
        'device_id',
        'name',
        'type',
        'brand',
        'model',
        'year',
        'plate',
        'insurance_company',
        'policy_number',
        'policy_expiry',
        'policy_document_url',
        'last_service_date',
        'last_service_odometer',
        'service_interval',
        'next_service_type',
        'map_icon',
    ];

    // En Vehicle.php
    public function alertRules()
    {
        return $this->belongsToMany(AlertRule::class, 'alert_rule_vehicle');
    }

    /**
     * Historial completo de asignaciones del vehículo.
     */
    public function assignments()
    {
        return $this->hasMany(VehicleAssignment::class);
    }

    /**
     * Asignación activa actual del vehículo.
     */
    public function currentAssignment()
    {
        return $this->hasOne(VehicleAssignment::class)
            ->where('active', true);
    }

    /**
     * Conductor activo actual (relación directa a DriverProfile).
     */
    public function customer()
    {
        // Ahora la relación es directa a Account a través de la pivote limpia
        return $this->hasOneThrough(
            Account::class,
            VehicleAssignment::class,
            'vehicle_id', // FK en pivote
            'id',         // PK en accounts
            'id',         // PK en vehicles
            'account_id'  // FK en pivote
        )->where('vehicle_assignments.active', true);
    }

    public function group()
    {
        return $this->belongsTo(Group::class);
    }

    /**
     * Dispositivo GPS asociado al vehículo.
     */
    public function device()
    {
        return $this->hasOne(Device::class);
    }

    /**
     * Viajes asociados al vehículo.
     */
    public function trips()
    {
        return $this->hasMany(Trip::class);
    }

    /**
     * Geocercas a las que pertenece el vehículo.
     */
    public function geofences()
    {
        return $this->belongsToMany(Geofence::class, 'geofence_vehicle');
    }

    public function lastPosition()
    {
        return $this->hasOne(\App\Models\Position::class)
            ->latest('timestamp');
    }

    public function leaseContracts()
    {
        // Asumiendo que tu modelo de contrato se llama LeaseContract
        // y que la llave foránea en esa tabla es vehicle_id
        return $this->hasMany(LeaseContract::class, 'vehicle_id');
    }

    public function currentLease()
    {
        return $this->hasOne(LeaseContract::class, 'vehicle_id')->latest();
    }

    public function currentCustomer()
    {
        return $this->hasOneThrough(Account::class, LeaseContract::class, 'vehicle_id', 'id', 'id', 'account_id');
    }
}
