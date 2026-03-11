<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Reporte de Multas</title>
  <style>
        /* --- CONFIGURACIÓN DE PÁGINA --- */
        @page { margin: 0cm 0cm; }
        body {
            margin-top: 3.5cm;
            margin-left: 1cm;
            margin-right: 1cm;
            margin-bottom: 2cm;
            font-family: 'Helvetica', 'Arial', sans-serif;
            font-size: 10px;
            color: #334155;
            background-color: #fff;
        }

        /* --- HEADER --- */
        header {
            position: fixed;
            top: 0cm;
            left: 0cm;
            right: 0cm;
            height: 3cm;
            background-color: #f8fafc;
            border-bottom: 3px solid #1e3a8a; /* Azul más oscuro */
            padding: 0.5cm 1cm;
        }

        .header-table { width: 100%; border: none; }
        .header-logo { width: 20%; vertical-align: middle; }
        .header-info { width: 80%; text-align: right; vertical-align: middle; }
        
        h1 { margin: 0; color: #0f172a; font-size: 18px; text-transform: uppercase; font-weight: 800; }
        .company-name { font-weight: bold; font-size: 12px; color: #475569; margin-top: 4px; }
        .meta-data { font-size: 9px; color: #64748b; margin-top: 4px; }

        /* --- TABLA DE DATOS (AQUÍ ESTÁ EL ARREGLO) --- */
        table.data-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
        }
        
        .data-table th {
            /* CAMBIO: Azul Marino Oscuro para que el texto blanco resalte 100% */
            background-color: #1e3a8a; 
            color: #ffffff;
            
            /* CAMBIO: Letra más grande y gruesa */
            font-size: 10px; 
            font-weight: bold;
            text-transform: uppercase;
            
            padding: 10px 6px; /* Más espacio vertical */
            text-align: left;
            border-bottom: 2px solid #000;
        }

        .data-table td {
            padding: 8px 6px;
            border-bottom: 1px solid #e2e8f0;
            vertical-align: top;
            color: #334155;
        }

        /* Zebra */
        .data-table tr:nth-child(even) { background-color: #f1f5f9; }

        /* --- UTILIDADES --- */
        .text-right { text-align: right; }
        .font-bold { font-weight: bold; color: #0f172a; }
        .text-sm { font-size: 9px; color: #64748b; }
        .text-xs { font-size: 8px; color: #94a3b8; }
        
        /* Badges */
        .badge {
            padding: 4px 8px;
            border-radius: 4px;
            color: #fff;
            font-weight: bold;
            font-size: 9px; /* Badge un poco más grande */
            text-transform: uppercase;
            display: inline-block;
        }
        .badge-paid { background-color: #10b981; }
        .badge-pending { background-color: #ef4444; }

        /* Motivo */
        .motivo-box {
            background-color: #fff;
            border-left: 3px solid #94a3b8; /* Borde más visible */
            padding-left: 8px;
            margin-top: 4px;
            font-style: italic;
            color: #475569;
            font-size: 9px;
        }

        /* --- FOOTER --- */
        footer {
            position: fixed; 
            bottom: 0cm; left: 0cm; right: 0cm;
            height: 1cm; 
            background-color: #f1f5f9;
            color: #64748b;
            text-align: center;
            line-height: 1cm;
            font-size: 9px;
            border-top: 1px solid #cbd5e1;
        }
        
        .total-box {
            margin-top: 25px;
            text-align: right;
            padding-right: 10px;
        }
        .total-label { font-size: 11px; color: #475569; font-weight: bold; margin-right: 15px; text-transform: uppercase; }
        .total-amount { font-size: 18px; font-weight: 800; color: #0f172a; }
    </style>
</head>
<body>

    <header>
        <table class="header-table">
            <tr>
                <td class="header-logo">
                    <img src="{{ public_path('images/logo.png') }}" style="max-height: 50px; max-width: 150px;">

                    <div style="background:#3b82f6; color:#fff; padding:8px; border-radius:4px; display:inline-block; font-weight:bold;">
                        TrackGPX
                    </div>
                </td>
                <td class="header-info">
                    <h1>Reporte de Infracciones</h1>
                    <div class="company-name">{{ auth()->user()->company->name ?? 'Empresa Cliente' }}</div>
                    <div class="meta-data">
                        Generado: {{ date('d/m/Y H:i') }} | 
                        Usuario: {{ auth()->user()->name }}
                    </div>
                </td>
            </tr>
        </table>
    </header>

    <footer>
        Página 1 de 1 | Documento generado automáticamente por el sistema TrackGPX
    </footer>

    <table class="data-table">
        <thead>
            <tr>
                <th style="width: 10%">Fecha</th>
                <th style="width: 20%">Vehículo / Conductor</th>
                <th style="width: 30%">Detalle Infracción</th> <th style="width: 18%">Grupo / Supervisor</th>
                <th style="width: 10%">Estatus</th>
                <th style="width: 12%" class="text-right">Monto</th>
            </tr>
        </thead>
        <tbody>
            @foreach($fines as $fine)
            <tr>
                <td>
                    <div class="font-bold">{{ \Carbon\Carbon::parse($fine->detected_at)->format('d/m/Y') }}</div>
                    <div class="text-sm">{{ \Carbon\Carbon::parse($fine->detected_at)->format('H:i') }} hrs</div>
                </td>

                <td>
                    <div class="font-bold" style="color:#2563eb;">{{ $fine->vehicle->plate }}</div>
                    <div class="text-sm">{{ $fine->vehicle->name }}</div>
                    
                    <div style="margin-top: 6px; border-top: 1px dotted #e2e8f0; padding-top:2px;">
                        <span class="text-xs">COND:</span><br>
                        <span class="font-bold" style="font-size:9px;">
                            {{ $fine->vehicle->driver->account->name ?? $fine->vehicle->driver->name ?? 'Sin Asignar' }}
                        </span>
                    </div>
                </td>

                <td>
                    <div>
                        <span class="text-xs font-bold" style="color:#64748b;">FOLIO:</span>
                        <span class="font-bold" style="font-size:11px;">{{ $fine->reference ?? 'S/N' }}</span>
                    </div>
                    <div class="motivo-box">
                        {{ $fine->description ?? 'Sin descripción registrada.' }}
                    </div>
                </td>

                <td>
                    @if($fine->vehicle->group)
                        <div class="font-bold">{{ $fine->vehicle->group->name }}</div>
                        <div class="text-xs" style="margin-top:2px;">
                            <span style="color:#94a3b8">SUP:</span> 
                            {{ $fine->vehicle->group->supervisor->name ?? 'N/A' }}
                        </div>
                    @else
                        <span style="color:#cbd5e1">-- Sin Grupo --</span>
                    @endif
                </td>

                <td>
                    <span class="badge {{ $fine->status === 'paid' ? 'badge-paid' : 'badge-pending' }}">
                        {{ $fine->status === 'paid' ? 'PAGADO' : 'PENDIENTE' }}
                    </span>
                </td>

                <td class="text-right">
                    <div class="font-bold" style="font-size:11px;">${{ number_format($fine->amount, 2) }}</div>
                </td>
            </tr>
            @endforeach
        </tbody>
    </table>

    <div class="total-box">
        <span class="total-label">Total General:</span>
        <span class="total-amount">${{ number_format($fines->sum('amount'), 2) }}</span>
    </div>

</body>
</html>