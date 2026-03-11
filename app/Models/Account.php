<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Laravel\Sanctum\HasApiTokens;

class Account extends Authenticatable
{
    use HasApiTokens;

    protected $fillable = [
        'company_id',
        'name',
        'email',
        'password',
        'status',
    ];

    protected $hidden = ['password'];

    public function company()
    {
        return $this->belongsTo(Company::class);
    }
    
    public function companyUser()
    {
        return $this->hasOne(CompanyUser::class, 'account_id');
    }
    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'account_role');
    }

    public function driverProfile()
    {
        return $this->hasOne(DriverProfile::class);
    }

    public function hasPermission($slug)
    {
        return $this->roles()->whereHas('permissions', function ($q) use ($slug) {
            $q->where('slug', $slug);
        })->exists();
    }
}
