<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SyncLog extends Model
{
    protected $table = 'sync_logs';

    protected $fillable = [
        'user_id',
        'device_type',
        'action',
        'endpoint',
        'status_code',
        'duration_ms'
    ];

    public function user()
    {
        return $this->belongsTo(Account::class, 'user_id');
    }
}
