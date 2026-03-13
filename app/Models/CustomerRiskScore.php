<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CustomerRiskScore extends Model
{
    use HasFactory;

    /**
     * Atributos que se pueden asignar masivamente.
     * Estos campos permiten guardar la calificación (Bajo, Medio, Alto)[cite: 138, 139, 140, 141].
     */
    protected $fillable = [
        'account_id',
        'score',
        'points',
        'reason'
    ];

    /**
     * Relación con la cuenta (Cliente).
     * Esto une el score con el nombre del cliente para el reporte de directores[cite: 204, 207].
     */
    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    /**
     * Scope para filtrar rápidamente carteras por nivel de riesgo.
     * Útil para el dashboard de "Cartera en riesgo"[cite: 173, 174, 337].
     */
    public function scopeHighRisk($query)
    {
        return $query->where('score', 'Alto');
    }
}