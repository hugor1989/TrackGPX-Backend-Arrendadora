<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class NotificationQueue extends Model
{
    protected $table = 'notifications_queue';

    protected $fillable = [
        'user_id',
        'type',
        'title',
        'message',
        'status',
        'attempts',
        'sent_at'
    ];

    protected $casts = [
        'attempts' => 'integer',
        'sent_at' => 'datetime'
    ];

    public function user()
    {
        return $this->belongsTo(Account::class, 'user_id');
    }
}
