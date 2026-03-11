<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AlertRule extends Model
{
    use HasFactory;

    protected $fillable = [
        'company_id',
        'name',
        'type',
        'geofence_id',
        'value',
        'notification_settings',
        'schedule_settings', // <--- Nuevo campo
        'is_active',
    ];

    protected $casts = [
        'notification_settings' => 'array',
        'schedule_settings' => 'array', // <--- Importante para guardar horarios JSON
        'value' => 'float',
        'is_active' => 'boolean',
    ];

    public function geofence()
    {
        return $this->belongsTo(Geofence::class);
    }

    public function vehicles()
    {
        return $this->belongsToMany(Vehicle::class, 'alert_rule_vehicle')
                    ->withTimestamps();
    }
}