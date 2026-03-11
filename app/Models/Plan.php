<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Plan extends Model
{
    protected $fillable = [
        'openpay_plan_id',
        'name',
        'description',
        'price',
        'currency',
        'interval',
        'interval_count',
        'features',
        'status',
        'sat_product_code',
        'max_vehicles',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'interval_count' => 'integer',
        'max_vehicles' => 'integer',
        'features' => 'array',
    ];

    // Relaciones
    public function subscriptions()
    {
        return $this->hasMany(DeviceSubscription::class);
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopeMonthly($query)
    {
        return $query->where('interval', 'month')->where('interval_count', 1);
    }

    public function scopeAnnual($query)
    {
        return $query->where('interval', 'year')->where('interval_count', 1);
    }

    // Métodos útiles
    public function getIntervalTextAttribute(): string
    {
        $intervals = [
            'day' => 'día',
            'week' => 'semana',
            'month' => 'mes',
            'year' => 'año',
        ];

        $interval = $intervals[$this->interval] ?? $this->interval;
        
        return $this->interval_count > 1 
            ? "{$this->interval_count} {$interval}s"
            : $interval;
    }

    public function getPriceFormatAttribute(): string
    {
        return '$' . number_format($this->price, 2) . ' ' . $this->currency;
    }
}