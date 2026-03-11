<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Alert extends Model
{
    protected $fillable = [
        'event_id',
        'vehicle_id',
        'company_id',
        'type',
        'message',
        'resolved',
        'resolved_by',
        'triggered_at'
    ];

    protected $casts = [
        'resolved' => 'boolean',
        'triggered_at' => 'datetime'
    ];

    public function event()
    {
        return $this->belongsTo(Event::class);
    }

    public function vehicle()
    {
        return $this->belongsTo(Vehicle::class);
    }

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function resolver()
    {
        return $this->belongsTo(Account::class, 'resolved_by');
    }
}
