@extends('pdf.reports.layout')

@section('content')
    {{-- KPIs --}}
    <table class="kpi-container">
        <tr>
            <td>
                <div class="kpi-box">
                    <div class="kpi-value">S/ {{ number_format($kpis['total_nc'], 2) }}</div>
                    <div class="kpi-label">Total NC ({{ $kpis['cantidad_nc'] }})</div>
                </div>
            </td>
            <td>
                <div class="kpi-box">
                    <div class="kpi-value">S/ {{ number_format($kpis['total_nd'], 2) }}</div>
                    <div class="kpi-label">Total ND ({{ $kpis['cantidad_nd'] }})</div>
                </div>
            </td>
            <td>
                <div class="kpi-box">
                    <div class="kpi-value">S/ {{ number_format($kpis['nc_netas'], 2) }}</div>
                    <div class="kpi-label">NC Netas (NC-ND)</div>
                </div>
            </td>
            <td>
                <div class="kpi-box">
                    <div class="kpi-value">S/ {{ number_format($kpis['igv_revertido'], 2) }}</div>
                    <div class="kpi-label">IGV Revertido</div>
                </div>
            </td>
        </tr>
    </table>

    {{-- Resumen por Motivo --}}
    @if(count($resumen_por_motivo) > 0)
    <div class="section-title">Resumen por Motivo (NC)</div>
    <table class="data-table">
        <thead>
            <tr>
                <th style="width:8%;">Código</th>
                <th style="width:30%;">Motivo</th>
                <th class="text-right" style="width:12%;">Cantidad</th>
                <th class="text-right" style="width:18%;">Monto Total</th>
                <th class="text-right" style="width:16%;">IGV Revertido</th>
                <th class="text-right" style="width:16%;">Base Gravada</th>
            </tr>
        </thead>
        <tbody>
            @foreach($resumen_por_motivo as $m)
            <tr>
                <td class="text-center">{{ $m['cod_motivo'] }}</td>
                <td>{{ $m['des_motivo'] }}</td>
                <td class="text-right">{{ $m['cantidad'] }}</td>
                <td class="text-right">{{ number_format($m['monto_total'], 2) }}</td>
                <td class="text-right">{{ number_format($m['igv_revertido'], 2) }}</td>
                <td class="text-right">{{ number_format($m['base_gravada_reducida'], 2) }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>
    @endif

    {{-- Notas de Crédito --}}
    @if(count($notas_credito) > 0)
    <div class="section-title">Notas de Crédito</div>
    <table class="data-table">
        <thead>
            <tr>
                <th>Nro</th>
                <th>F.Emisión</th>
                <th>Número</th>
                <th>Doc.Afectado</th>
                <th>Motivo</th>
                <th>RUC/DNI</th>
                <th>Razón Social</th>
                <th class="text-right">B.Gravada</th>
                <th class="text-right">IGV</th>
                <th class="text-right">Total</th>
                <th>Estado</th>
            </tr>
        </thead>
        <tbody>
            @foreach($notas_credito as $i => $nc)
            <tr>
                <td class="text-center">{{ $i + 1 }}</td>
                <td>{{ $nc['fecha_emision'] }}</td>
                <td>{{ $nc['numero_completo'] }}</td>
                <td>{{ $nc['doc_afectado'] }}</td>
                <td>{{ $nc['cod_motivo'] }} - {{ \Illuminate\Support\Str::limit($nc['des_motivo'], 20) }}</td>
                <td>{{ $nc['client_num_doc'] }}</td>
                <td>{{ \Illuminate\Support\Str::limit($nc['client_razon_social'], 20) }}</td>
                <td class="text-right">{{ number_format($nc['mto_oper_gravadas'], 2) }}</td>
                <td class="text-right">{{ number_format($nc['mto_igv'], 2) }}</td>
                <td class="text-right">{{ number_format($nc['mto_imp_venta'], 2) }}</td>
                <td class="text-center">
                    @if($nc['sunat_status'] === 'aceptado')
                        <span class="badge badge-success">Acept.</span>
                    @elseif($nc['sunat_status'] === 'rechazado')
                        <span class="badge badge-danger">Rech.</span>
                    @else
                        <span class="badge badge-warning">{{ ucfirst(substr($nc['sunat_status'], 0, 5)) }}</span>
                    @endif
                </td>
            </tr>
            @endforeach
        </tbody>
    </table>
    @endif

    {{-- Notas de Débito --}}
    @if(count($notas_debito) > 0)
    <div class="section-title">Notas de Débito</div>
    <table class="data-table">
        <thead>
            <tr>
                <th>Nro</th>
                <th>F.Emisión</th>
                <th>Número</th>
                <th>Doc.Afectado</th>
                <th>Motivo</th>
                <th>RUC/DNI</th>
                <th>Razón Social</th>
                <th class="text-right">B.Gravada</th>
                <th class="text-right">IGV</th>
                <th class="text-right">Total</th>
                <th>Estado</th>
            </tr>
        </thead>
        <tbody>
            @foreach($notas_debito as $i => $nd)
            <tr>
                <td class="text-center">{{ $i + 1 }}</td>
                <td>{{ $nd['fecha_emision'] }}</td>
                <td>{{ $nd['numero_completo'] }}</td>
                <td>{{ $nd['doc_afectado'] }}</td>
                <td>{{ $nd['cod_motivo'] }} - {{ \Illuminate\Support\Str::limit($nd['des_motivo'], 20) }}</td>
                <td>{{ $nd['client_num_doc'] }}</td>
                <td>{{ \Illuminate\Support\Str::limit($nd['client_razon_social'], 20) }}</td>
                <td class="text-right">{{ number_format($nd['mto_oper_gravadas'], 2) }}</td>
                <td class="text-right">{{ number_format($nd['mto_igv'], 2) }}</td>
                <td class="text-right">{{ number_format($nd['mto_imp_venta'], 2) }}</td>
                <td class="text-center">
                    @if($nd['sunat_status'] === 'aceptado')
                        <span class="badge badge-success">Acept.</span>
                    @elseif($nd['sunat_status'] === 'rechazado')
                        <span class="badge badge-danger">Rech.</span>
                    @else
                        <span class="badge badge-warning">{{ ucfirst(substr($nd['sunat_status'], 0, 5)) }}</span>
                    @endif
                </td>
            </tr>
            @endforeach
        </tbody>
    </table>
    @endif
@endsection
