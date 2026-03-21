@extends('pdf.reports.layout')

@section('content')
    {{-- Cotizaciones --}}
    <div class="section-title">Cotizaciones</div>
    <table class="kpi-container">
        <tr>
            <td>
                <div class="kpi-box">
                    <div class="kpi-value">{{ $cotizaciones['total'] }}</div>
                    <div class="kpi-label">Total Emitidas</div>
                </div>
            </td>
            <td>
                <div class="kpi-box">
                    <div class="kpi-value">{{ $cotizaciones['tasa_conversion'] }}%</div>
                    <div class="kpi-label">Tasa de Conversión</div>
                </div>
            </td>
            <td>
                <div class="kpi-box">
                    <div class="kpi-value">S/ {{ number_format($cotizaciones['monto_total'], 2) }}</div>
                    <div class="kpi-label">Monto Total</div>
                </div>
            </td>
            <td>
                <div class="kpi-box">
                    <div class="kpi-value">S/ {{ number_format($cotizaciones['ticket_promedio'], 2) }}</div>
                    <div class="kpi-label">Ticket Promedio</div>
                </div>
            </td>
        </tr>
    </table>

    {{-- Status breakdown --}}
    <table class="data-table" style="margin-bottom: 8px;">
        <thead>
            <tr>
                <th>Estado</th>
                <th class="text-right">Cantidad</th>
                <th class="text-right">Monto (S/)</th>
            </tr>
        </thead>
        <tbody>
            @php
                $statusBadge = [
                    'vigente' => 'badge-info',
                    'aceptada' => 'badge-success',
                    'rechazada' => 'badge-danger',
                    'vencida' => 'badge-warning',
                ];
            @endphp
            @foreach(['vigente', 'aceptada', 'rechazada', 'vencida'] as $st)
            <tr>
                <td><span class="badge {{ $statusBadge[$st] ?? '' }}">{{ ucfirst($st) }}</span></td>
                <td class="text-right">{{ $cotizaciones['por_status'][$st] ?? 0 }}</td>
                <td class="text-right">{{ number_format($cotizaciones['monto_por_status'][$st] ?? 0, 2) }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>

    {{-- Detalle cotizaciones --}}
    @if(count($detalle_cotizaciones) > 0)
    <table class="data-table">
        <thead>
            <tr>
                <th>Número</th>
                <th>Fecha</th>
                <th>F.Venc.</th>
                <th>Cliente</th>
                <th>RUC/DNI</th>
                <th class="text-right">Monto</th>
                <th>Mon.</th>
                <th>Estado</th>
            </tr>
        </thead>
        <tbody>
            @foreach($detalle_cotizaciones as $d)
            <tr>
                <td>{{ $d['numero'] }}</td>
                <td>{{ $d['fecha_emision'] }}</td>
                <td>{{ $d['fecha_vencimiento'] ?? '-' }}</td>
                <td>{{ \Illuminate\Support\Str::limit($d['client_razon_social'], 25) }}</td>
                <td>{{ $d['client_num_doc'] }}</td>
                <td class="text-right">{{ number_format($d['mto_imp_venta'], 2) }}</td>
                <td class="text-center">{{ $d['tipo_moneda'] }}</td>
                <td class="text-center">
                    @php $cotBadge = match($d['status'] ?? '') { 'vigente' => 'badge-info', 'aceptada' => 'badge-success', 'rechazada' => 'badge-danger', 'vencida' => 'badge-warning', default => 'badge-info' }; @endphp
                    <span class="badge {{ $cotBadge }}">{{ ucfirst(substr($d['status'], 0, 7)) }}</span>
                </td>
            </tr>
            @endforeach
        </tbody>
    </table>
    @endif

    {{-- Notas de Venta --}}
    <div class="section-title">Notas de Venta</div>
    <table class="kpi-container">
        <tr>
            <td>
                <div class="kpi-box">
                    <div class="kpi-value">{{ $notas_venta['total'] }}</div>
                    <div class="kpi-label">Total Emitidas</div>
                </div>
            </td>
            <td>
                <div class="kpi-box">
                    <div class="kpi-value">S/ {{ number_format($notas_venta['monto_total'], 2) }}</div>
                    <div class="kpi-label">Monto Total</div>
                </div>
            </td>
            <td>
                <div class="kpi-box">
                    <div class="kpi-value">S/ {{ number_format($notas_venta['cobrado'], 2) }}</div>
                    <div class="kpi-label">Cobrado</div>
                </div>
            </td>
            <td>
                <div class="kpi-box">
                    <div class="kpi-value">S/ {{ number_format($notas_venta['pendiente'], 2) }}</div>
                    <div class="kpi-label">Pendiente</div>
                </div>
            </td>
        </tr>
    </table>

    @if(count($detalle_notas_venta) > 0)
    <table class="data-table">
        <thead>
            <tr>
                <th>Número</th>
                <th>Fecha</th>
                <th>Cliente</th>
                <th>RUC/DNI</th>
                <th class="text-right">Monto</th>
                <th class="text-right">Pagado</th>
                <th>Mon.</th>
                <th>Estado</th>
                <th>Pago</th>
            </tr>
        </thead>
        <tbody>
            @foreach($detalle_notas_venta as $d)
            <tr>
                <td>{{ $d['numero'] }}</td>
                <td>{{ $d['fecha_emision'] }}</td>
                <td>{{ \Illuminate\Support\Str::limit($d['client_razon_social'], 25) }}</td>
                <td>{{ $d['client_num_doc'] }}</td>
                <td class="text-right">{{ number_format($d['mto_imp_venta'], 2) }}</td>
                <td class="text-right">{{ number_format($d['monto_pagado'], 2) }}</td>
                <td class="text-center">{{ $d['tipo_moneda'] }}</td>
                <td class="text-center">
                    <span class="badge {{ ($d['status'] ?? '') === 'emitida' ? 'badge-success' : 'badge-danger' }}">{{ ucfirst(substr($d['status'], 0, 7)) }}</span>
                </td>
                <td class="text-center">
                    @if(($d['payment_status'] ?? '') === 'pagado')
                        <span class="badge badge-success">Pagado</span>
                    @elseif(($d['payment_status'] ?? '') === 'parcial')
                        <span class="badge badge-warning">Parcial</span>
                    @else
                        <span class="badge badge-danger">Pend.</span>
                    @endif
                </td>
            </tr>
            @endforeach
        </tbody>
    </table>
    @endif

    {{-- Top Clientes --}}
    @if(count($top_clientes) > 0)
    <div class="section-title">Top 10 Clientes (Cotizaciones)</div>
    <table class="data-table">
        <thead>
            <tr>
                <th>RUC/DNI</th>
                <th>Razón Social</th>
                <th class="text-right">Cantidad</th>
                <th class="text-right">Monto (S/)</th>
            </tr>
        </thead>
        <tbody>
            @foreach($top_clientes as $c)
            <tr>
                <td>{{ $c['num_doc'] }}</td>
                <td>{{ $c['razon_social'] }}</td>
                <td class="text-right">{{ $c['cantidad'] }}</td>
                <td class="text-right">{{ number_format($c['monto'], 2) }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>
    @endif
@endsection
