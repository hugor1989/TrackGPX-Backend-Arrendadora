<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Builder;
use Carbon\Carbon;

class DeviceSubscription extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'company_id',
        'device_id',
        'vehicle_id',
        'plan_id',
        'amount',              // ✅ Cambio: monthly_price → amount
        'billing_cycle',       // ✅ Agregar: para identificar monthly/annual
        'currency',
        'status',
        'start_date',
        'end_date',
        'next_billing_date',
        'auto_renew',
        'paused_at',
        'paused_by',
        'pause_reason',
        'canceled_at',
        'canceled_by',
        'cancelation_reason',
        'activated_at',        // ✅ Agregar si lo tienes
        'metadata',            // ✅ Agregar si lo tienes
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'amount' => 'decimal:2',           // ✅ Cambio: monthly_price → amount
        'start_date' => 'date',
        'end_date' => 'date',
        'next_billing_date' => 'date',
        'auto_renew' => 'boolean',
        'paused_at' => 'datetime',
        'canceled_at' => 'datetime',
        'activated_at' => 'datetime',
        'metadata' => 'array',
    ];

    /**
     * The accessors to append to the model's array form.
     *
     * @var array
     */
    protected $appends = [
        'days_to_billing',
        'is_active',
        'is_paused',
        'is_canceled',
        'total_with_tax',      // ✅ Cambio: monthly_total → total_with_tax
        'monthly_price',       // ✅ Nuevo: calcular precio mensual dinámicamente
    ];

    // ============================================
    // RELACIONES
    // ============================================

    /**
     * Get the company that owns the subscription.
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * Get the device that owns the subscription.
     */
    public function device(): BelongsTo
    {
        return $this->belongsTo(Device::class);
    }

    /**
     * Get the vehicle associated with the subscription.
     */
    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }

    /**
     * Get the plan for the subscription.
     */
    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    /**
     * Get the user who paused the subscription.
     */
    public function pausedBy(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'paused_by');
    }

    /**
     * Get the user who canceled the subscription.
     */
    public function canceledBy(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'canceled_by');
    }

    /**
     * Get the invoice items for this subscription.
     */
    public function invoiceItems(): HasMany
    {
        return $this->hasMany(InvoiceItem::class);
    }

    /**
     * Get billing cycles for this subscription.
     */
    public function billingCycles(): HasMany
    {
        return $this->hasMany(BillingCycle::class);
    }

    // ============================================
    // ACCESSORS
    // ============================================

    /**
     * Get days until next billing.
     */
    public function getDaysToBillingAttribute(): int
    {
        return Carbon::parse($this->next_billing_date)->diffInDays(Carbon::today(), false);
    }

    /**
     * Check if subscription is active.
     */
    public function getIsActiveAttribute(): bool
    {
        return $this->status === 'active';
    }

    /**
     * Check if subscription is paused.
     */
    public function getIsPausedAttribute(): bool
    {
        return $this->status === 'paused';
    }

    /**
     * Check if subscription is canceled.
     */
    public function getIsCanceledAttribute(): bool
    {
        return $this->status === 'canceled';
    }

    /**
     * ✅ NUEVO: Calcular precio mensual dinámicamente
     * Si es anual, dividir entre 12
     * Si es mensual, usar el amount directo
     */
    public function getMonthlyPriceAttribute(): float
    {
        if ($this->billing_cycle === 'annual') {
            return round($this->amount / 12, 2);
        }
        
        return (float) $this->amount;
    }

    /**
     * ✅ ACTUALIZADO: Get total including tax
     */
    public function getTotalWithTaxAttribute(): float
    {
        return round($this->amount * 1.16, 2); // IVA 16%
    }

    /**
     * Get days remaining in subscription
     */
    public function getDaysRemainingAttribute(): int
    {
        if (!$this->end_date) {
            return 0;
        }
        
        return max(0, Carbon::today()->diffInDays($this->end_date, false));
    }

    /**
     * Check if subscription is expiring soon
     */
    public function getIsExpiringSoonAttribute(): bool
    {
        return $this->days_remaining > 0 && $this->days_remaining <= 7;
    }

    // ============================================
    // SCOPES
    // ============================================

    /**
     * Scope a query to only include active subscriptions.
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 'active');
    }

    /**
     * Scope a query to only include pending subscriptions.
     */
    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', 'pending');
    }

    /**
     * Scope a query to only include paused subscriptions.
     */
    public function scopePaused(Builder $query): Builder
    {
        return $query->where('status', 'paused');
    }

    /**
     * Scope a query to only include canceled subscriptions.
     */
    public function scopeCanceled(Builder $query): Builder
    {
        return $query->where('status', 'canceled');
    }

    /**
     * Scope a query to only include expired subscriptions.
     */
    public function scopeExpired(Builder $query): Builder
    {
        return $query->where('status', 'expired');
    }

    /**
     * Scope a query for subscriptions of a specific company.
     */
    public function scopeForCompany(Builder $query, int $companyId): Builder
    {
        return $query->where('company_id', $companyId);
    }

    /**
     * Scope a query for subscriptions due for billing.
     */
    public function scopeDueForBilling(Builder $query, ?Carbon $date = null): Builder
    {
        $date = $date ?? Carbon::today();
        
        return $query->where('status', 'active')
                     ->where('next_billing_date', '<=', $date);
    }

    /**
     * Scope a query for subscriptions expiring soon.
     */
    public function scopeExpiringSoon(Builder $query, int $days = 7): Builder
    {
        $futureDate = Carbon::today()->addDays($days);
        
        return $query->where('status', 'active')
                     ->whereBetween('next_billing_date', [Carbon::today(), $futureDate]);
    }

    /**
     * ✅ NUEVO: Scope for due for renewal
     */
    public function scopeDueForRenewal(Builder $query): Builder
    {
        return $query->where('status', 'active')
                     ->where('auto_renew', true)
                     ->where('end_date', '<=', Carbon::today()->addDays(7));
    }

    // ============================================
    // MÉTODOS DE NEGOCIO
    // ============================================

    /**
     * Activate the subscription.
     */
    public function activate(): bool
    {
        if ($this->status === 'active') {
            return false;
        }

        return $this->update([
            'status' => 'active',
            'activated_at' => now(),
        ]);
    }

    /**
     * Pause the subscription.
     */
    public function pause(?int $pausedBy = null, ?string $reason = null): bool
    {
        if ($this->status !== 'active') {
            return false;
        }

        return $this->update([
            'status' => 'paused',
            'paused_at' => now(),
            'paused_by' => $pausedBy,
            'pause_reason' => $reason,
        ]);
    }

    /**
     * Resume a paused subscription.
     */
    public function resume(): bool
    {
        if ($this->status !== 'paused') {
            return false;
        }

        // Extender la fecha de siguiente cobro
        $pausedDays = $this->paused_at->diffInDays(now());
        $newBillingDate = Carbon::parse($this->next_billing_date)->addDays($pausedDays);

        return $this->update([
            'status' => 'active',
            'paused_at' => null,
            'paused_by' => null,
            'pause_reason' => null,
            'next_billing_date' => $newBillingDate,
        ]);
    }

    /**
     * Cancel the subscription.
     */
    public function cancel(?int $canceledBy = null, ?string $reason = null): bool
    {
        if ($this->status === 'canceled') {
            return false;
        }

        return $this->update([
            'status' => 'canceled',
            'canceled_at' => now(),
            'canceled_by' => $canceledBy,
            'cancelation_reason' => $reason,
            'end_date' => Carbon::today(),
            'auto_renew' => false,
        ]);
    }

    /**
     * ✅ ACTUALIZADO: Renew the subscription
     */
    public function renew(): bool
    {
        if ($this->status === 'canceled') {
            return false;
        }

        // Determinar el nuevo periodo
        if ($this->billing_cycle === 'annual') {
            $newEndDate = Carbon::parse($this->end_date)->addYear();
            $newBillingDate = $newEndDate;
        } else {
            $newEndDate = Carbon::parse($this->end_date)->addMonth();
            $newBillingDate = $newEndDate;
        }

        return $this->update([
            'status' => 'active',
            'end_date' => $newEndDate,
            'next_billing_date' => $newBillingDate,
        ]);
    }

    /**
     * Update next billing date (usually after successful payment).
     */
    public function updateNextBillingDate(?Carbon $date = null): bool
    {
        if ($this->billing_cycle === 'annual') {
            $nextDate = $date ?? Carbon::parse($this->next_billing_date)->addYear();
        } else {
            $nextDate = $date ?? Carbon::parse($this->next_billing_date)->addMonth();
        }
        
        return $this->update([
            'next_billing_date' => $nextDate,
        ]);
    }

    /**
     * ✅ ACTUALIZADO: Change subscription plan.
     */
    public function changePlan(Plan $plan): bool
    {
        if ($this->status !== 'active') {
            return false;
        }

        // Calcular el precio según el billing_cycle
        $newAmount = $plan->price;

        return $this->update([
            'plan_id' => $plan->id,
            'amount' => $newAmount,
        ]);
    }

    /**
     * ✅ ACTUALIZADO: Calculate prorated amount for partial period.
     */
    public function calculateProratedAmount(?Carbon $startDate = null, ?Carbon $endDate = null): float
    {
        $start = $startDate ?? $this->start_date;
        $end = $endDate ?? Carbon::today();
        
        if ($this->billing_cycle === 'annual') {
            $daysInYear = $start->daysInYear;
            $daysUsed = $start->diffInDays($end) + 1;
            return round(($this->amount / $daysInYear) * $daysUsed, 2);
        }
        
        // Monthly
        $daysInMonth = $start->daysInMonth;
        $daysUsed = $start->diffInDays($end) + 1;
        
        return round(($this->amount / $daysInMonth) * $daysUsed, 2);
    }

    /**
     * Calculate next billing date based on billing cycle
     */
    public function calculateNextBillingDate(?Carbon $from = null): Carbon
    {
        $from = $from ?? $this->end_date ?? now();

        if ($this->billing_cycle === 'annual') {
            return Carbon::parse($from)->addYear();
        }

        return Carbon::parse($from)->addMonth();
    }

    /**
     * Get billing history for this subscription.
     */
    public function getBillingHistory()
    {
        return $this->invoiceItems()
                    ->with('invoice')
                    ->latest()
                    ->get();
    }

    /**
     * Check if subscription needs renewal.
     */
    public function needsRenewal(): bool
    {
        return $this->is_active 
               && $this->auto_renew 
               && $this->next_billing_date <= Carbon::today();
    }

    // ============================================
    // MÉTODOS ESTÁTICOS
    // ============================================

    /**
     * Get active subscriptions count for a company.
     */
    public static function activeCountForCompany(int $companyId): int
    {
        return static::where('company_id', $companyId)
                     ->where('status', 'active')
                     ->count();
    }

    /**
     * ✅ ACTUALIZADO: Get monthly revenue for a company.
     */
    public static function monthlyRevenueForCompany(int $companyId): float
    {
        return static::where('company_id', $companyId)
                     ->where('status', 'active')
                     ->sum('amount');
    }

    /**
     * ✅ ACTUALIZADO: Create subscription from device and plan.
     */
    public static function createFromDevice(
        Device $device, 
        Plan $plan,
        string $billingCycle = 'monthly',
        ?int $vehicleId = null
    ): self {
        // Calcular fechas según el ciclo
        $startDate = Carbon::today();
        
        if ($billingCycle === 'annual') {
            $endDate = $startDate->copy()->addYear();
        } else {
            $endDate = $startDate->copy()->addMonth();
        }

        return static::create([
            'company_id' => $device->company_id,
            'device_id' => $device->id,
            'vehicle_id' => $vehicleId ?? $device->vehicle_id,
            'plan_id' => $plan->id,
            'amount' => $plan->price,        // ✅ Precio del plan
            'billing_cycle' => $billingCycle, // ✅ monthly o annual
            'currency' => $plan->currency ?? 'MXN',
            'status' => 'pending',            // Se activa después del pago
            'start_date' => $startDate,
            'end_date' => $endDate,
            'next_billing_date' => $endDate,
            'auto_renew' => true,
        ]);
    }
}