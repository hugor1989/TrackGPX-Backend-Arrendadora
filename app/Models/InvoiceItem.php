<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Carbon\Carbon;

class InvoiceItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'invoice_id', 'device_subscription_id', 'product_code', 'unit_code',
        'description', 'quantity', 'unit_price', 'subtotal', 'tax_rate',
        'tax_amount', 'discount', 'total', 'period_start', 'period_end', 'metadata',
    ];

    protected $casts = [
        'quantity' => 'decimal:2',
        'unit_price' => 'decimal:2',
        'subtotal' => 'decimal:2',
        'tax_rate' => 'decimal:4',
        'tax_amount' => 'decimal:2',
        'discount' => 'decimal:2',
        'total' => 'decimal:2',
        'period_start' => 'date',
        'period_end' => 'date',
        'metadata' => 'array',
    ];

    // Relaciones
    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function deviceSubscription(): BelongsTo
    {
        return $this->belongsTo(DeviceSubscription::class);
    }

    // Métodos
    public function calculateTotals(): void
    {
        $this->subtotal = round($this->quantity * $this->unit_price, 2);
        $this->tax_amount = round($this->subtotal * ($this->tax_rate / 100), 2);
        $this->total = round($this->subtotal + $this->tax_amount - $this->discount, 2);
    }

    public static function createFromSubscription(
        Invoice $invoice,
        DeviceSubscription $subscription,
        Carbon $periodStart,
        Carbon $periodEnd
    ): self {
        $item = new self();
        $item->invoice_id = $invoice->id;
        $item->device_subscription_id = $subscription->id;
        $item->product_code = $subscription->plan->sat_product_code ?? '81112001';
        $item->unit_code = 'E48';
        $item->description = "Servicio de rastreo GPS - " . $subscription->device->name;
        $item->quantity = 1;
        $item->unit_price = $subscription->calculateProratedAmount($periodStart, $periodEnd);
        $item->tax_rate = 16.00;
        $item->discount = 0.00;
        $item->period_start = $periodStart;
        $item->period_end = $periodEnd;
        $item->metadata = [
            'device_id' => $subscription->device_id,
            'device_imei' => $subscription->device->imei ?? null,
            'plan_id' => $subscription->plan_id,
            'plan_name' => $subscription->plan->name,
        ];
        $item->calculateTotals();
        return $item;
    }
}