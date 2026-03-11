<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AlertLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'company_id',
        'vehicle_id',
        'alert_rule_id',
        'type',
        'message',
        'latitude',
        'longitude',
        'speed',
        'occurred_at',
        'is_read'
    ];

    protected $casts = [
        'occurred_at' => 'datetime',
        'is_read' => 'boolean',
        'latitude' => 'float',
        'longitude' => 'float',
    ];

    public function vehicle()
    {
        return $this->belongsTo(Vehicle::class);
    }

    public function rule()
    {
        return $this->belongsTo(AlertRule::class, 'alert_rule_id');
    }
}