<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Builder;
use Carbon\Carbon;

class Payment extends Model
{
    use HasFactory;

    protected $fillable = [
        'company_id',
        'device_subscription_id',
        'device_id',
        'invoice_id',
        'openpay_charge_id',
        'openpay_order_id',
        'authorization_code',
        'amount',
        'tax',
        'total',
        'currency',
        'type',
        'status',
        'payment_method',
        'description',
        'card_type',
        'card_last_four',
        'card_holder_name',
        'bank_name',
        'error_code',
        'error_message',
        'paid_at',
        'refunded_at',
        'metadata',
        'notes',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'tax' => 'decimal:2',
        'total' => 'decimal:2',
        'paid_at' => 'datetime',
        'refunded_at' => 'datetime',
        'metadata' => 'array',
    ];

    protected $appends = [
        'is_paid',
        'is_failed',
        'is_refunded',
        'has_invoice',
    ];

    // ==================== RELACIONES ====================

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }
    public function subscription()
    {
        return $this->belongsTo(DeviceSubscription::class);
    }

    public function deviceSubscription(): BelongsTo
    {
        return $this->belongsTo(DeviceSubscription::class);
    }

    public function device(): BelongsTo
    {
        return $this->belongsTo(Device::class);
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    // ==================== ACCESSORS ====================

    public function getIsPaidAttribute(): bool
    {
        return $this->status === 'completed';
    }

    public function getIsFailedAttribute(): bool
    {
        return $this->status === 'failed';
    }

    public function getIsRefundedAttribute(): bool
    {
        return $this->status === 'refunded';
    }

    public function getHasInvoiceAttribute(): bool
    {
        return !is_null($this->invoice_id);
    }

    public function getFormattedAmountAttribute(): string
    {
        return '$' . number_format($this->total, 2) . ' ' . $this->currency;
    }

    public function getCardDisplayAttribute(): ?string
    {
        if (!$this->card_last_four) {
            return null;
        }

        $type = $this->card_type ? ucfirst($this->card_type) : 'Card';
        return "{$type} •••• {$this->card_last_four}";
    }

    // ==================== SCOPES ====================

    public function scopeCompleted(Builder $query): Builder
    {
        return $query->where('status', 'completed');
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', 'pending');
    }

    public function scopeFailed(Builder $query): Builder
    {
        return $query->where('status', 'failed');
    }

    public function scopeForCompany(Builder $query, int $companyId): Builder
    {
        return $query->where('company_id', $companyId);
    }

    public function scopeActivations(Builder $query): Builder
    {
        return $query->where('type', 'activation');
    }

    public function scopeRenewals(Builder $query): Builder
    {
        return $query->where('type', 'renewal');
    }

    public function scopeWithoutInvoice(Builder $query): Builder
    {
        return $query->whereNull('invoice_id');
    }

    public function scopeInvoiceable(Builder $query): Builder
    {
        return $query->where('status', 'completed')
            ->whereNull('invoice_id');
    }

    public function scopeInPeriod(Builder $query, Carbon $start, Carbon $end): Builder
    {
        return $query->whereBetween('paid_at', [$start, $end]);
    }

    // ==================== MÉTODOS DE NEGOCIO ====================

    /**
     * Marcar como completado
     */
    public function markAsCompleted(
        string $chargeId,
        ?string $authorizationCode = null,
        ?array $cardInfo = []
    ): bool {
        return $this->update([
            'status' => 'completed',
            'openpay_charge_id' => $chargeId,
            'authorization_code' => $authorizationCode,
            'card_type' => $cardInfo['type'] ?? null,
            'card_last_four' => $cardInfo['last_four'] ?? null,
            'card_holder_name' => $cardInfo['holder_name'] ?? null,
            'bank_name' => $cardInfo['bank_name'] ?? null,
            'paid_at' => now(),
        ]);
    }

    /**
     * Marcar como fallido
     */
    public function markAsFailed(string $errorCode, string $errorMessage): bool
    {
        return $this->update([
            'status' => 'failed',
            'error_code' => $errorCode,
            'error_message' => $errorMessage,
        ]);
    }

    /**
     * Marcar como reembolsado
     */
    public function markAsRefunded(): bool
    {
        return $this->update([
            'status' => 'refunded',
            'refunded_at' => now(),
        ]);
    }

    /**
     * Vincular con factura
     */
    public function attachInvoice(Invoice $invoice): bool
    {
        return $this->update([
            'invoice_id' => $invoice->id,
        ]);
    }

    /**
     * Verificar si puede ser facturado
     */
    public function canBeInvoiced(): bool
    {
        return $this->status === 'completed' && is_null($this->invoice_id);
    }

    // ==================== MÉTODOS ESTÁTICOS ====================

    /**
     * Crear pago para activación
     */
    public static function createForActivation(
        int $companyId,
        int $deviceId,
        ?int $subscriptionId,
        float $amount,
        string $description
    ): self {
        $tax = round($amount * 0.16, 2);

        return self::create([
            'company_id' => $companyId,
            'device_id' => $deviceId,
            'device_subscription_id' => $subscriptionId,
            'amount' => $amount,
            'tax' => $tax,
            'total' => round($amount + $tax, 2),
            'type' => 'activation',
            'status' => 'pending',
            'description' => $description,
        ]);
    }

    /**
     * Crear pago para renovación
     */
    public static function createForRenewal(
        DeviceSubscription $subscription,
        ?string $description = null
    ): self {
        $amount = $subscription->amount;
        $tax = round($amount * 0.16, 2);

        return self::create([
            'company_id' => $subscription->company_id,
            'device_id' => $subscription->device_id,
            'device_subscription_id' => $subscription->id,
            'amount' => $amount,
            'tax' => $tax,
            'total' => round($amount + $tax, 2),
            'type' => 'renewal',
            'status' => 'pending',
            'description' => $description ?? "Renovación GPS - IMEI {$subscription->device->imei}",
        ]);
    }

    /**
     * Total de pagos completados para una compañía
     */
    public static function totalForCompany(int $companyId): float
    {
        return self::where('company_id', $companyId)
            ->where('status', 'completed')
            ->sum('total');
    }

    /**
     * Pagos del mes actual para una compañía
     */
    public static function currentMonthForCompany(int $companyId): float
    {
        return self::where('company_id', $companyId)
            ->where('status', 'completed')
            ->whereMonth('paid_at', now()->month)
            ->whereYear('paid_at', now()->year)
            ->sum('total');
    }
}
