<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Laravel\Sanctum\HasApiTokens;
use App\Models\CustomerProfile;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\HasOneThrough;

class Account extends Authenticatable
{
    use HasApiTokens;

    protected $fillable = [
        'company_id',
        'name',
        'email',
        'password',
        'status',
        'role',
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

    public function customerProfile()
    {
        return $this->hasOne(CustomerProfile::class, 'account_id');
    }

    public function riskScore(): HasOne
    {
        return $this->hasOne(CustomerRiskScore::class, 'account_id');
    }

    public function leaseContract(): HasOne
    {
        return $this->hasOne(LeaseContract::class, 'account_id');
    }

    public function latestPosition(): HasOneThrough
    {
        return $this->hasOneThrough(
            Position::class,
            VehicleAssignment::class,
            'account_id', // Llave foránea en VehicleAssignment
            'vehicle_id', // Llave foránea en Position
            'id',         // Llave local en Account
            'vehicle_id'  // Llave local en VehicleAssignment
        )->where('active', true)->latest('timestamp');
    }
}
