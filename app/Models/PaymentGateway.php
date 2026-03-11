<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PaymentGateway extends Model
{
    protected $fillable = [
        'provider',
        'mode',
        'account_id',
        'public_key',
        'last4',
        'active'
    ];
}
