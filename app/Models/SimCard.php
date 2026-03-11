<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Traits\BelongsToCompany;

class SimCard extends Model
{
    use BelongsToCompany;

    protected $fillable = [
        'company_id',
        'phone_number',
        'carrier',
        'apn',
    ];

    public function device()
    {
        return $this->hasOne(Device::class);
    }
}
