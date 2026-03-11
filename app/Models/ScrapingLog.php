<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ScrapingLog extends Model
{
    protected $table = 'scraping_logs';

    protected $fillable = [
        'vehicle_id',
        'state',
        'action',
        'result',
        'raw_data',
        'executed_at'
    ];

    protected $casts = [
        'raw_data' => 'array',
        'executed_at' => 'datetime'
    ];

    public function vehicle()
    {
        return $this->belongsTo(Vehicle::class);
    }
}
