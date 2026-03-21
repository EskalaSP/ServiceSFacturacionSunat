@extends('pdf.reports.layout')

@section('content')
    {{-- Client Info --}}
    <div class="section-title">Datos del Cliente</div>
    <table style="width: 100%; margin-bottom: 8px; font-size: 9.5px;">
        <tr>
            <td style="width: 50%;">
                <strong>{{ $cliente['razon_social'] ?? 'N/A' }}</strong><br>
                {{ $cliente['tipo_documento'] ?? '' }} {{ $cliente['numero_documento'] ?? $cliente['num_doc'] ?? '' }}<br>
                {{ $cliente['direccion'] ?? '' }}
            </td>
            <td style="width: 50%;">
                @if(!empty($cliente['email']))Email: {{ $cliente['email'] }}<br>@endif
                @if(!empty($cliente['telefono']))Tel: {{ $cliente['telefono'] }}<br>@endif
                @if(!empty($cliente['nombre_comercial']))N. Comercial: {{ $cliente['nombre_comercial'] }}@endif
            </td>
        </tr>
    </table>

    {{-- Resumen --}}
    <table class="kpi-container">
        <tr>
            <td>
                <div class="kpi-box">
                    <div class="kpi-value">S/ {{ number_format($resumen['total_facturado'], 2) }}</div>
                    <div class="kpi-label">Total Facturado</div>
                </div>
            </td>
            <td>
                <div class="kpi-box">
                    <div class="kpi-value">S/ {{ number_format($resumen['neto'], 2) }}</div>
                    <div class="kpi-label">Neto</div>
                </div>
            </td>
            <td>
                <div class="kpi-box">
                    <div class="kpi-value">S/ {{ number_format($resumen['cobrado'], 2) }}</div>
                    <div class="kpi-label">Cobrado</div>
                </div>
            </td>
            <td>
                <div class="kpi-box">
                    <div class="kpi-value">S/ {{ number_format($resumen['pendiente'], 2) }}</div>
                    <div class="kpi-label">Pendiente</div>
                </div>
            </td>
        </tr>
    </table>

    {{-- Extra info --}}
    <table class="data-table" style="width: 50%; margin-bottom: 8px;">
        <tr><td><strong>NC emitidas:</strong> S/ {{ number_format($resumen['nc_emitidas'], 2) }}</td><td><strong>ND emitidas:</strong> S/ {{ number_format($resumen['nd_emitidas'], 2) }}</td></tr>
    </table>

    {{-- Detalle Documentos --}}
    <div class="section-title">Detalle de Documentos</div>
    <table class="data-table">
        <thead>
            <tr>
                <th>Tipo</th>
                <th>Número</th>
                <th>F.Emisión</th>
                <th>F.Venc.</th>
                <th class="text-right">Total</th>
                <th>Mon.</th>
                <th>SUNAT</th>
                <th>Pago</th>
                <th class="text-right">Pagado</th>
            </tr>
        </thead>
        <tbody>
            @foreach($detalle_documentos as $d)
            <tr>
                <td>{{ $d['tipo_nombre'] }}</td>
                <td>{{ $d['numero_completo'] }}</td>
                <td>{{ $d['fecha_emision'] }}</td>
                <td>{{ $d['fecha_vencimiento'] ?? '-' }}</td>
                <td class="text-right">{{ number_format($d['mto_imp_venta'], 2) }}</td>
                <td class="text-center">{{ $d['tipo_moneda'] }}</td>
                <td class="text-center">
                    @if($d['sunat_status'] === 'aceptado')
                        <span class="badge badge-success">Acept.</span>
                    @elseif($d['sunat_status'] === 'rechazado')
                        <span class="badge badge-danger">Rech.</span>
                    @elseif($d['sunat_status'])
                        <span class="badge badge-warning">{{ ucfirst(substr($d['sunat_status'], 0, 5)) }}</span>
                    @else
                        -
                    @endif
                </td>
                <td class="text-center">
                    @if(($d['payment_status'] ?? '') === 'pagado')
                        <span class="badge badge-success">Pagado</span>
                    @elseif(($d['payment_status'] ?? '') === 'parcial')
                        <span class="badge badge-warning">Parcial</span>
                    @elseif($d['payment_status'])
                        <span class="badge badge-danger">Pend.</span>
                    @else
                        -
                    @endif
                </td>
                <td class="text-right">{{ number_format($d['monto_pagado'], 2) }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>

    {{-- Historial de Pagos --}}
    @if(count($historial_pagos) > 0)
    <div class="section-title">Historial de Pagos</div>
    <table class="data-table">
        <thead>
            <tr>
                <th>Fecha</th>
                <th>Método</th>
                <th class="text-right">Monto (S/)</th>
                <th>Referencia</th>
                <th>Notas</th>
            </tr>
        </thead>
        <tbody>
            @foreach($historial_pagos as $p)
            <tr>
                <td>{{ $p['fecha'] }}</td>
                <td>{{ ucfirst($p['metodo']) }}</td>
                <td class="text-right">{{ number_format($p['monto'], 2) }}</td>
                <td>{{ $p['referencia'] ?? '-' }}</td>
                <td>{{ \Illuminate\Support\Str::limit($p['notas'] ?? '-', 30) }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>
    @endif

    {{-- Aging --}}
    <div class="section-title">Antigüedad de Deuda</div>
    <table class="data-table">
        <thead>
            <tr>
                <th>Rango (días)</th>
                <th class="text-right">Monto Pendiente (S/)</th>
                <th style="width: 50%;">Proporción</th>
            </tr>
        </thead>
        <tbody>
            @php
                $agingColors = ['0-15' => 'aging-green', '16-30' => 'aging-yellow', '31-60' => 'aging-orange', '61-90' => 'aging-red', '91-120' => 'aging-darkred', '120+' => 'aging-crimson'];
                $maxAging = max(array_values($aging) ?: [1]);
            @endphp
            @foreach($aging as $rango => $monto)
            <tr>
                <td>{{ $rango }}</td>
                <td class="text-right">{{ number_format($monto, 2) }}</td>
                <td>
                    @if($maxAging > 0)
                        <div class="aging-bar {{ $agingColors[$rango] ?? 'aging-green' }}" style="width: {{ max(2, ($monto / $maxAging) * 100) }}%;"></div>
                    @endif
                </td>
            </tr>
            @endforeach
        </tbody>
    </table>
@endsection
