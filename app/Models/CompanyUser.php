<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CompanyUser extends Model
{
    protected $table = 'company_users';

    protected $fillable = [
        'account_id',
        'company_id',
        'name',
        'phone',
        'position',
        'timezone',
    ];

    public $timestamps = true;

    // Relaciones
    public function account()
    {
        return $this->belongsTo(Account::class);
    }

    public function company()
    {
        return $this->belongsTo(Company::class);
    }
}
