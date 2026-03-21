@extends('pdf.reports.layout')

@section('content')
    {{-- KPIs --}}
    <table class="kpi-container">
        <tr>
            <td>
                <div class="kpi-box">
                    <div class="kpi-value">S/ {{ number_format($kpis['total_facturado'], 2) }}</div>
                    <div class="kpi-label">Total Facturado</div>
                </div>
            </td>
            <td class="kpi-highlight">
                <div class="kpi-box">
                    <div class="kpi-value">S/ {{ number_format($kpis['total_cobrado'], 2) }}</div>
                    <div class="kpi-label">Total Cobrado</div>
                </div>
            </td>
            <td class="kpi-danger">
                <div class="kpi-box">
                    <div class="kpi-value">S/ {{ number_format($kpis['total_pendiente'], 2) }}</div>
                    <div class="kpi-label">Total Pendiente</div>
                </div>
            </td>
            <td>
                <div class="kpi-box">
                    <div class="kpi-value">{{ $kpis['porcentaje_cobranza'] }}%</div>
                    <div class="kpi-label">% Cobranza</div>
                </div>
            </td>
        </tr>
    </table>

    {{-- Aging --}}
    <div class="section-title">Antigüedad de Deuda (Aging)</div>
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

    {{-- Resumen por Método de Pago --}}
    @if(count($resumen_por_metodo) > 0)
    <div class="section-title">Resumen por Método de Pago</div>
    <table class="data-table">
        <thead>
            <tr>
                <th>Método</th>
                <th class="text-right">Cantidad</th>
                <th class="text-right">Monto (S/)</th>
            </tr>
        </thead>
        <tbody>
            @foreach($resumen_por_metodo as $m)
            <tr>
                <td>{{ ucfirst($m['metodo']) }}</td>
                <td class="text-right">{{ $m['cantidad'] }}</td>
                <td class="text-right">{{ number_format($m['monto'], 2) }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>
    @endif

    {{-- Resumen por Cliente --}}
    <div class="section-title">Resumen por Cliente (Top Deudores)</div>
    <table class="data-table">
        <thead>
            <tr>
                <th>RUC/DNI</th>
                <th>Razón Social</th>
                <th class="text-right">Facturado (S/)</th>
                <th class="text-right">Cobrado (S/)</th>
                <th class="text-right">Pendiente (S/)</th>
            </tr>
        </thead>
        <tbody>
            @foreach($resumen_por_cliente->take(20) as $c)
            <tr>
                <td>{{ $c['num_doc'] }}</td>
                <td>{{ \Illuminate\Support\Str::limit($c['razon_social'], 35) }}</td>
                <td class="text-right">{{ number_format($c['total_facturado'], 2) }}</td>
                <td class="text-right">{{ number_format($c['total_cobrado'], 2) }}</td>
                <td class="text-right">{{ number_format($c['total_pendiente'], 2) }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>

    {{-- Detalle de Documentos --}}
    <div class="section-title">Detalle de Documentos</div>
    <table class="data-table">
        <thead>
            <tr>
                <th>Tipo</th>
                <th>Número</th>
                <th>Cliente</th>
                <th>F.Emisión</th>
                <th>F.Venc.</th>
                <th class="text-right">Total</th>
                <th class="text-right">Cobrado</th>
                <th class="text-right">Pendiente</th>
                <th class="text-right">Días Atraso</th>
                <th>Estado</th>
            </tr>
        </thead>
        <tbody>
            @foreach($detalle as $d)
            <tr>
                <td class="text-center">{{ $d['tipo'] }}</td>
                <td>{{ $d['numero_completo'] }}</td>
                <td>{{ \Illuminate\Support\Str::limit($d['client_razon_social'], 20) }}</td>
                <td>{{ $d['fecha_emision'] }}</td>
                <td>{{ $d['fecha_vencimiento'] ?? '-' }}</td>
                <td class="text-right">{{ number_format($d['total'], 2) }}</td>
                <td class="text-right">{{ number_format($d['cobrado'], 2) }}</td>
                <td class="text-right">{{ number_format($d['pendiente'], 2) }}</td>
                <td class="text-right">{{ $d['dias_atraso'] > 0 ? $d['dias_atraso'] : '-' }}</td>
                <td class="text-center">
                    @if($d['payment_status'] === 'pagado')
                        <span class="badge badge-success">Pagado</span>
                    @elseif($d['payment_status'] === 'parcial')
                        <span class="badge badge-warning">Parcial</span>
                    @else
                        <span class="badge badge-danger">Pendiente</span>
                    @endif
                </td>
            </tr>
            @endforeach
        </tbody>
    </table>
@endsection
