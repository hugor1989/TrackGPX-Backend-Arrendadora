<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Traits\BelongsToCompany;

class Geofence extends Model
{
    use BelongsToCompany;

    protected $fillable = [
        'company_id',
        'name',
        'type',
        'coordinates',
        'radius'
    ];

    protected $casts = [
        'coordinates' => 'array',
        'radius' => 'float'
    ];

    public function vehicles()
    {
        return $this->belongsToMany(Vehicle::class, 'geofence_vehicle');
    }
}
