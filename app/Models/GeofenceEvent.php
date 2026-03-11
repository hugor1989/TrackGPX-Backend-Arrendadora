<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class GeofenceEvent extends Model
{
    use HasFactory;

    protected $table = 'geofence_events';

    protected $fillable = [
        'device_id',
        'geofence_id',
        'event_type',
        'latitude',
        'longitude',
        'event_time',
    ];

    protected $casts = [
        'event_time' => 'datetime',
    ];

    // 🔗 Relación con el dispositivo
    public function device()
    {
        return $this->belongsTo(Device::class);
    }

    // 🔗 Relación con la geocerca
    public function geofence()
    {
        return $this->belongsTo(Geofence::class);
    }
}
