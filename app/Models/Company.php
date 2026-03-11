<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Company extends Model
{
    protected $fillable = [
        'name',
        'slug',
        'rfc',
        'fiscal_address',
        'contact_email',
        'phone',
        'status',
        'website',
        'logo',
        'openpay_customer_id',
    ];

    public function accounts()
    {
        return $this->hasMany(Account::class);
    }

    /**
     * Relación: Una empresa tiene muchos logs de alertas.
     */
    public function alertLogs()
    {
        return $this->hasMany(AlertLog::class);
    }
    /**
     * Relación: Una empresa tiene muchas reglas de alerta.
     */
    public function alertRules()
    {
        return $this->hasMany(AlertRule::class);
    }
    public function geofences()
    {
        return $this->hasMany(Geofence::class);
    }
    public function devices()
    {
        return $this->hasMany(Device::class);
    }
    public function vehicles()
    {
        return $this->hasMany(Vehicle::class);
    }

    public function drivers()
    {
        return $this->hasMany(DriverProfile::class);
    }

    public function simCards()
    {
        return $this->hasMany(SimCard::class);
    }

    public function trips()
    {
        return $this->hasManyThrough(
            Trip::class,
            Vehicle::class,
            'company_id',
            'vehicle_id'
        );
    }


    public function deviceSubscriptions()
    {
        return $this->hasMany(DeviceSubscription::class);
    }

    public function billingCycles()
    {
        return $this->hasMany(BillingCycle::class);
    }

    public function invoices()
    {
        return $this->hasMany(Invoice::class);
    }

    public function billingInfo()
    {
        return $this->hasOne(CompanyBillingInfo::class);
    }

    // ==================== MÉTODOS ÚTILES ====================

    /**
     * Verificar si tiene customer en OpenPay
     */
    public function hasOpenPayCustomer(): bool
    {
        return !empty($this->openpay_customer_id);
    }

    /**
     * Verificar si tiene métodos de pago configurados
     */
    public function hasPaymentMethods(): bool
    {
        if (!$this->hasOpenPayCustomer()) {
            return false;
        }

        // Aquí puedes hacer una llamada a OpenPay para verificar
        // Por ahora solo verificamos que tenga customer_id
        return true;
    }

    /**
     * Verificar si tiene información de facturación completa
     */
    public function hasBillingInfoComplete(): bool
    {
        return $this->billingInfo && $this->billingInfo->isComplete();
    }

    /**
     * Verificar si puede ser facturada
     */
    public function canBeBilled(): bool
    {
        return $this->hasOpenPayCustomer() &&
            $this->hasPaymentMethods() &&
            $this->hasBillingInfoComplete();
    }

    public function payments()
    {
        return $this->hasMany(Payment::class);
    }
}
