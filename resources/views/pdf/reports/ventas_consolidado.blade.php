@extends('pdf.reports.layout')

@section('content')
    {{-- KPIs principales --}}
    <table class="kpi-container">
        <tr>
            <td>
                <div class="kpi-box">
                    <div class="kpi-value">S/ {{ number_format($kpis['total_facturado'], 2) }}</div>
                    <div class="kpi-label">Total Facturado</div>
                </div>
            </td>
            <td>
                <div class="kpi-box">
                    <div class="kpi-value">S/ {{ number_format($kpis['total_igv'], 2) }}</div>
                    <div class="kpi-label">Total IGV</div>
                </div>
            </td>
            <td>
                <div class="kpi-box">
                    <div class="kpi-value">S/ {{ number_format($kpis['venta_neta'], 2) }}</div>
                    <div class="kpi-label">Venta Neta</div>
                </div>
            </td>
            <td>
                <div class="kpi-box">
                    <div class="kpi-value">{{ $kpis['porcentaje_cobrado'] }}%</div>
                    <div class="kpi-label">% Cobrado</div>
                </div>
            </td>
        </tr>
    </table>

    {{-- Indicadores secundarios en tabla compacta --}}
    <table class="data-table" style="width: 60%; margin-bottom: 10px;">
        <tr>
            <td><strong>Docs emitidos:</strong> {{ $kpis['docs_emitidos'] }}</td>
            <td><strong>Ticket promedio:</strong> S/ {{ number_format($kpis['ticket_promedio'], 2) }}</td>
            <td><strong>NC emitidas:</strong> {{ $kpis['nc_emitidas'] }}</td>
            <td><strong>ND emitidas:</strong> {{ $kpis['nd_emitidas'] }}</td>
        </tr>
    </table>

    {{-- Desglose por Tipo --}}
    <div class="section-title">Desglose por Tipo de Documento</div>
    <table class="data-table">
        <thead>
            <tr>
                <th>Tipo</th>
                <th>Nombre</th>
                <th class="text-right">Cantidad</th>
                <th class="text-right">Monto (S/)</th>
            </tr>
        </thead>
        <tbody>
            @foreach($desglose_por_tipo as $tipo)
            <tr>
                <td class="text-center">{{ $tipo['tipo'] }}</td>
                <td>{{ $tipo['nombre'] }}</td>
                <td class="text-right">{{ $tipo['cantidad'] }}</td>
                <td class="text-right">{{ number_format($tipo['monto'], 2) }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>

    {{-- Desglose Temporal --}}
    <div class="section-title">Desglose Temporal</div>
    <table class="data-table">
        <thead>
            <tr>
                <th>Periodo</th>
                <th class="text-right">Facturas</th>
                <th class="text-right">Monto F.</th>
                <th class="text-right">Boletas</th>
                <th class="text-right">Monto B.</th>
                <th class="text-right">NC</th>
                <th class="text-right">ND</th>
                <th class="text-right">Neto (S/)</th>
                <th class="text-right">IGV (S/)</th>
            </tr>
        </thead>
        <tbody>
            @foreach($desglose_temporal as $row)
            <tr>
                <td>{{ $row['periodo'] }}</td>
                <td class="text-right">{{ $row['facturas'] }}</td>
                <td class="text-right">{{ number_format($row['facturas_monto'], 2) }}</td>
                <td class="text-right">{{ $row['boletas'] }}</td>
                <td class="text-right">{{ number_format($row['boletas_monto'], 2) }}</td>
                <td class="text-right">{{ $row['nc'] }}</td>
                <td class="text-right">{{ $row['nd'] }}</td>
                <td class="text-right">{{ number_format($row['neto'], 2) }}</td>
                <td class="text-right">{{ number_format($row['igv'], 2) }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>

    {{-- Desglose por Sucursal --}}
    @if(count($desglose_por_sucursal) > 1)
    <div class="section-title">Desglose por Sucursal</div>
    <table class="data-table">
        <thead>
            <tr>
                <th>Sucursal</th>
                <th class="text-right">Facturas</th>
                <th class="text-right">Boletas</th>
                <th class="text-right">NC</th>
                <th class="text-right">ND</th>
                <th class="text-right">Neto (S/)</th>
            </tr>
        </thead>
        <tbody>
            @foreach($desglose_por_sucursal as $s)
            <tr>
                <td>{{ $s['sucursal'] }} ({{ $s['cod_local'] }})</td>
                <td class="text-right">{{ $s['facturas'] }}</td>
                <td class="text-right">{{ $s['boletas'] }}</td>
                <td class="text-right">{{ $s['nc'] }}</td>
                <td class="text-right">{{ $s['nd'] }}</td>
                <td class="text-right">{{ number_format($s['monto'], 2) }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>
    @endif

    {{-- Top Clientes --}}
    @if(count($top_clientes) > 0)
    <div class="section-title">Top 10 Clientes por Monto</div>
    <table class="data-table">
        <thead>
            <tr>
                <th style="width:5%;">Nro</th>
                <th style="width:15%;">RUC/DNI</th>
                <th style="width:40%;">Razón Social</th>
                <th class="text-right" style="width:15%;">Docs</th>
                <th class="text-right" style="width:25%;">Monto (S/)</th>
            </tr>
        </thead>
        <tbody>
            @foreach($top_clientes as $i => $c)
            <tr>
                <td class="text-center">{{ $i + 1 }}</td>
                <td>{{ $c['num_doc'] }}</td>
                <td>{{ $c['razon_social'] }}</td>
                <td class="text-right">{{ $c['cantidad_docs'] }}</td>
                <td class="text-right">{{ number_format($c['monto_bruto'], 2) }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>
    @endif

    {{-- Top Productos --}}
    @if(count($top_productos) > 0)
    <div class="section-title">Top 10 Productos</div>
    <table class="data-table">
        <thead>
            <tr>
                <th style="width:5%;">Nro</th>
                <th style="width:55%;">Descripción</th>
                <th class="text-right" style="width:15%;">Cantidad</th>
                <th class="text-right" style="width:25%;">Monto (S/)</th>
            </tr>
        </thead>
        <tbody>
            @foreach($top_productos as $i => $p)
            <tr>
                <td class="text-center">{{ $i + 1 }}</td>
                <td>{{ $p['descripcion'] }}</td>
                <td class="text-right">{{ number_format($p['cantidad'], 2) }}</td>
                <td class="text-right">{{ number_format($p['monto'], 2) }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>
    @endif

    {{-- Desglose por Moneda --}}
    @if(count($desglose_por_moneda) > 1)
    <div class="section-title">Desglose por Moneda</div>
    <table class="data-table">
        <thead>
            <tr>
                <th>Moneda</th>
                <th class="text-right">Cantidad</th>
                <th class="text-right">Monto</th>
            </tr>
        </thead>
        <tbody>
            @foreach($desglose_por_moneda as $m)
            <tr>
                <td>{{ $m['moneda'] }}</td>
                <td class="text-right">{{ $m['cantidad'] }}</td>
                <td class="text-right">{{ number_format($m['monto'], 2) }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>
    @endif
@endsection
