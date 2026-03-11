<?php

namespace App\Services;

use App\Models\Invoice;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;

/**
 * Servicio para generar folios consecutivos de facturas
 * 
 * Genera folios con formato: TGX0000001, TGX0000002, etc.
 * Thread-safe usando transacciones y locks de base de datos
 */
class FolioGenerator
{
    /**
     * Prefijo del folio (configurable)
     */
    private string $prefix;

    /**
     * Longitud del número (padding con ceros)
     */
    private int $padding;

    /**
     * Constructor
     * 
     * @param string|null $prefix Prefijo del folio (default: TGX)
     * @param int $padding Longitud del número (default: 7)
     */
    public function __construct(?string $prefix = null, int $padding = 7)
    {
        $this->prefix = $prefix ?? config('invoice.folio_prefix', 'TGX');
        $this->padding = $padding;
    }

    /**
     * Genera el siguiente folio consecutivo
     * 
     * Este método es thread-safe y previene duplicados incluso
     * con múltiples peticiones simultáneas
     * 
     * @return string El siguiente folio (ej: TGX0000001)
     */
    public function next(): string
    {
        return DB::transaction(function () {
            // Obtener el último folio con lock (previene race conditions)
            $lastInvoice = Invoice::query()
                ->whereNotNull('folio')
                ->where('folio', 'LIKE', $this->prefix . '%')
                ->lockForUpdate()
                ->orderBy('id', 'desc')
                ->first();

            if (!$lastInvoice || !$lastInvoice->folio) {
                // Primera factura
                return $this->format(1);
            }

            // Extraer el número del último folio
            $lastNumber = $this->extractNumber($lastInvoice->folio);
            
            // Incrementar
            $nextNumber = $lastNumber + 1;
            
            return $this->format($nextNumber);
        });
    }

    /**
     * Genera el siguiente invoice_number único
     * 
     * Formato: INV-YYYYMMDD-XXXX (se resetea cada día)
     * 
     * @return string
     */
    public function nextInvoiceNumber(): string
    {
        return DB::transaction(function () {
            $today = date('Ymd');
            
            // Contar facturas creadas hoy con lock
            $todayCount = Invoice::query()
                ->whereDate('created_at', today())
                ->lockForUpdate()
                ->count();
            
            $sequence = str_pad($todayCount + 1, 4, '0', STR_PAD_LEFT);
            
            return "TGX-{$today}-{$sequence}";
        });
    }

    /**
     * Obtiene el último folio usado (sin incrementar)
     * 
     * @return string|null
     */
    public function current(): ?string
    {
        $lastInvoice = Invoice::query()
            ->whereNotNull('folio')
            ->where('folio', 'LIKE', $this->prefix . '%')
            ->orderBy('id', 'desc')
            ->first();

        return $lastInvoice?->folio;
    }

    /**
     * Obtiene el siguiente número sin formato
     * 
     * @return int
     */
    public function nextNumber(): int
    {
        return DB::transaction(function () {
            $lastInvoice = Invoice::query()
                ->whereNotNull('folio')
                ->where('folio', 'LIKE', $this->prefix . '%')
                ->lockForUpdate()
                ->orderBy('id', 'desc')
                ->first();

            if (!$lastInvoice || !$lastInvoice->folio) {
                return 1;
            }

            return $this->extractNumber($lastInvoice->folio) + 1;
        });
    }

    /**
     * Valida si un folio tiene el formato correcto
     * 
     * @param string $folio
     * @return bool
     */
    public function isValid(string $folio): bool
    {
        $pattern = '/^' . preg_quote($this->prefix, '/') . '\d{' . $this->padding . '}$/';
        return preg_match($pattern, $folio) === 1;
    }

    /**
     * Extrae el número de un folio
     * 
     * @param string $folio (ej: TGX0000123)
     * @return int (ej: 123)
     */
    protected function extractNumber(string $folio): int
    {
        // Remover el prefijo
        $withoutPrefix = str_replace($this->prefix, '', $folio);
        
        // Convertir a entero (quita los ceros a la izquierda automáticamente)
        return (int) $withoutPrefix;
    }

    /**
     * Formatea un número al formato de folio
     * 
     * @param int $number (ej: 123)
     * @return string (ej: TGX0000123)
     */
    protected function format(int $number): string
    {
        return $this->prefix . str_pad($number, $this->padding, '0', STR_PAD_LEFT);
    }

    /**
     * Obtiene estadísticas de folios
     * 
     * @return array
     */
    public function stats(): array
    {
        $current = $this->current();
        $currentNumber = $current ? $this->extractNumber($current) : 0;

        return [
            'prefix' => $this->prefix,
            'padding' => $this->padding,
            'current_folio' => $current,
            'current_number' => $currentNumber,
            'next_folio' => $this->format($currentNumber + 1),
            'next_number' => $currentNumber + 1,
            'total_invoices' => Invoice::whereNotNull('folio')->count(),
        ];
    }

    /**
     * Verifica si hay huecos en la secuencia de folios
     * (Útil para auditorías)
     * 
     * @return array
     */
    public function findGaps(): array
    {
        $invoices = Invoice::query()
            ->whereNotNull('folio')
            ->where('folio', 'LIKE', $this->prefix . '%')
            ->orderBy('id')
            ->pluck('folio')
            ->map(fn($folio) => $this->extractNumber($folio))
            ->toArray();

        if (empty($invoices)) {
            return [];
        }

        $gaps = [];
        $expected = $invoices[0];

        foreach ($invoices as $actual) {
            if ($actual > $expected) {
                // Hay un hueco
                for ($missing = $expected; $missing < $actual; $missing++) {
                    $gaps[] = $this->format($missing);
                }
            }
            $expected = $actual + 1;
        }

        return $gaps;
    }
}
