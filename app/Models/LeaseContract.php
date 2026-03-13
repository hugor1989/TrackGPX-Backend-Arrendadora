<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Traits\BelongsToCompany;
USE Carbon\Carbon;
class LeaseContract extends Model
{
    use BelongsToCompany;

    protected $fillable = [
        'company_id',
        'account_id',
        'vehicle_id',
        'contract_number',
        'monthly_amount',
        'payment_day',
        'grace_days',
        'auto_immobilize',
        'is_immobilized',
        'status',
        'down_payment',
        'amount_financed'
    ];

    public function account()
    {
        return $this->belongsTo(Account::class);
    }

    public function vehicle()
    {
        return $this->belongsTo(Vehicle::class);
    }

    public function payments()
    {
        return $this->hasMany(LeasePayment::class);
    }

    public function leasePayments()
    {
        return $this->hasMany(LeasePayment::class, 'lease_contract_id');
    }

    /**
     * Lógica para calcular días de atraso
     * * @return int
     */
    public function calculateDaysOverdue(): int
    {
        // Si el contrato ya está finalizado, no hay atraso activo
        if ($this->status === 'finished') {
            return 0;
        }

        // Definimos la fecha de vencimiento basada en el payment_day
        // Si el día de pago es hoy o ya pasó este mes, el vencimiento es este mes
        // Si no ha llegado el día, el vencimiento fue el mes pasado
        $dueDate = Carbon::now()->day($this->payment_day);

        if ($dueDate->isFuture()) {
            $dueDate->subMonth();
        }

        // Si el contrato es nuevo y aún no llega su primer pago, no hay atraso
        if ($this->created_at->isAfter($dueDate)) {
            return 0;
        }

        // Calculamos la diferencia de días entre el vencimiento y hoy
        $diff = (int) $dueDate->diffInDays(Carbon::now(), false);

        // Si la diferencia es positiva, son días de atraso
        return $diff > 0 ? $diff : 0;
    }
}
