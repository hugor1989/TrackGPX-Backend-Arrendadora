<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BillingCycle extends Model
{
    use HasFactory;

    protected $fillable = [
        'company_id', 'period_start', 'period_end', 'total_devices', 'active_days',
        'subtotal', 'tax', 'discount', 'total', 'currency', 'status',
        'openpay_customer_id', 'openpay_card_id', 'openpay_transaction_id', 'openpay_charge_id',
        'charged_at', 'invoice_id', 'invoice_requested', 'charge_attempt_count',
        'last_charge_error', 'next_retry_at',
    ];

    protected $casts = [
        'period_start' => 'date',
        'period_end' => 'date',
        'total_devices' => 'integer',
        'active_days' => 'integer',
        'subtotal' => 'decimal:2',
        'tax' => 'decimal:2',
        'discount' => 'decimal:2',
        'total' => 'decimal:2',
        'invoice_requested' => 'boolean',
        'charged_at' => 'datetime',
        'charge_attempt_count' => 'integer',
        'next_retry_at' => 'datetime',
    ];

    protected $appends = ['is_charged', 'can_retry'];

    // Relaciones
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function subscriptions(): HasMany
    {
        return $this->company->deviceSubscriptions()
            ->whereBetween('start_date', [$this->period_start, $this->period_end]);
    }

    // Scopes
    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeFailed($query)
    {
        return $query->where('status', 'failed');
    }

    public function scopeReadyForRetry($query)
    {
        return $query->where('status', 'failed')
            ->where('next_retry_at', '<=', now())
            ->where('charge_attempt_count', '<', 3);
    }

    // Accessors
    public function getIsChargedAttribute(): bool
    {
        return $this->status === 'charged';
    }

    public function getCanRetryAttribute(): bool
    {
        return $this->status === 'failed' && 
               $this->charge_attempt_count < 3 &&
               ($this->next_retry_at === null || $this->next_retry_at->isPast());
    }

    // Métodos
    public function markAsCharged(array $openPayData): bool
    {
        return $this->update(array_merge([
            'status' => 'charged',
            'charged_at' => now(),
        ], $openPayData));
    }

    public function markAsFailed(string $error): bool
    {
        return $this->update([
            'status' => 'failed',
            'last_charge_error' => $error,
            'charge_attempt_count' => $this->charge_attempt_count + 1,
            'next_retry_at' => now()->addHours(24 * $this->charge_attempt_count),
        ]);
    }

    public function calculateTotals(): void
    {
        $activeSubscriptions = DeviceSubscription::where('company_id', $this->company_id)
            ->active()
            ->whereBetween('start_date', [$this->period_start, $this->period_end])
            ->get();

        $this->total_devices = $activeSubscriptions->count();
        
        $subtotal = 0;
        foreach ($activeSubscriptions as $subscription) {
            $subtotal += $subscription->calculateProratedAmount($this->period_start, $this->period_end);
        }

        $this->subtotal = round($subtotal, 2);
        $this->tax = round($this->subtotal * 0.16, 2);
        $this->total = round($this->subtotal + $this->tax - $this->discount, 2);
    }
}