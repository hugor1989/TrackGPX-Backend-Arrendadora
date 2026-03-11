<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Str;

class Device extends Model
{
    use HasFactory;

    protected $fillable = [
        'company_id',
        'imei',
        'serial_number',
        'model',
        'manufacturer',
        'protocol',
        'firmware_version',
        'activation_code',
        'activated_at',
        'status',
        'vehicle_id',
        'sim_id',
        'config_parameters',
        'last_connection',
    ];

    protected $casts = [
        'activated_at' => 'datetime',
        'last_connection' => 'datetime',
        'config_parameters' => 'array',
    ];

    protected $appends = [
        'is_activated',
        'is_online',
    ];

    // ==================== RELACIONES ====================

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }

    public function simCard(): BelongsTo
    {
        return $this->belongsTo(SimCard::class, 'sim_id');
    }

    public function subscription(): HasOne
    {
        return $this->hasOne(DeviceSubscription::class);
    }

    public function activeSubscription(): HasOne
    {
        return $this->hasOne(DeviceSubscription::class)
            ->where('status', 'active');
    }

    // ==================== SCOPES ====================

    public function scopeAvailable($query)
    {
        return $query->where('status', 'available');
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopeActivated($query)
    {
        return $query->whereNotNull('activated_at');
    }

    public function scopeUnassigned($query)
    {
        return $query->whereNull('company_id');
    }

    public function scopeOnline($query)
    {
        return $query->where('last_connection', '>=', now()->subMinutes(5));
    }

    // ==================== ACCESSORS ====================

    public function getIsActivatedAttribute(): bool
    {
        return !is_null($this->activated_at);
    }

    public function getIsOnlineAttribute(): bool
    {
        if (!$this->last_connection) {
            return false;
        }

        return $this->last_connection->diffInMinutes(now()) <= 5;
    }

    // ==================== MÉTODOS DE NEGOCIO ====================

    /**
     * Generar código de activación único
     */
    public static function generateActivationCode(): string
    {
        do {
            $code = strtoupper(Str::random(6) . rand(100, 999));
        } while (self::where('activation_code', $code)->exists());

        return $code;
    }

    /**
     * Registrar dispositivo desde el servidor (auto-registro)
     */
    public static function registerFromServer(array $data): self
    {
        return self::create([
            'imei' => $data['imei'],
            'serial_number' => $data['serial_number'] ?? null,
            'model' => $data['model'] ?? null,
            'manufacturer' => $data['manufacturer'] ?? null,
            'protocol' => $data['protocol'] ?? 'JT808',
            'activation_code' => self::generateActivationCode(),
            'status' => 'available',
            'config_parameters' => [
                'gps_interval' => 30,
                'heartbeat_interval' => 60,
                'overspeed_limit' => 80,
                'alarms' => [
                    'sos' => true,
                    'overspeed' => true,
                    'power_cut' => true,
                    'low_battery' => true,
                ],
                'geo_fences' => [],
                'sos_numbers' => [],
            ],
        ]);
    }

    /**
     * Activar dispositivo (vincularlo a una empresa)
     */
    public function activate(int $companyId): bool
    {
        if ($this->is_activated) {
            return false;
        }

        return $this->update([
            'company_id' => $companyId,
            'status' => 'active',
            'activated_at' => now(),
        ]);
    }

    /**
     * Validar código de activación
     */
    public static function validateActivationCode(string $imei, string $activationCode): ?self
    {
        return self::where('imei', $imei)
            ->where('activation_code', $activationCode)
            ->where('status', 'available')
            ->whereNull('activated_at')
            ->first();
    }

    /**
     * Asignar a un vehículo
     */
    public function assignToVehicle(?int $vehicleId): bool
    {
        return $this->update(['vehicle_id' => $vehicleId]);
    }

    /**
     * Actualizar última conexión
     */
    public function updateLastConnection(): bool
    {
        return $this->update(['last_connection' => now()]);
    }

    /**
     * Suspender dispositivo
     */
    public function suspend(): bool
    {
        return $this->update(['status' => 'suspended']);
    }

    /**
     * Reactivar dispositivo
     */
    public function reactivate(): bool
    {
        return $this->update(['status' => 'active']);
    }

    /**
     * Verificar si tiene suscripción activa
     */
    public function hasActiveSubscription(): bool
    {
        return $this->activeSubscription()->exists();
    }
}