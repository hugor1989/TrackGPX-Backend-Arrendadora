<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Position extends Model
{
    use HasFactory;

    protected $fillable = [
        'device_id',
        'vehicle_id',
        'latitude',
        'longitude',
        'speed',
        'heading',
        'altitude',
        'accuracy',
        'ignition',
        'attributes', // JSON extra
        'address',
        'timestamp'
    ];

    protected $casts = [
        'timestamp' => 'datetime',
        'ignition' => 'boolean',
        'speed' => 'float',
        'heading' => 'float',
        'latitude' => 'float',
        'longitude' => 'float',
        'altitude' => 'float',
        'attributes' => 'array', // <--- ¡MAGIA! Convierte JSON a Array automáticamente
    ];

    public function vehicle()
    {
        return $this->belongsTo(Vehicle::class);
    }
    
    public function device()
    {
        return $this->belongsTo(Device::class);
    }
}