<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GpsPosition extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'device_id',
        'lat',
        'lng',
        'speed',
        'heading',
        'event',
        'sent_at'
    ];

    protected $casts = [
        'sent_at' => 'datetime'
    ];

    public function device()
    {
        return $this->belongsTo(Device::class);
    }
}
