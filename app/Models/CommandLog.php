<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Casts\Attribute;

class CommandLog extends Model
{
    protected $fillable = [
        'vehicle_id',
        'user_id',
        'command_type',
        'action',
        'reason',
        'status',
        'error_message',
        'metadata'
    ];

    protected $casts = [
        'metadata' => 'array',
        'status' => 'boolean'
    ];

    public function vehicle()
    {
        return $this->belongsTo(Vehicle::class);
    }


    /**
     * Crea el atributo virtual 'status_label'
     */
    protected function statusLabel(): Attribute
    {
        return Attribute::make(
            get: fn() => $this->status ? 'Exitoso' : 'Fallido',
        );
    }

    /**
     * Crea el atributo virtual 'action_label'
     */
    protected function actionLabel(): Attribute
    {
        return Attribute::make(
            get: fn() => $this->action === 'stop' ? 'Bloqueo de Motor' : 'Reactivación de Motor',
        );
    }

    // Esto es para que Laravel los incluya siempre en el JSON que mandas al Front
    protected $appends = ['status_label', 'action_label'];
}
