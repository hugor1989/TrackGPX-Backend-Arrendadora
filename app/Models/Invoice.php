<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Invoice extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'company_id', 'billing_cycle_id', 'invoice_number', 'invoice_date', 'due_date',
        'subtotal', 'tax', 'discount', 'total', 'currency', 'status',
        'paid_at', 'payment_method', 'payment_reference', 'notes', 'internal_notes',
        // CFDI fields
        'cfdi_uuid', 'cfdi_folio', 'cfdi_serie', 'cfdi_xml_path', 'cfdi_pdf_path',
        'cfdi_original_string', 'cfdi_sat_seal', 'cfdi_cfdi_seal', 'cfdi_sat_cert_number',
        'cfdi_stamp_date', 'pac_name', 'pac_rfc',
        'issuer_rfc', 'issuer_name', 'issuer_fiscal_regime',
        'receiver_rfc', 'receiver_name', 'receiver_fiscal_regime', 'receiver_zip_code', 'receiver_tax_regime',
        'cfdi_use', 'cfdi_payment_method', 'cfdi_payment_form', 'export_type',
        'cfdi_canceled_at', 'cfdi_cancellation_status', 'cfdi_cancellation_reason',
    ];

    protected $casts = [
        'invoice_date' => 'date',
        'due_date' => 'date',
        'subtotal' => 'decimal:2',
        'tax' => 'decimal:2',
        'discount' => 'decimal:2',
        'total' => 'decimal:2',
        'paid_at' => 'datetime',
        'cfdi_stamp_date' => 'datetime',
        'cfdi_canceled_at' => 'datetime',
    ];

    protected $appends = ['is_paid', 'is_overdue', 'is_issued'];

    // Relaciones
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }
    public function payment()
    {
        return $this->belongsTo(Payment::class);
    }

    public function billingCycle(): BelongsTo
    {
        return $this->belongsTo(BillingCycle::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(InvoiceItem::class);
    }

    public function invoiceRequest(): HasOne
    {
        return $this->hasOne(InvoiceRequest::class);
    }

    // Scopes
    public function scopePaid($query)
    {
        return $query->where('status', 'paid');
    }

    public function scopeIssued($query)
    {
        return $query->where('status', 'issued');
    }

    public function scopeOverdue($query)
    {
        return $query->where('status', '!=', 'paid')
            ->where('status', '!=', 'canceled')
            ->where('due_date', '<', now());
    }

    public function scopeForCompany($query, $companyId)
    {
        return $query->where('company_id', $companyId);
    }

    // Accessors
    public function getIsPaidAttribute(): bool
    {
        return $this->status === 'paid';
    }

    public function getIsOverdueAttribute(): bool
    {
        return !in_array($this->status, ['paid', 'canceled']) && $this->due_date->isPast();
    }

    public function getIsIssuedAttribute(): bool
    {
        return !empty($this->cfdi_uuid) && $this->status === 'issued';
    }

    // Métodos de negocio
    public function markAsPaid(string $paymentMethod, ?string $reference = null): bool
    {
        return $this->update([
            'status' => 'paid',
            'paid_at' => now(),
            'payment_method' => $paymentMethod,
            'payment_reference' => $reference,
        ]);
    }

    public function markAsIssued(array $cfdiData): bool
    {
        return $this->update(array_merge([
            'status' => 'issued',
        ], $cfdiData));
    }

    public function cancel(string $reason): bool
    {
        return $this->update([
            'status' => 'canceled',
            'cfdi_canceled_at' => now(),
            'cfdi_cancellation_reason' => $reason,
        ]);
    }

    public function calculateTotals(): void
    {
        $this->subtotal = $this->items->sum('subtotal');
        $this->tax = $this->items->sum('tax_amount');
        $this->discount = $this->items->sum('discount');
        $this->total = round($this->subtotal + $this->tax - $this->discount, 2);
    }
}