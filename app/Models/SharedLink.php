<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class SharedLink extends Model
{
    protected $fillable = [
        'vehicle_id',
        'token',
        'expires_at',
        'is_active',
        'password'
    ];

    // Relación con tu tabla de vehículos
    public function vehicle()
    {
        return $this->belongsTo(Vehicle::class);
    }

    // Método para generar un token único automáticamente
    public static function generateUniqueToken()
    {
        return Str::random(32); // Genera algo como 'aBc123XyZ...'
    }
}