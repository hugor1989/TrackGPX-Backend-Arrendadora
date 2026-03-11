<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Group extends Model
{
    use HasFactory;

    protected $fillable = [
        'company_id',
        'name',
        'supervisor_id', // ID del usuario en tabla 'accounts'
        'color',
        'description'
    ];

    /**
     * Relación: Un grupo tiene un Supervisor (Usuario de la plataforma).
     * Nota: Asumo que tu modelo de usuarios se llama 'User'. 
     * Si se llama 'Account', cambia 'User::class' por 'Account::class'.
     */
    public function supervisor()
    {
        return $this->belongsTo(Account::class, 'supervisor_id');
    }

    /**
     * Relación: Un grupo tiene muchos vehículos.
     */
    public function vehicles()
    {
        return $this->hasMany(Vehicle::class);
    }
}