<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TelemetryDaily extends Model
{
    protected $table = 'telemetry_daily';

    protected $fillable = [
        'vehicle_id',
        'date',
        'total_distance',
        'total_idle_time',
        'avg_speed',
        'max_speed',
        'events_count'
    ];

    protected $casts = [
        'date' => 'date',
        'total_distance' => 'decimal:2',
        'avg_speed' => 'float',
        'max_speed' => 'float'
    ];

    public function vehicle()
    {
        return $this->belongsTo(Vehicle::class);
    }
}
