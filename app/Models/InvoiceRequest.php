<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InvoiceRequest extends Model
{
    use HasFactory;

    protected $fillable = [
        'company_id', 'billing_cycle_id', 'invoice_id', 'user_id',
        'rfc', 'legal_name', 'fiscal_regime', 'zip_code', 'email',
        'cfdi_use', 'payment_method', 'payment_form',
        'status', 'requested_at', 'processed_at', 'error_message',
    ];

    protected $casts = [
        'requested_at' => 'datetime',
        'processed_at' => 'datetime',
    ];

    // Relaciones
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function billingCycle(): BelongsTo
    {
        return $this->belongsTo(BillingCycle::class);
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    // Scopes
    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }

    // Métodos
    public function markAsCompleted(Invoice $invoice): bool
    {
        return $this->update([
            'status' => 'completed',
            'invoice_id' => $invoice->id,
            'processed_at' => now(),
        ]);
    }

    public function markAsFailed(string $error): bool
    {
        return $this->update([
            'status' => 'failed',
            'error_message' => $error,
            'processed_at' => now(),
        ]);
    }
}