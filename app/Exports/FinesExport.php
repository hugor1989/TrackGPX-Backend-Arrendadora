<?php

namespace App\Exports;

use App\Models\Fine;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class FinesExport implements FromQuery, WithHeadings, WithMapping, ShouldAutoSize, WithStyles
{
    protected $filters;

    public function __construct($filters)
    {
        $this->filters = $filters;
    }

    // 1. La Consulta (Misma lógica que en pantalla)
    public function query()
    {
        $query = Fine::with(['vehicle.group.supervisor', 'vehicle.driver']) // Cargamos Supervisor y Conductor
            ->where('company_id', auth()->user()->company_id);

        if (!empty($this->filters['start_date'])) {
            $query->whereBetween('detected_at', [$this->filters['start_date'], $this->filters['end_date']]);
        }

        if (!empty($this->filters['group_id']) && $this->filters['group_id'] != 'all') {
            $query->whereHas('vehicle', function($q) {
                $q->where('group_id', $this->filters['group_id']);
            });
        }

        if (!empty($this->filters['status']) && $this->filters['status'] != 'all') {
            $query->where('status', $this->filters['status']);
        }
        
        // Ordenar por fecha reciente
        return $query->orderBy('detected_at', 'desc');
    }

    // 2. Mapeo de Datos (Fila por fila)
    public function map($fine): array
    {
        return [
            $fine->id,
            $fine->detected_at, // Excel formatea fechas automático
            $fine->status === 'paid' ? 'PAGADO' : 'PENDIENTE',
            $fine->amount,
            $fine->vehicle->plate,
            $fine->vehicle->name,
            $fine->vehicle->driver->name ?? 'Sin Asignar', // Conductor
            $fine->vehicle->group->name ?? 'Sin Grupo',     // Flota
            $fine->vehicle->group->supervisor->name ?? 'N/A', // Supervisor
            $fine->reference,
            $fine->description
        ];
    }

    // 3. Cabeceras
    public function headings(): array
    {
        return [
            'ID', 'Fecha Infracción', 'Estatus', 'Monto', 
            'Placa', 'Vehículo', 'Conductor', 'Flota/Grupo', 
            'Supervisor', 'Referencia', 'Motivo'
        ];
    }

    // 4. Estilos (Negritas en cabecera)
    public function styles(Worksheet $sheet)
    {
        return [
            1 => ['font' => ['bold' => true]],
        ];
    }
}