<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Event extends Model
{
    protected $fillable = [
        'device_id',
        'vehicle_id',
        'type',
        'data',
        'triggered_at'
    ];

    protected $casts = [
        'data' => 'array',
        'triggered_at' => 'datetime'
    ];

    public function device()
    {
        return $this->belongsTo(Device::class);
    }

    public function vehicle()
    {
        return $this->belongsTo(Vehicle::class);
    }

    public function alerts()
    {
        return $this->hasMany(Alert::class);
    }
}
