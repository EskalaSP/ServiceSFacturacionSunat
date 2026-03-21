@extends('pdf.reports.layout')

@section('content')
    <table class="data-table">
        <thead>
            <tr>
                <th style="width:3%;">Nro</th>
                <th style="width:6%;">F.Emisión</th>
                <th style="width:6%;">F.Venc.</th>
                <th style="width:3%;">Tipo</th>
                <th style="width:8%;">Serie-Corr.</th>
                <th style="width:3%;">T.Doc</th>
                <th style="width:8%;">RUC/DNI</th>
                <th style="width:14%;">Razón Social</th>
                <th class="text-right" style="width:6%;">B.Gravada</th>
                <th class="text-right" style="width:6%;">B.Exoner.</th>
                <th class="text-right" style="width:6%;">B.Inafect.</th>
                <th class="text-right" style="width:5%;">B.Gratuit.</th>
                <th class="text-right" style="width:6%;">IGV</th>
                <th class="text-right" style="width:4%;">ISC</th>
                <th class="text-right" style="width:4%;">ICBPER</th>
                <th class="text-right" style="width:6%;">Total</th>
                <th style="width:3%;">Mon.</th>
                <th style="width:3%;">Estado</th>
            </tr>
        </thead>
        <tbody>
            @php $nro = 1; $currentTipo = null; @endphp
            @foreach($documentos as $doc)
                @if($currentTipo !== null && $currentTipo !== $doc['tipo_doc'])
                    {{-- Subtotal del tipo anterior --}}
                    @php $sub = $totales_por_tipo[$currentTipo]; @endphp
                    <tr class="subtotal">
                        <td colspan="8" class="text-right">Subtotal {{ $currentTipo == '01' ? 'Facturas' : ($currentTipo == '03' ? 'Boletas' : ($currentTipo == '07' ? 'NC' : 'ND')) }}:</td>
                        <td class="text-right">{{ number_format($sub['mto_oper_gravadas'], 2) }}</td>
                        <td class="text-right">{{ number_format($sub['mto_oper_exoneradas'], 2) }}</td>
                        <td class="text-right">{{ number_format($sub['mto_oper_inafectas'], 2) }}</td>
                        <td class="text-right">{{ number_format($sub['mto_oper_gratuitas'], 2) }}</td>
                        <td class="text-right">{{ number_format($sub['mto_igv'], 2) }}</td>
                        <td class="text-right">{{ number_format($sub['mto_isc'], 2) }}</td>
                        <td class="text-right">{{ number_format($sub['mto_icbper'], 2) }}</td>
                        <td class="text-right">{{ number_format($sub['mto_imp_venta'], 2) }}</td>
                        <td></td>
                        <td class="text-center">{{ $sub['cantidad'] }}</td>
                    </tr>
                @endif
                @php $currentTipo = $doc['tipo_doc']; @endphp
                <tr>
                    <td class="text-center">{{ $nro++ }}</td>
                    <td>{{ $doc['fecha_emision'] }}</td>
                    <td>{{ $doc['fecha_vencimiento'] ?? '-' }}</td>
                    <td class="text-center">{{ $doc['tipo_doc'] }}</td>
                    <td>{{ $doc['numero_completo'] }}</td>
                    <td class="text-center">{{ $doc['client_tipo_doc'] }}</td>
                    <td>{{ $doc['client_num_doc'] }}</td>
                    <td>{{ \Illuminate\Support\Str::limit($doc['client_razon_social'], 25) }}</td>
                    <td class="text-right">{{ number_format($doc['mto_oper_gravadas'], 2) }}</td>
                    <td class="text-right">{{ number_format($doc['mto_oper_exoneradas'], 2) }}</td>
                    <td class="text-right">{{ number_format($doc['mto_oper_inafectas'], 2) }}</td>
                    <td class="text-right">{{ number_format($doc['mto_oper_gratuitas'], 2) }}</td>
                    <td class="text-right">{{ number_format($doc['mto_igv'], 2) }}</td>
                    <td class="text-right">{{ number_format($doc['mto_isc'], 2) }}</td>
                    <td class="text-right">{{ number_format($doc['mto_icbper'], 2) }}</td>
                    <td class="text-right">{{ number_format($doc['mto_imp_venta'], 2) }}</td>
                    <td class="text-center">{{ $doc['tipo_moneda'] }}</td>
                    <td class="text-center">
                        @if($doc['sunat_status'] === 'aceptado')
                            <span class="badge badge-success">Acept.</span>
                        @elseif($doc['sunat_status'] === 'rechazado')
                            <span class="badge badge-danger">Rech.</span>
                        @else
                            <span class="badge badge-warning">{{ ucfirst(substr($doc['sunat_status'], 0, 5)) }}</span>
                        @endif
                    </td>
                </tr>
            @endforeach

            {{-- Subtotal del último tipo --}}
            @if($currentTipo && isset($totales_por_tipo[$currentTipo]))
                @php $sub = $totales_por_tipo[$currentTipo]; @endphp
                <tr class="subtotal">
                    <td colspan="8" class="text-right">Subtotal {{ $currentTipo == '01' ? 'Facturas' : ($currentTipo == '03' ? 'Boletas' : ($currentTipo == '07' ? 'NC' : 'ND')) }}:</td>
                    <td class="text-right">{{ number_format($sub['mto_oper_gravadas'], 2) }}</td>
                    <td class="text-right">{{ number_format($sub['mto_oper_exoneradas'], 2) }}</td>
                    <td class="text-right">{{ number_format($sub['mto_oper_inafectas'], 2) }}</td>
                    <td class="text-right">{{ number_format($sub['mto_oper_gratuitas'], 2) }}</td>
                    <td class="text-right">{{ number_format($sub['mto_igv'], 2) }}</td>
                    <td class="text-right">{{ number_format($sub['mto_isc'], 2) }}</td>
                    <td class="text-right">{{ number_format($sub['mto_icbper'], 2) }}</td>
                    <td class="text-right">{{ number_format($sub['mto_imp_venta'], 2) }}</td>
                    <td></td>
                    <td class="text-center">{{ $sub['cantidad'] }}</td>
                </tr>
            @endif

            {{-- Gran Total --}}
            <tr class="total">
                <td colspan="8" class="text-right">GRAN TOTAL:</td>
                <td class="text-right">{{ number_format($gran_total['mto_oper_gravadas'], 2) }}</td>
                <td class="text-right">{{ number_format($gran_total['mto_oper_exoneradas'], 2) }}</td>
                <td class="text-right">{{ number_format($gran_total['mto_oper_inafectas'], 2) }}</td>
                <td class="text-right">{{ number_format($gran_total['mto_oper_gratuitas'], 2) }}</td>
                <td class="text-right">{{ number_format($gran_total['mto_igv'], 2) }}</td>
                <td class="text-right">{{ number_format($gran_total['mto_isc'], 2) }}</td>
                <td class="text-right">{{ number_format($gran_total['mto_icbper'], 2) }}</td>
                <td class="text-right">{{ number_format($gran_total['mto_imp_venta'], 2) }}</td>
                <td></td>
                <td class="text-center">{{ $gran_total['cantidad'] }}</td>
            </tr>
        </tbody>
    </table>

    <div style="margin-top: 8px; font-size: 10px; font-weight: bold; text-align: right;">
        VENTA NETA DEL PERIODO: S/ {{ number_format($venta_neta, 2) }}
    </div>
@endsection
